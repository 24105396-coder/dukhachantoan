<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thẻ chủ đề phân cấp, đa ngữ, gắn cho di sản / hiện vật / nhân vật / tuyến tham quan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->enum('tag_group', ['chu_de', 'loai_hinh', 'ton_giao', 'phong_cach', 'vat_lieu', 'thoi_ky', 'vung_mien']);
            $table->foreignId('parent_id')->nullable()->constrained('tags')->nullOnDelete();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();
            $table->index('tag_group');
        });

        Schema::create('tag_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->char('locale', 2);
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->unique(['tag_id', 'locale']);
            $table->index(['locale', 'name']);
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->string('taggable_type', 60);
            $table->unsignedBigInteger('taggable_id');
            $table->primary(['tag_id', 'taggable_type', 'taggable_id']);
            $table->index(['taggable_type', 'taggable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tag_translations');
        Schema::dropIfExists('tags');
    }
};
