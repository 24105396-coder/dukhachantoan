<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ScenarioItem;
use App\Models\SimulationRun;
use App\Models\SimulationScenario;
use App\Services\SimulationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Kịch bản mô phỏng (chỉ admin): chạy, kết thúc lần chạy. */
class ScenarioController extends Controller
{
    public const RECENT_RUNS = 20;

    public function index(): Response
    {
        $this->authorize('run-simulation');

        return Inertia::render('Admin/Scenarios/Index', [
            'scenarios' => ScenarioItem::list(SimulationScenario::query()->with(ScenarioItem::RELATIONS)->orderBy('id')->get()),
            'runs' => SimulationRun::query()->with('scenario', 'starter:id,name')->latest('started_at')->latest('id')
                ->limit(self::RECENT_RUNS)->get()->map(fn (SimulationRun $r) => [
                    'id' => $r->id,
                    'scenario' => $r->scenario ? ['slug' => $r->scenario->slug, 'name' => $r->scenario->localized('name')] : null,
                    'started_at' => $r->started_at?->toIso8601String(),
                    'finished_at' => $r->finished_at?->toIso8601String(),
                    'alerts_created' => (int) $r->alerts_created,
                    'matches_created' => (int) $r->matches_created,
                    'notifications_created' => (int) $r->notifications_created,
                    'starter' => $r->starter ? ['name' => $r->starter->name] : null,
                    'started_by' => $r->starter ? ['name' => $r->starter->name] : null,
                    'mode' => $r->summary['mode'] ?? null,
                    'steps_total' => (int) ($r->summary['steps_total'] ?? 0),
                    'steps_done' => (int) ($r->summary['steps_done'] ?? 0),
                    'alert_codes' => $r->summary['alert_codes'] ?? [],
                ])->all(),
            'modes' => [SimulationService::MODE_REALTIME, SimulationService::MODE_INSTANT],
        ]);
    }

    public function run(Request $request, SimulationService $simulation): RedirectResponse
    {
        $this->authorize('run-simulation');
        $data = $request->validate([
            'slug' => ['required', 'string', Rule::exists('simulation_scenarios', 'slug')],
            'mode' => ['nullable', Rule::in([SimulationService::MODE_REALTIME, SimulationService::MODE_INSTANT])],
        ]);
        $scenario = SimulationScenario::query()->where('slug', $data['slug'])->firstOrFail();
        $run = $simulation->run($scenario, $request->user(), $data['mode'] ?? null);

        return back()->with('success', __('messages.simulation.started', [
            'alerts' => $run->alerts_created, 'matches' => $run->matches_created, 'notifications' => $run->notifications_created,
        ]));
    }

    /** Kết thúc lần chạy đang mở mới nhất của kịch bản (frontend gửi {slug}). */
    public function finishBySlug(Request $request, SimulationService $simulation): RedirectResponse
    {
        $this->authorize('run-simulation');
        $slug = $request->validate(['slug' => ['required', 'string', Rule::exists('simulation_scenarios', 'slug')]])['slug'];
        $run = SimulationRun::query()->whereNull('finished_at')
            ->whereHas('scenario', fn ($q) => $q->where('slug', $slug))->latest('started_at')->latest('id')->first();
        if ($run === null) {
            return back()->withErrors(['slug' => __('messages.simulation.already_finished')])->with('error', __('messages.simulation.already_finished'));
        }

        return $this->finish($request, $simulation, $run);
    }

    public function finish(Request $request, SimulationService $simulation, SimulationRun $run): RedirectResponse
    {
        $this->authorize('run-simulation');
        $simulation->finish($run, $request->user());

        return back()->with('success', __('messages.simulation.finished'));
    }
}
