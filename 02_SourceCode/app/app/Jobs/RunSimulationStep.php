<?php

namespace App\Jobs;

use App\Models\SimulationRun;
use App\Services\SimulationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Bước mô phỏng chạy trễ at_minute phút (hàng đợi database, queue:work trong container). */
class RunSimulationStep implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $runId, public readonly int $stepIndex)
    {
    }

    public function handle(SimulationService $simulation): void
    {
        $run = SimulationRun::query()->with('scenario', 'starter')->find($this->runId);
        if ($run) {
            $simulation->runStep($run, $this->stepIndex);
        }
    }
}
