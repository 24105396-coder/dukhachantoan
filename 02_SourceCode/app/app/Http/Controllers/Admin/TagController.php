<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TagRequest;
use App\Http\Resources\TagItemResource;
use App\Models\Tag;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): Response
    {
        $filters = $this->filters($request, ['q', 'group']);
        $tags = Tag::query()->with(['translations', 'parent.translations'])
            ->when($filters['group'], fn ($q, $v) => $q->where('tag_group', $v))
            ->when($filters['q'], fn ($q, $v) => $q->where('slug', 'like', like_contains($v)))
            ->orderBy('tag_group')->orderBy('slug')->get();

        return Inertia::render('Admin/Tags/Index', [
            'tags' => $tags->map(fn (Tag $t) => $this->row($t))->all(),
            'filters' => $filters,
            'enums' => ['groups' => HeritagePresenter::enumList('tag_groups', Tag::GROUPS)],
        ]);
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function edit(Tag $tag): Response
    {
        return $this->form($tag->load(['translations', 'parent']));
    }

    public function store(TagRequest $request): RedirectResponse
    {
        $tag = $this->save(new Tag(), $request->validated());
        $this->audit->log('admin.tag.create', $tag, ['slug' => $tag->slug]);

        return redirect(localized_route('admin.tags.index'))->with('success', __('messages.admin.created'));
    }

    public function update(TagRequest $request, Tag $tag): RedirectResponse
    {
        $this->save($tag, $request->validated());
        $this->audit->log('admin.tag.update', $tag, ['slug' => $tag->slug]);

        return redirect(localized_route('admin.tags.index'))->with('success', __('messages.admin.updated'));
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $this->audit->log('admin.tag.delete', $tag, ['slug' => $tag->slug]);
        $tag->delete();

        return redirect(localized_route('admin.tags.index'))->with('success', __('messages.admin.deleted'));
    }

    private function save(Tag $tag, array $data): Tag
    {
        return DB::transaction(function () use ($tag, $data) {
            $tag->fill(['slug' => $data['slug'], 'tag_group' => $data['tag_group'], 'parent_id' => $data['parent_id'] ?? null])->save();
            foreach (['vi', 'en'] as $locale) {
                $tag->translations()->updateOrCreate(['locale' => $locale], [
                    'name' => $data['name_'.$locale],
                    'description' => $data['description_'.$locale] ?? null,
                ]);
            }

            return $tag;
        });
    }

    private function form(?Tag $tag): Response
    {
        $others = Tag::query()->with(['translations', 'parent.translations'])->whereKeyNot($tag?->id ?? 0)->orderBy('slug')->get();

        return Inertia::render('Admin/Tags/Form', [
            'tag' => $tag ? $this->row($tag) : null,
            'parents' => $others->map(fn ($t) => ['id' => $t->id, 'slug' => $t->slug, 'name' => $t->name])->all(),
            // Frontend lọc thẻ cha cùng nhóm nên cần group/tag_group và name_vi của từng thẻ
            'tags' => $others->map(fn (Tag $t) => $this->row($t))->all(),
            'enums' => ['groups' => HeritagePresenter::enumList('tag_groups', Tag::GROUPS)],
        ]);
    }

    private function row(Tag $t): array
    {
        $vi = $t->translations->firstWhere('locale', 'vi');
        $en = $t->translations->firstWhere('locale', 'en');

        return TagItemResource::one($t) + [
            'tag_group' => $t->tag_group,
            'parent_id' => $t->parent_id,
            'name_vi' => $vi?->name,
            'name_en' => $en?->name,
            'description_vi' => $vi?->description,
            'description_en' => $en?->description,
            'parent' => $t->parent ? ['id' => $t->parent->id, 'slug' => $t->parent->slug, 'name' => $t->parent->name] : null,
        ];
    }
}
