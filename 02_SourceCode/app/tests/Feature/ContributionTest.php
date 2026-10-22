<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\HeritageSite;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_submits_contribution_with_suggested_tags_and_attachment(): void
    {
        Storage::fake('local');
        $tag = Tag::create(['slug' => 'kinh-do-co', 'tag_group' => 'chu_de']);
        $tag->translations()->create(['locale' => 'vi', 'name' => 'Kinh đô cổ']);
        $site = HeritageSite::factory()->create();
        Http::fake(['*/suggest-tags' => Http::response(['suggestions' => [['tag_id' => $tag->id, 'slug' => 'kinh-do-co', 'score' => 0.91234]]])]);

        $this->actingAs($this->member())->post('/vi/dong-gop', [
            'contribution_type' => 'story', 'locale' => 'vi', 'title' => 'Ký ức về kinh thành',
            'body' => 'Ông bà tôi kể về kinh thành Thăng Long xưa với nhiều câu chuyện thú vị.',
            'subject_type' => 'site', 'subject_id' => $site->id,
            'attachment' => UploadedFile::fake()->image('anh.png'),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $c = Contribution::firstOrFail();
        $this->assertEquals([['tag_id' => $tag->id, 'slug' => 'kinh-do-co', 'name' => 'Kinh đô cổ', 'score' => 0.912]], $c->suggested_tags);
        $this->assertSame('site', $c->subject_type);
        // Tệp đính kèm nằm ở disk private, tên ngẫu nhiên + đuôi theo MIME thật
        Storage::disk('local')->assertExists($c->attachment_path);
        $this->assertMatchesRegularExpression('#^contributions/[a-z0-9]{40}\.png$#', $c->attachment_path);
    }

    public function test_contribution_saved_without_tags_when_analytics_down(): void
    {
        Http::fake(['*' => Http::response(null, 500)]);
        $user = $this->member();

        $this->actingAs($user)->post('/vi/dong-gop', [
            'contribution_type' => 'new_site', 'locale' => 'vi', 'title' => 'Đề xuất đình làng',
            'body' => 'Đình làng có kiến trúc gỗ cổ cần được số hóa và bảo tồn.',
        ])->assertRedirect();

        $c = Contribution::firstOrFail();
        $this->assertNull($c->suggested_tags);
        $this->actingAs($user)->get("/vi/dong-gop/{$c->id}")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Contributions/Show')->where('contribution.status', 'pending')->where('contribution.subject', null));
    }

    public function test_create_page_prefills_subject(): void
    {
        $site = HeritageSite::factory()->create();

        $this->actingAs($this->member())->get("/vi/dong-gop/moi?type=site&id={$site->id}")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Contributions/Create')->where('subject.id', $site->id)->has('types', 5));
    }
}
