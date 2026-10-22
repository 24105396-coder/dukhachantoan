<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lớp diễn giải (đa ngữ, đa mức chuyên sâu), tài nguyên đa phương tiện có siêu dữ liệu
 * nguồn gốc – bản quyền, và dòng thời gian lịch sử.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interpretations', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 60);                  // App\Models\HeritageSite | Artifact | HistoricalFigure
            $table->unsignedBigInteger('subject_id');
            $table->char('locale', 2);                            // vi | en
            $table->unsignedTinyInteger('depth_level');           // 1 phổ thông, 2 chuyên sâu, 3 học thuật
            $table->string('title', 255);
            $table->text('summary')->nullable();
            $table->longText('body');
            $table->text('sources')->nullable();                  // danh sách nguồn trích dẫn, mỗi dòng một nguồn
            $table->boolean('is_machine_translated')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['subject_type', 'subject_id', 'locale', 'depth_level'], 'interp_subject_locale_depth_unique');
            $table->index(['subject_type', 'subject_id']);
            $table->index(['locale', 'depth_level']);
            $table->fullText(['title', 'summary', 'body'], 'interp_fulltext');
        });

        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 60);
            $table->unsignedBigInteger('subject_id');
            $table->enum('media_type', ['image', 'audio', 'video', 'model3d', 'document']);
            $table->char('locale', 2)->nullable();                // thuyết minh âm thanh theo ngôn ngữ
            $table->string('path', 255)->nullable();              // tệp trong storage/app/public
            $table->string('external_url', 500)->nullable();      // hoặc liên kết ngoài
            $table->string('mime', 80)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('duration_sec')->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_cover')->default(false);
            // Siêu dữ liệu nguồn gốc và bản quyền (bắt buộc theo đề tài ĐT-04)
            $table->string('source_name', 190)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->string('author', 150)->nullable();
            $table->enum('license', ['public_domain', 'cc_by', 'cc_by_sa', 'cc_by_nc', 'all_rights_reserved', 'simulated'])->default('simulated');
            $table->string('rights_holder', 190)->nullable();
            $table->date('captured_at')->nullable();
            $table->text('provenance_note')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id', 'media_type']);
            $table->index('license');
        });

        Schema::create('media_captions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('media_assets')->cascadeOnDelete();
            $table->char('locale', 2);
            $table->string('caption', 500);
            $table->text('transcript')->nullable();               // lời thuyết minh dạng văn bản
            $table->unique(['media_id', 'locale']);
        });

        Schema::create('timeline_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->nullable()->constrained('heritage_sites')->cascadeOnDelete();
            $table->foreignId('figure_id')->nullable()->constrained('historical_figures')->cascadeOnDelete();
            $table->foreignId('period_id')->nullable()->constrained('periods')->nullOnDelete();
            $table->enum('event_type', ['xay_dung', 'trung_tu', 'cong_nhan', 'su_kien', 'sinh', 'mat', 'phat_hien', 'khac']);
            $table->smallInteger('event_year');
            $table->unsignedTinyInteger('event_month')->nullable();
            $table->unsignedTinyInteger('event_day')->nullable();
            $table->smallInteger('end_year')->nullable();
            $table->unsignedTinyInteger('importance')->default(2); // 1 thấp, 2 vừa, 3 cao
            $table->timestamps();
            $table->index('event_year');
            $table->index(['site_id', 'event_year']);
        });

        Schema::create('timeline_event_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('timeline_events')->cascadeOnDelete();
            $table->char('locale', 2);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->unique(['event_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timeline_event_translations');
        Schema::dropIfExists('timeline_events');
        Schema::dropIfExists('media_captions');
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('interpretations');
    }
};
