<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hành trình du khách (F1): hành trình, chặng theo vùng/điểm đến, thành viên đoàn (dữ liệu mô phỏng).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();                       // TR-xxxxxxxx ngẫu nhiên
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 190);
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('group_size')->default(1);
            $table->string('nationality', 60)->nullable();
            $table->char('preferred_locale', 2)->default('vi');
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_email', 190)->nullable();
            $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('active');
            $table->char('share_token', 40)->unique();                  // xem công khai theo token
            $table->unsignedTinyInteger('risk_level_cached')->default(0);
            $table->timestamp('last_alert_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'start_date', 'end_date']);
            $table->index(['user_id', 'status']);
            $table->index(['partner_id', 'status']);
        });

        Schema::create('trip_legs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->unsignedSmallInteger('leg_order')->default(1);
            $table->foreignId('zone_id')->constrained('zones')->restrictOnDelete();
            $table->foreignId('destination_id')->nullable()->constrained('destinations')->nullOnDelete();
            $table->string('title', 190)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('lodging', 190)->nullable();
            $table->string('transport', 120)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['trip_id', 'leg_order']);
            $table->index(['zone_id', 'starts_at', 'ends_at']);
        });

        Schema::create('trip_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->string('full_name', 120);
            $table->string('phone', 20)->nullable();
            $table->string('nationality', 60)->nullable();
            $table->enum('age_group', ['child', 'adult', 'senior'])->default('adult');
            $table->string('special_needs', 255)->nullable();
            $table->boolean('is_leader')->default(false);
            $table->timestamps();
            $table->index('trip_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_members');
        Schema::dropIfExists('trip_legs');
        Schema::dropIfExists('trips');
    }
};
