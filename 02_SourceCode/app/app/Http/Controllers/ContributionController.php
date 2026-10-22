<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContributionStoreRequest;
use App\Http\Resources\ContributionItemResource;
use App\Models\Contribution;
use App\Models\Tag;
use App\Services\AnalyticsClient;
use App\Services\AuditLogger;
use App\Services\SearchService;
use App\Support\UploadPolicy;
use App\Support\UrlToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContributionController extends Controller
{
    public const ATTACHMENT_DISK = 'local';

    public function create(Request $request): Response
    {
        return Inertia::render('Contributions/Create', [
            'types' => $this->enumOptions('contribution_types', Contribution::TYPES),
            'subject' => $this->subjectFromQuery($request),
        ]);
    }

    public function store(ContributionStoreRequest $request, AnalyticsClient $analytics, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();
        $subjectId = null;
        if (! empty($data['subject_type'])) {
            $class = SearchService::TYPES[$data['subject_type']];
            $subjectId = $class::query()->published()->findOrFail($data['subject_id'])->id;
        }

        $path = $request->hasFile('attachment') ? $this->storeAttachment($request->file('attachment')) : null;

        $contribution = Contribution::create([
            'user_id' => $request->user()->id,
            'subject_type' => $subjectId ? $data['subject_type'] : null,
            'subject_id' => $subjectId,
            'contribution_type' => $data['contribution_type'],
            'locale' => $data['locale'],
            'title' => $data['title'],
            'body' => $data['body'],
            'attachment_path' => $path ?: null,
            'suggested_tags' => $this->suggestTags($analytics, $data['title']."\n".$data['body'], $data['locale']),
            'status' => 'pending',
        ]);
        $audit->log('contribution.submitted', $contribution, ['type' => $contribution->contribution_type]);

        return redirect(localized_route('contributions.show', ['contribution' => $contribution->id]))
            ->with('success', __('messages.contributions.submitted'));
    }

    public function show(Request $request, Contribution $contribution): Response
    {
        $this->authorize('view', $contribution);
        $contribution->load(ContributionItemResource::eager());

        return Inertia::render('Contributions/Show', [
            'contribution' => ContributionItemResource::one($contribution),
        ]);
    }

    /** Tải tệp đính kèm: chỉ chủ đóng góp hoặc staff (Policy view); phục vụ inline, không cho trình duyệt đoán kiểu. */
    public function attachment(Request $request, string $token): StreamedResponse
    {
        // Chữ ký đã được middleware signed kiểm; token giải mã lỗi/sai mục đích → 404 (không tiết lộ id có tồn tại hay không)
        $id = UrlToken::decode(ContributionItemResource::URL_TOKEN_PURPOSE, $token);
        abort_if($id === null, 404);
        $contribution = Contribution::query()->findOrFail($id);
        $this->authorize('view', $contribution);
        $path = (string) $contribution->attachment_path;
        // Bản cũ từng lưu trên disk public; bản mới nằm ở disk private (storage/app/private)
        $disk = collect([self::ATTACHMENT_DISK, 'public'])->first(fn ($d) => $path !== '' && Storage::disk($d)->exists($path));
        abort_unless($disk, 404);

        return Storage::disk($disk)->response($path, basename($path), [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    private function storeAttachment(UploadedFile $file): string
    {
        $extension = UploadPolicy::extensionFor('attachment', $file);
        abort_unless($extension !== null, 422);

        return $file->storeAs('contributions', UploadPolicy::randomName($extension), self::ATTACHMENT_DISK);
    }

    /** Gợi ý thẻ từ dịch vụ Python; dịch vụ tắt thì để trống (kiểm duyệt viên gắn tay). */
    private function suggestTags(AnalyticsClient $analytics, string $text, string $locale): ?array
    {
        $suggestions = $analytics->suggestTags($text, $locale, 5);
        if (! $suggestions) {
            return null;
        }
        $tags = Tag::query()->with('translations')
            ->whereIn('slug', collect($suggestions)->pluck('slug')->filter())->get()->keyBy('slug');

        return collect($suggestions)->filter(fn ($s) => isset($s['slug']))->map(fn ($s) => [
            'tag_id' => $tags[$s['slug']]->id ?? ($s['tag_id'] ?? null),
            'slug' => $s['slug'],
            'name' => isset($tags[$s['slug']]) ? $tags[$s['slug']]->translate($locale)?->name : $s['slug'],
            'score' => round((float) ($s['score'] ?? 0), 3),
        ])->values()->all();
    }

    private function subjectFromQuery(Request $request): ?array
    {
        $type = $request->query('type');
        $id = $request->query('id');
        if (! is_string($type) || ! isset(SearchService::TYPES[$type]) || ! ctype_digit((string) $id)) {
            return null;
        }
        $subject = SearchService::TYPES[$type]::query()->published()->with('interpretations')->find((int) $id);

        return $subject ? ['type' => $type, 'id' => $subject->id, 'title' => $subject->title] : null;
    }
}
