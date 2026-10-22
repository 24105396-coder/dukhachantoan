<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nhật ký kiểm toán, nhật ký tìm kiếm và lượt xem phục vụ thống kê.
 */
return new class extends Migration
{
    public function up(): void
    {
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

        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('locale', 2);
            $table->string('query', 255);
            $table->unsignedSmallInteger('result_count');
            $table->enum('engine', ['semantic', 'fallback']);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
        });

        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 60);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('locale', 2);
            $table->date('viewed_on');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
            $table->index('viewed_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
        Schema::dropIfExists('search_logs');
        Schema::dropIfExists('audit_logs');
    }
};
