<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hỗ trợ: danh bạ khẩn cấp theo địa phương (F6) và hướng dẫn ứng phó đa ngữ (F7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete(); // null = toàn quốc
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->enum('contact_type', ['police', 'fire', 'ambulance', 'rescue', 'coast_guard', 'tourist_support', 'hospital', 'embassy', 'hotline_national']);
            $table->string('phone', 40);
            $table->string('phone_alt', 40)->nullable();
            $table->string('address', 255)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('hours', 60)->nullable();
            $table->json('languages')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->date('verified_at')->nullable();
            $table->string('source_name', 255)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['province_id', 'contact_type']);
        });

        Schema::create('emergency_contact_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('emergency_contacts')->cascadeOnDelete();
            $table->char('locale', 2);
            $table->string('name', 190);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['contact_id', 'locale']);
        });

        Schema::create('response_guides', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->foreignId('hazard_type_id')->constrained('hazard_types')->restrictOnDelete();
            $table->unsignedTinyInteger('min_severity')->default(1);
            $table->enum('audience', ['all', 'international', 'elderly', 'family'])->default('all');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['hazard_type_id', 'status']);
        });

        Schema::create('response_guide_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guide_id')->constrained('response_guides')->cascadeOnDelete();
            $table->char('locale', 2);
            $table->string('title', 190);
            $table->text('summary')->nullable();
            $table->json('before_steps')->nullable();
            $table->json('during_steps')->nullable();
            $table->json('after_steps')->nullable();
            $table->json('sources')->nullable();
            $table->boolean('is_machine_translated')->default(false);
            $table->timestamps();
            $table->unique(['guide_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('response_guide_translations');
        Schema::dropIfExists('response_guides');
        Schema::dropIfExists('emergency_contact_translations');
        Schema::dropIfExists('emergency_contacts');
    }
};
