<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContributionReviewRequest;
use App\Http\Resources\ContributionItemResource;
use App\Models\Contribution;
use App\Services\AuditLogger;
use App\Services\LookupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContributionController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request, ['status', 'type']);
        $items = Contribution::query()->with(ContributionItemResource::eager())
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['type'], fn ($q, $v) => $q->where('contribution_type', $v))
            ->orderByRaw("FIELD(status, 'pending', 'needs_revision', 'approved', 'rejected')")->latest('id')
            ->paginate(20)->withQueryString();

        return Inertia::render('Admin/Contributions/Index', [
            'contributions' => ContributionItemResource::paginate($items),
            'filters' => $filters,
            'enums' => [
                'statuses' => HeritagePresenter::enumList('contribution_statuses', Contribution::STATUSES),
                'types' => HeritagePresenter::enumList('contribution_types', Contribution::TYPES),
            ],
        ]);
    }

    public function show(Contribution $contribution, LookupService $lookup): Response
    {
        $contribution->load(ContributionItemResource::eager());

        return Inertia::render('Admin/Contributions/Show', [
            'contribution' => ContributionItemResource::one($contribution) + ['user_email' => $contribution->user?->email],
            'tags' => $lookup->tags(),
        ]);
    }

    public function review(ContributionReviewRequest $request, Contribution $contribution, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('review', $contribution);
        $contribution->update([
            'status' => $request->validated('status'),
            'review_note' => $request->validated('review_note'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);
        $audit->log('contribution.'.$contribution->status, $contribution, ['note' => $contribution->review_note]);

        return redirect(localized_route('admin.contributions.index'))->with('success', __('messages.contributions.reviewed'));
    }
}
