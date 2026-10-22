<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ba thực thể lõi: di sản, hiện vật, nhân vật lịch sử và quan hệ di sản – nhân vật.
 * Mọi nội dung văn bản nằm ở bảng interpretations (tách nội dung khỏi bản dịch).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heritage_sites', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 150)->unique();
            $table->enum('site_type', ['di_tich', 'danh_thang', 'di_san_phi_vat_the', 'bao_tang', 'lang_nghe']);
            $table->foreignId('period_id')->nullable()->constrained('periods')->nullOnDelete();
            $table->foreignId('province_id')->constrained('provinces')->restrictOnDelete();
            $table->string('address', 255)->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->enum('recognition_level', ['unesco', 'quoc_gia_dac_biet', 'quoc_gia', 'cap_tinh', 'chua_xep_hang'])->default('chua_xep_hang');
            $table->smallInteger('year_recognized')->nullable();
            $table->smallInteger('year_built')->nullable();
            $table->string('visiting_hours', 120)->nullable();
            $table->unsignedInteger('ticket_price_vnd')->nullable();
            $table->string('website', 255)->nullable();
            $table->unsignedBigInteger('cover_media_id')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->unsignedInteger('view_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'province_id']);
            $table->index(['status', 'site_type']);
            $table->index(['lat', 'lng']);
            $table->index('recognition_level');
        });

        Schema::create('artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('heritage_sites')->cascadeOnDelete();
            $table->string('slug', 150)->unique();
            $table->string('inventory_code', 40)->nullable()->unique();
            $table->enum('artifact_type', ['tuong', 'bia_ky', 'do_gom', 'do_dong', 'vu_khi', 'trang_phuc', 'kien_truc', 'tai_lieu', 'bao_vat', 'khac']);
            $table->string('material', 120)->nullable();
            $table->foreignId('period_id')->nullable()->constrained('periods')->nullOnDelete();
            $table->smallInteger('year_from')->nullable();
            $table->smallInteger('year_to')->nullable();
            $table->decimal('height_cm', 8, 2)->nullable();
            $table->decimal('width_cm', 8, 2)->nullable();
            $table->decimal('depth_cm', 8, 2)->nullable();
            $table->decimal('weight_kg', 8, 2)->nullable();
            $table->boolean('is_national_treasure')->default(false);
            $table->string('model3d_url', 255)->nullable();    // phục vụ xem AR trên web
            $table->unsignedBigInteger('cover_media_id')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->unsignedInteger('view_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'site_id']);
            $table->index('artifact_type');
        });

        Schema::create('historical_figures', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 150)->unique();
            $table->enum('figure_type', ['vua_chua', 'tuong_linh', 'nha_van_hoa', 'anh_hung', 'nhan_vat_ton_giao', 'nghe_nhan', 'khac']);
            $table->smallInteger('birth_year')->nullable();
            $table->smallInteger('death_year')->nullable();
            $table->foreignId('period_id')->nullable()->constrained('periods')->nullOnDelete();
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete(); // quê quán
            $table->unsignedBigInteger('portrait_media_id')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->unsignedInteger('view_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'figure_type']);
            $table->index('birth_year');
        });

        Schema::create('site_figure', function (Blueprint $table) {
            $table->foreignId('site_id')->constrained('heritage_sites')->cascadeOnDelete();
            $table->foreignId('figure_id')->constrained('historical_figures')->cascadeOnDelete();
            $table->enum('relation_type', ['sang_lap', 'duoc_tho', 'gan_lien', 'trung_tu', 'sinh_ra', 'an_tang']);
            $table->primary(['site_id', 'figure_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_figure');
        Schema::dropIfExists('historical_figures');
        Schema::dropIfExists('artifacts');
        Schema::dropIfExists('heritage_sites');
    }
};
