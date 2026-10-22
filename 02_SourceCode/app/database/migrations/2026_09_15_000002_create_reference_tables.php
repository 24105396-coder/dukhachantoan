<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng tham chiếu: tỉnh/thành và thời kỳ lịch sử (có bản dịch).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->string('name', 100);
            $table->string('name_en', 100);
            $table->enum('region', ['bac', 'trung', 'nam']);
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->timestamps();
        });

        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->smallInteger('start_year');          // năm âm = TCN
            $table->smallInteger('end_year')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('period_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->char('locale', 2);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->unique(['period_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_translations');
        Schema::dropIfExists('periods');
        Schema::dropIfExists('provinces');
    }
};
