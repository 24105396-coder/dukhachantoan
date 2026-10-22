<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\AlertSource;
use App\Models\HazardType;
use App\Models\Zone;
use Database\Seeders\Concerns\ReadsSeedJson;
use Illuminate\Database\Seeder;

/**
 * Cảnh báo mẫu từ database/data/alerts_seed.json (idempotent theo code). Mốc thời gian tương đối
 * ("+2d 08:00", "-1d") đổi theo thời điểm seed để cảnh báo mẫu luôn đang hiệu lực khi trình diễn.
 */
class AlertSeeder extends Seeder
{
    use ReadsSeedJson;

    public function run(): void
    {
        $sources = AlertSource::pluck('id', 'code');
        $hazards = HazardType::pluck('id', 'code');
        $zones = Zone::pluck('id', 'code');

        foreach ($this->readJson('alerts_seed.json') as $row) {
            $sourceId = $sources[$row['source_code'] ?? 'simulation'] ?? null;
            $hazardId = $hazards[$row['hazard_code'] ?? ''] ?? null;
            if (empty($row['code']) || ! $sourceId || ! $hazardId) {
                $this->warnSeed('bỏ qua cảnh báo thiếu code/source_code/hazard_code hợp lệ: '.($row['code'] ?? '?'));

                continue;
            }
            $alert = Alert::updateOrCreate(['code' => $row['code']], $this->attributes($row, $sourceId, $hazardId));
            $alert->zones()->sync(collect($row['zone_codes'] ?? [])->map(fn ($c) => $zones[$c] ?? null)->filter()->values()->all());
            $alert->syncTranslations(array_filter(
                $this->translationsFrom($row, ['headline' => 'headline', 'description' => 'description', 'instructions' => 'instructions']),
                fn ($t) => ! empty($t['headline']),
            ));
        }
    }

    private function attributes(array $row, int $sourceId, int $hazardId): array
    {
        $status = $row['status'] ?? Alert::STATUS_ACTIVE;
        $issuedAt = $this->when($row['issued_at'] ?? null) ?? now();

        return [
            'source_id' => $sourceId,
            'hazard_type_id' => $hazardId,
            'severity' => max(1, min(4, (int) ($row['severity'] ?? 2))),
            'status' => $status,
            'geometry' => $row['geometry'] ?? null,
            'starts_at' => $this->when($row['starts_at'] ?? null),
            'ends_at' => $this->when($row['ends_at'] ?? null),
            'issued_at' => $issuedAt,
            'updated_at_source' => $this->when($row['updated_at_source'] ?? null) ?? $issuedAt,
            'source_name' => $row['source_name'] ?? 'Không rõ nguồn',
            'source_url' => $row['source_url'] ?? null,
            'raw_payload' => $row['raw_payload'] ?? null,
            'is_simulated' => (bool) ($row['is_simulated'] ?? true),
            'published_at' => $status === Alert::STATUS_DRAFT ? null : $issuedAt,
            'expired_at' => $status === Alert::STATUS_EXPIRED ? $this->when($row['ends_at'] ?? null) : null,
        ];
    }
}
