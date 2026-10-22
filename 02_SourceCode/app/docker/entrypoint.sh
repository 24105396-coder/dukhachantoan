#!/bin/bash
# Entrypoint DuKhachAnToan: wait for MySQL, run migrations, seed once, cache config,
# start scheduler + queue worker in background, start Apache.
set -e
cd /var/www/html

# Mọi lệnh artisan/ghi tệp chạy dưới www-data (cùng user với Apache) để storage/logs,
# storage/app/** không bị root chiếm quyền -> Apache vẫn ghi được laravel.log.
run_as_www() {
  if command -v setpriv >/dev/null 2>&1; then
    setpriv --reuid=www-data --regid=www-data --init-groups -- "$@"
  else
    su -s /bin/sh www-data -c "$(printf '%q ' "$@")"
  fi
}

# Volume mới gắn vào (hoặc tệp do bản cũ tạo bằng root) -> trả quyền cho www-data trước
mkdir -p storage/logs storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 1. APP_KEY: generate once and persist in the storage volume (never committed to Git)
KEY_FILE=storage/app/.appkey
if [ -z "$APP_KEY" ]; then
  if [ ! -f "$KEY_FILE" ]; then
    run_as_www sh -c "php -r 'echo \"base64:\".base64_encode(random_bytes(32)).PHP_EOL;' > $KEY_FILE"
  fi
  export APP_KEY="$(cat $KEY_FILE)"
fi

# 2. Wait for the database (max ~2 minutes)
echo "[entrypoint] waiting for database ${DB_HOST}:${DB_PORT:-3306} ..."
for i in $(seq 1 60); do
  if php -r 'try{new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT")?:3306),getenv("DB_USERNAME"),getenv("DB_PASSWORD"));exit(0);}catch(Exception $e){exit(1);}'; then
    break
  fi
  sleep 2
done

# (DB test dukhach_test do MySQL tạo qua db-init/01-test-db.sql; app không cần quyền root MySQL)

# 3. Migrate and seed (seed only when the roles table is empty)
run_as_www php artisan migrate --force
if [ "$(run_as_www php artisan app:is-seeded)" != "yes" ]; then
  echo "[entrypoint] seeding sample data (roles, provinces, accounts, hazards, zones, alerts, trips) ..."
  run_as_www php artisan db:seed --force
  # Dữ liệu trình diễn: đối sánh cảnh báo mẫu với hành trình mẫu rồi gửi một lượt thông báo
  # (SMS là mô phỏng, chỉ ghi log; email dùng MAIL_MAILER=log) để trang Thông báo/Dashboard có số liệu ngay
  echo "[entrypoint] matching sample alerts with sample trips and dispatching demo notifications ..."
  run_as_www php artisan app:match-alerts || true
  run_as_www php artisan app:dispatch-notifications || true
fi

# public/storage symlink nằm ngoài storage/ nên tạo bằng root là đủ
php artisan storage:link >/dev/null 2>&1 || true
run_as_www php artisan optimize:clear >/dev/null
run_as_www php artisan optimize >/dev/null

# 4. Tiến trình nền (thay supervisord/cron), mỗi tiến trình tự khởi động lại nếu thoát:
#    - schedule:work: app:dispatch-notifications mỗi phút, app:match-alerts 5', app:expire-alerts 10',
#      app:fetch-weather 30', app:backup-db 02:00 → storage/logs/scheduler.log
#    - queue:work database: job nặng (đối sánh sau phát hành) → storage/logs/queue.log
#    Tắt bằng BACKGROUND_WORKERS=false (vd. khi chạy worker ở dịch vụ riêng trên PaaS).
keep_running() {
  name="$1"; shift
  log="storage/logs/$name.log"
  (
    while true; do
      # Giới hạn tệp log ~5 MB để không phình volume
      [ "$(stat -c%s "$log" 2>/dev/null || echo 0)" -gt 5242880 ] && : > "$log"
      echo "[$(date '+%F %T')] start $name" >> "$log"
      run_as_www "$@" >> "$log" 2>&1 || true
      echo "[$(date '+%F %T')] $name exited, restarting in 5s" >> "$log"
      sleep 5
    done
  ) &
}
if [ "${BACKGROUND_WORKERS:-true}" = "true" ]; then
  if [ "${BACKUP_ENABLED:-true}" = "true" ]; then
    (run_as_www sh -c 'php artisan app:backup-db >> storage/logs/backup.log 2>&1' || true) &
  fi
  keep_running scheduler php artisan schedule:work
  keep_running queue php artisan queue:work database --tries=1 --sleep=3 --max-time=3600
  echo "[entrypoint] background workers started (scheduler, queue)"
fi

# Lần cuối bảo đảm mọi tệp sinh ra ở trên thuộc www-data
chown -R www-data:www-data storage bootstrap/cache

# 5. PaaS (Railway/Render) cấp cổng qua biến PORT: đổi Apache sang cổng đó (mặc định 80 khi chạy Docker Compose)
if [ -n "${PORT:-}" ] && [ "$PORT" != "80" ]; then
  sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
  sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
  echo "[entrypoint] apache listening on port ${PORT}"
fi

# 6. Bảo đảm chỉ một MPM được nạp (một số nền tảng build để sót mpm_event cạnh mpm_prefork → AH00534)
if [ -d /etc/apache2/mods-enabled ]; then
  a2dismod -q mpm_event mpm_worker >/dev/null 2>&1 || true
  a2enmod -q mpm_prefork >/dev/null 2>&1 || true
  echo "[entrypoint] apache mpm: $(ls /etc/apache2/mods-enabled | grep -i mpm | tr '\n' ' ')"
  apache2ctl -t 2>&1 | sed 's/^/[entrypoint] configtest: /' | head -5
fi

echo "[entrypoint] starting web server"
exec "$@"
