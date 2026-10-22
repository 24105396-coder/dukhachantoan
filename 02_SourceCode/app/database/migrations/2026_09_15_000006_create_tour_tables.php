<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tuyến tham quan chủ đề: tuyến, bản dịch, điểm dừng và khung giờ có sức chứa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_routes', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 150)->unique();
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->foreignId('theme_tag_id')->nullable()->constrained('tags')->nullOnDelete();
            $table->unsignedSmallInteger('duration_minutes');
            $table->decimal('distance_km', 6, 2)->nullable();
            $table->enum('difficulty', ['de', 'vua', 'kho'])->default('de');
            $table->unsignedInteger('price_vnd')->default(0);
            $table->unsignedSmallInteger('default_capacity')->default(20);
            $table->unsignedBigInteger('cover_media_id')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'province_id']);
        });

        Schema::create('tour_route_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('tour_routes')->cascadeOnDelete();
            $table->char('locale', 2);
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->text('highlights')->nullable();
            $table->unique(['route_id', 'locale']);
        });

        Schema::create('tour_route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('tour_routes')->cascadeOnDelete();
            $table->unsignedTinyInteger('stop_order');
            $table->foreignId('site_id')->constrained('heritage_sites')->cascadeOnDelete();
            $table->foreignId('artifact_id')->nullable()->constrained('artifacts')->nullOnDelete();
            $table->unsignedSmallInteger('dwell_minutes')->default(30);
            $table->string('note', 255)->nullable();
            $table->unique(['route_id', 'stop_order']);
        });

        Schema::create('tour_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('tour_routes')->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('capacity');
            $table->unsignedSmallInteger('booked_count')->default(0); // gồm cả chỗ đang giữ và đã thanh toán
            $table->string('guide_name', 120)->nullable();
            $table->char('language', 2)->default('vi');
            $table->enum('status', ['open', 'closed', 'cancelled'])->default('open');
            $table->timestamps();
            $table->unique(['route_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_slots');
        Schema::dropIfExists('tour_route_stops');
        Schema::dropIfExists('tour_route_translations');
        Schema::dropIfExists('tour_routes');
    }
};
