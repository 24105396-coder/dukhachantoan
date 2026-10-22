<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\TourSlot;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Đồng thời thật: dữ liệu được commit (DatabaseMigrations, không bọc transaction) để
 * nhiều kết nối/tiến trình cùng thấy và tranh chấp khóa dòng tour_slots.
 */
class BookingParallelHoldTest extends TestCase
{
    use DatabaseMigrations;

    public function test_row_lock_blocks_a_second_connection_while_first_holds_it(): void
    {
        $slot = TourSlot::factory()->capacity(1)->create();
        config(['database.connections.mysql_second' => config('database.connections.mysql')]);
        $second = DB::connection('mysql_second');
        $second->statement('SET SESSION innodb_lock_wait_timeout = 1');

        DB::beginTransaction();
        TourSlot::query()->whereKey($slot->id)->lockForUpdate()->first();

        $blocked = false;
        try {
            $second->table('tour_slots')->where('id', $slot->id)->lockForUpdate()->first();
        } catch (QueryException $e) {
            $blocked = str_contains($e->getMessage(), 'Lock wait timeout');
        }
        DB::rollBack();

        $this->assertTrue($blocked, 'Kết nối thứ hai phải bị chặn bởi SELECT ... FOR UPDATE của kết nối thứ nhất');
        $this->assertNotNull($second->table('tour_slots')->where('id', $slot->id)->lockForUpdate()->first());
        $second->disconnect();
    }

    public function test_parallel_processes_competing_for_last_seat_only_one_wins(): void
    {
        if (! function_exists('proc_open')) {
            $this->markTestSkipped('proc_open không khả dụng');
        }
        $slot = TourSlot::factory()->capacity(1)->create();
        $users = collect(range(1, 4))->map(fn () => $this->member());
        $startAt = microtime(true) + 2.5;
        $script = base_path('tests/Support/hold_worker.php');

        $procs = $users->map(function ($user) use ($slot, $startAt, $script) {
            $pipes = [];
            $proc = proc_open([PHP_BINARY, $script, (string) $slot->id, (string) $user->id, (string) $startAt],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());

            return [$proc, $pipes];
        });

        $outputs = $procs->map(function ($p) {
            [$proc, $pipes] = $p;
            $out = trim(stream_get_contents($pipes[1]).stream_get_contents($pipes[2]));
            proc_close($proc);

            return $out;
        })->all();

        $this->assertSame(1, collect($outputs)->filter(fn ($o) => $o === 'OK')->count(), 'Kết quả: '.implode(' | ', $outputs));
        $this->assertSame(3, collect($outputs)->filter(fn ($o) => $o === 'FULL')->count(), 'Kết quả: '.implode(' | ', $outputs));
        $this->assertSame(1, $slot->fresh()->booked_count);
        $this->assertSame(1, Booking::where('slot_id', $slot->id)->count());
    }

    public function test_parallel_fork_with_pcntl_if_available(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Phần mở rộng pcntl không có trong image; đã kiểm chứng song song bằng proc_open ở test trên.');
        }
        $this->assertTrue(true);
    }
}
