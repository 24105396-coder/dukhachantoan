<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nguồn dữ liệu, loại rủi ro và cảnh báo (F2). Mọi cảnh báo ghi nguồn + thời điểm cập nhật.
 * alerts.scenario_id được gắn khóa ngoại ở migration simulation (bảng kịch bản tạo sau).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_sources', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 190);
            $table->enum('source_type', ['weather', 'disaster', 'security', 'health', 'traffic', 'manual', 'simulation']);
            $table->enum('provider', ['openmeteo', 'manual', 'simulated']);
            $table->string('base_url', 500)->nullable();
            $table->string('attribution', 255)->nullable();
            $table->string('license', 120)->nullable();
            $table->unsignedSmallInteger('poll_interval_min')->nullable();
            $table->timestamp('last_polled_at')->nullable();
            $table->enum('last_status', ['ok', 'error'])->nullable();
            $table->text('last_error')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hazard_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('icon', 60)->nullable();
            $table->string('color', 16)->nullable();
            $table->unsignedTinyInteger('default_severity')->default(2);   // 1..4
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('hazard_type_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hazard_type_id')->constrained('hazard_types')->cascadeOnDelete();
            $table->char('locale', 2);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['hazard_type_id', 'locale']);
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();                      // AL-xxxx, không lộ id tự tăng
            $table->foreignId('source_id')->constrained('alert_sources')->restrictOnDelete();
            $table->foreignId('hazard_type_id')->constrained('hazard_types')->restrictOnDelete();
            $table->unsignedTinyInteger('severity');                    // 1 info | 2 advisory | 3 warning | 4 emergency
            $table->enum('status', ['draft', 'active', 'updated', 'expired', 'cancelled'])->default('draft');
            $table->json('geometry')->nullable();                       // Polygon | {type: Circle, center, radius_km}; null = theo zones
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('updated_at_source')->nullable();
            $table->string('source_name', 255);
            $table->string('source_url', 500)->nullable();
            $table->json('raw_payload')->nullable();
            $table->boolean('is_simulated')->default(false);
            $table->unsignedBigInteger('scenario_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'severity']);
            $table->index(['status', 'ends_at']);
            $table->index('hazard_type_id');
        });

        Schema::create('alert_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_id')->constrained('alerts')->cascadeOnDelete();
            $table->char('locale', 2);
            $table->string('headline', 255);
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('is_machine_translated')->default(false);
            $table->timestamps();
            $table->unique(['alert_id', 'locale']);
            $table->fullText(['headline', 'description'], 'alert_translations_fulltext');
        });

        Schema::create('alert_zones', function (Blueprint $table) {
            $table->foreignId('alert_id')->constrained('alerts')->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['alert_id', 'zone_id']);
            $table->index('zone_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_zones');
        Schema::dropIfExists('alert_translations');
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('hazard_type_translations');
        Schema::dropIfExists('hazard_types');
        Schema::dropIfExists('alert_sources');
    }
};
