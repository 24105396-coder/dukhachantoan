<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An toàn du khách (F8): báo an toàn/cần hỗ trợ và báo sự cố thực địa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safety_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // null khi báo qua liên kết chia sẻ
            $table->foreignId('alert_id')->nullable()->constrained('alerts')->nullOnDelete();
            $table->enum('status', ['safe', 'need_help', 'unknown']);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedInteger('accuracy_m')->nullable();
            $table->text('message')->nullable();
            $table->char('locale', 2)->default('vi');
            $table->enum('source', ['app', 'share_link', 'agency'])->default('app');
            $table->timestamp('checked_at');
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->text('response_note')->nullable();
            $table->enum('response_status', ['open', 'in_progress', 'closed'])->default('open');
            $table->timestamps();
            $table->index(['trip_id', 'checked_at']);
            $table->index(['status', 'response_status']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->foreign('checkin_id')->references('id')->on('safety_checkins')->nullOnDelete();
        });

        Schema::create('incident_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            $table->foreignId('zone_id')->constrained('zones')->restrictOnDelete();
            $table->foreignId('hazard_type_id')->constrained('hazard_types')->restrictOnDelete();
            $table->string('title', 190);
            $table->text('description')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->foreignId('photo_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->boolean('is_simulated')->default(false);
            $table->timestamps();
            $table->index(['status', 'zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_reports');
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['checkin_id']);
        });
        Schema::dropIfExists('safety_checkins');
    }
};
