<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đối sánh cảnh báo – chặng hành trình (F3) và thông báo phân cấp có thử lại (F4).
 * notifications.checkin_id được gắn khóa ngoại ở migration safety (bảng check-in tạo sau).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_id')->constrained('alerts')->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignId('leg_id')->constrained('trip_legs')->cascadeOnDelete();
            $table->enum('match_type', ['zone', 'geometry', 'destination']);
            $table->decimal('distance_km', 8, 1)->nullable();
            $table->timestamp('overlap_from')->nullable();
            $table->timestamp('overlap_to')->nullable();
            $table->enum('status', ['new', 'notified', 'acknowledged', 'resolved'])->default('new');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->unique(['alert_id', 'leg_id']);
            $table->index(['trip_id', 'status']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            $table->foreignId('alert_id')->nullable()->constrained('alerts')->nullOnDelete();
            $table->foreignId('match_id')->nullable()->constrained('alert_matches')->nullOnDelete();
            $table->unsignedBigInteger('checkin_id')->nullable();
            $table->enum('channel', ['inapp', 'email', 'sms']);
            $table->unsignedTinyInteger('level');                       // 1..4
            $table->char('locale', 2)->default('vi');
            $table->string('title', 255);
            $table->text('body')->nullable();
            $table->string('action_url', 500)->nullable();
            $table->enum('status', ['queued', 'sending', 'sent', 'failed', 'read', 'cancelled'])->default('queued');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->text('last_error')->nullable();
            $table->string('provider_ref', 120)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_attempt_at']);
            $table->index(['user_id', 'read_at']);
            $table->index(['alert_id', 'channel']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt_no');
            $table->enum('channel', ['inapp', 'email', 'sms']);
            $table->enum('status', ['sent', 'failed']);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamps();
            $table->index(['notification_id', 'attempt_no']);
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->json('channels_level1')->nullable();                // ["inapp","email","sms"]
            $table->json('channels_level2')->nullable();
            $table->json('channels_level3')->nullable();
            $table->json('channels_level4')->nullable();
            $table->time('quiet_from')->nullable();
            $table->time('quiet_to')->nullable();
            $table->boolean('digest_daily')->default(true);
            $table->char('language', 2)->default('vi');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('alert_matches');
    }
};
