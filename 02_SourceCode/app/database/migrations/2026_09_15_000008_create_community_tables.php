<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đóng góp nội dung cộng đồng (có kiểm duyệt) và bộ sưu tập cá nhân.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject_type', 60)->nullable();      // null = đề xuất hồ sơ mới
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->enum('contribution_type', ['new_site', 'new_artifact', 'correction', 'story', 'media']);
            $table->char('locale', 2)->default('vi');
            $table->string('title', 255);
            $table->longText('body');
            $table->string('attachment_path', 255)->nullable();
            $table->json('suggested_tags')->nullable();           // kết quả gợi ý từ mô-đun Python
            $table->enum('status', ['pending', 'approved', 'rejected', 'needs_revision'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_note', 500)->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject_type', 60);
            $table->unsignedBigInteger('subject_id');
            $table->timestamps();
            $table->unique(['user_id', 'subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('contributions');
    }
};
