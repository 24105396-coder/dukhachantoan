<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Địa lý: vùng rủi ro (tỉnh + vùng đặc thù, hình học GeoJSON lưu JSON) và điểm đến du lịch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->enum('zone_type', ['province', 'district', 'coast', 'mountain', 'island', 'urban', 'custom']);
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->string('name', 150);
            $table->string('name_en', 150)->nullable();
            $table->json('geometry')->nullable();                 // GeoJSON Polygon | MultiPolygon, tọa độ [lng, lat]
            $table->decimal('centroid_lat', 10, 7);
            $table->decimal('centroid_lng', 10, 7);
            $table->decimal('radius_km', 7, 2)->nullable();       // dùng khi không có polygon
            $table->string('population_note', 255)->nullable();
            $table->string('source_name', 190)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['zone_type', 'is_active']);
            $table->index('province_id');
        });

        Schema::create('destinations', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->foreignId('zone_id')->constrained('zones')->restrictOnDelete();
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->enum('dest_type', ['bai_bien', 'nui', 'dao', 'do_thi', 'di_tich', 'vuon_quoc_gia', 'cua_khau', 'san_bay', 'ben_cang', 'khac']);
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->integer('altitude_m')->nullable();
            $table->boolean('is_popular')->default(false);
            $table->timestamps();
            $table->index(['zone_id', 'is_popular']);
        });

        Schema::create('destination_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_id')->constrained('destinations')->cascadeOnDelete();
            $table->char('locale', 2);
            $table->string('name', 190);
            $table->text('description')->nullable();
            $table->text('safety_note')->nullable();
            $table->timestamps();
            $table->unique(['destination_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destination_translations');
        Schema::dropIfExists('destinations');
        Schema::dropIfExists('zones');
    }
};
