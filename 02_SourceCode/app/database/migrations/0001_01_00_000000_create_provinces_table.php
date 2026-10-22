<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tỉnh/thành (34 đơn vị sau sắp xếp 2025). Chạy trước bảng users vì users.province_id
 * (cơ quan hỗ trợ du khách phụ trách tỉnh) tham chiếu tới đây.
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
    }

    public function down(): void
    {
        Schema::dropIfExists('provinces');
    }
};
