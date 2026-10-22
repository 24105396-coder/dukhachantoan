<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * view_count được cộng dồn theo lô (lệnh app:sync-view-counts mỗi phút) thay vì UPDATE từng lượt xem:
 * 100 khách cùng mở một di sản không còn tranh khóa dòng heritage_sites (qa_4).
 * counted_at NULL = lượt xem chưa cộng vào view_count.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->timestamp('counted_at')->nullable()->after('created_at');
            $table->index('counted_at');
        });
        // Lượt xem cũ đã được cộng trực tiếp bởi mã trước đây
        DB::table('page_views')->update(['counted_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->dropIndex(['counted_at']);
            $table->dropColumn('counted_at');
        });
    }
};
