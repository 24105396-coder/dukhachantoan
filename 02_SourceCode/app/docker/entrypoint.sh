#!/bin/bash
# Entrypoint: wait for MySQL, run migrations, seed once, cache config, start Apache.
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

# 1b. Khóa HMAC cổng thanh toán sandbox: thiếu hoặc còn giá trị mẫu công khai → sinh ngẫu nhiên 32 byte một lần, lưu trong volume
SECRET_FILE=storage/app/.payment_secret
if [ -z "$PAYMENT_SANDBOX_SECRET" ] || [ "$PAYMENT_SANDBOX_SECRET" = "sandbox-secret-change-me" ]; then
  if [ ! -s "$SECRET_FILE" ]; then
    run_as_www sh -c "umask 077; php -r 'echo bin2hex(random_bytes(32)).PHP_EOL;' > $SECRET_FILE"
    echo "[entrypoint] generated PAYMENT_SANDBOX_SECRET in $SECRET_FILE"
  fi
  export PAYMENT_SANDBOX_SECRET="$(cat $SECRET_FILE)"
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
  echo "[entrypoint] seeding sample heritage data ..."
  run_as_www php artisan db:seed --force
fi
# 3a. Ảnh bìa thật từ Wikimedia Commons theo database/data/media_external.json (idempotent, cập nhật cả CSDL đã seed trước)
run_as_www php artisan app:import-external-media || true
# 3c. Vẽ lại SVG minh họa theo mẫu hiện hành (phông chữ, màu) cho CSDL đã seed từ trước
run_as_www php artisan app:render-illustrations || true
# 3b. Dựng lại chỉ mục tìm kiếm của analytics ngay sau seed (analytics có thể đã đánh chỉ mục khi seed còn dở).
#     Thử tối đa 10 lần cách 3 giây vì analytics có thể lên sau; thất bại không chặn khởi động.
reindex_analytics() {
  url="${ANALYTICS_URL:-http://analytics:8001}"
  url="${url%/}/reindex"
  for i in $(seq 1 10); do
    if command -v curl >/dev/null 2>&1; then
      result=$(curl -fsS -m 60 -X POST -H "X-Analytics-Token: ${ANALYTICS_TOKEN}" -H "Accept: application/json" "$url" 2>&1) && {
        echo "[entrypoint] analytics reindex: $result"; return 0; }
    else
      result=$(php -r '$ctx=stream_context_create(["http"=>["method"=>"POST","timeout"=>60,"header"=>"X-Analytics-Token: ".getenv("ANALYTICS_TOKEN")."\r\nAccept: application/json\r\nContent-Length: 0\r\n"]]); $r=@file_get_contents($argv[1],false,$ctx); if($r===false){fwrite(STDERR,"request failed");exit(1);} echo $r;' "$url" 2>&1) && {
        echo "[entrypoint] analytics reindex: $result"; return 0; }
    fi
    sleep 3
  done
  echo "[entrypoint] analytics reindex: failed after 10 attempts ($result) - search uses MySQL fallback until next scheduled reindex"
  return 0
}
reindex_analytics || true

# public/storage symlink nằm ngoài storage/ nên tạo bằng root là đủ
php artisan storage:link >/dev/null 2>&1 || true
run_as_www php artisan optimize:clear >/dev/null
run_as_www php artisan optimize >/dev/null

# 4. Lịch nền (thay cron): sao lưu ngay khi khởi động rồi chạy schedule:run mỗi phút
#    (app:expire-holds mỗi phút, app:backup-db 02:00 hằng ngày, ghi storage/logs/backup.log)
if [ "${BACKUP_ENABLED:-true}" = "true" ]; then
  (
    run_as_www sh -c 'php artisan app:backup-db >> storage/logs/backup.log 2>&1' || true
    while true; do
      sleep 60
      # Giới hạn scheduler.log ~1 MB để không phình volume
      run_as_www sh -c 'f=storage/logs/scheduler.log; [ "$(stat -c%s "$f" 2>/dev/null || echo 0)" -gt 1048576 ] && : > "$f"; php artisan schedule:run >> "$f" 2>&1' || true
    done
  ) &
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
