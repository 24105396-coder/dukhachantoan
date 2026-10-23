<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Giữ từ mã nền kế thừa: tệp đa phương tiện (ảnh hướng dẫn, ảnh sự cố, ảnh vùng) kèm chú thích đa ngữ
 * và nhật ký kiểm toán.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 60);                   // alias morph: guide | alert | incident | zone
            $table->unsignedBigInteger('subject_id');
            $table->enum('media_type', ['image', 'audio', 'video', 'document']);
            $table->char('locale', 2)->nullable();
            $table->string('path', 255)->nullable();              // tệp trong storage/app/public
            $table->string('external_url', 500)->nullable();
            $table->string('mime', 80)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('duration_sec')->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->string('source_name', 190)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->string('author', 150)->nullable();
            $table->enum('license', ['public_domain', 'cc_by', 'cc_by_sa', 'cc_by_nc', 'all_rights_reserved', 'user_upload', 'simulated'])->default('simulated');
            $table->string('rights_holder', 190)->nullable();
            $table->date('captured_at')->nullable();
            $table->text('provenance_note')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id', 'media_type']);
        });

        Schema::create('media_captions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('media_assets')->cascadeOnDelete();
            $table->char('locale', 2);
            $table->string('caption', 500);
            $table->text('transcript')->nullable();
            $table->unique(['media_id', 'locale']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
            $table->index(['subject_type', 'subject_id']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('media_captions');
        Schema::dropIfExists('media_assets');
    }
};
