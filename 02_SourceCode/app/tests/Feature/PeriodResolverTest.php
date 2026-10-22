<?php

namespace Tests\Feature;

use App\Models\HeritageSite;
use App\Models\Period;
use App\Models\TimelineEvent;
use App\Services\PeriodResolver;
use Database\Seeders\Concerns\ReadsSeedJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodResolverTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string,int> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            ['bac_thuoc', -179, 938, 2], ['champa', 192, 1832, 3], ['ly', 1009, 1225, 6], ['hien_dai', 1945, null, 14],
        ] as [$code, $start, $end, $order]) {
            $this->ids[$code] = Period::query()->firstOrCreate(['code' => $code], ['start_year' => $start, 'end_year' => $end, 'sort_order' => $order])->id;
        }
    }

    public function test_period_is_derived_from_event_year(): void
    {
        $resolver = new PeriodResolver();

        // Sự kiện công nhận 2010 của di tích thời Lý → hiện đại, không phải Lý
        $this->assertSame($this->ids['hien_dai'], $resolver->forYear(2010, $this->ids['ly']));
        $this->assertSame($this->ids['ly'], $resolver->forYear(1010, $this->ids['ly']));
        // Năm thuộc nhiều thời kỳ: ưu tiên thời kỳ của chủ thể, nếu không thì sort_order nhỏ nhất
        $this->assertSame($this->ids['champa'], $resolver->forYear(500, $this->ids['champa']));
        $this->assertSame($this->ids['bac_thuoc'], $resolver->forYear(500, null));
        // Ngoài mọi thời kỳ → null (Q08 bỏ qua)
        $this->assertNull($resolver->forYear(-2000, $this->ids['ly']));
        $this->assertNull($resolver->forYear(null));
    }

    public function test_seeder_events_do_not_inherit_site_period(): void
    {
        $site = HeritageSite::factory()->create(['period_id' => $this->ids['ly']]);
        $seeder = new class
        {
            use ReadsSeedJson;

            public function run(HeritageSite $site, array $events, ?int $periodId): void
            {
                $this->syncEvents('site_id', $site, $events, $periodId);
            }
        };

        $seeder->run($site, [
            ['year' => 1010, 'type' => 'xay_dung', 'vi' => ['title' => 'Dời đô']],
            ['year' => 2010, 'type' => 'cong_nhan', 'vi' => ['title' => 'Công nhận']],
        ], $this->ids['ly']);

        $this->assertSame($this->ids['ly'], TimelineEvent::where('site_id', $site->id)->where('event_year', 1010)->value('period_id'));
        $this->assertSame($this->ids['hien_dai'], TimelineEvent::where('site_id', $site->id)->where('event_year', 2010)->value('period_id'));
    }
}
