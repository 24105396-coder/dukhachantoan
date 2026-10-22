<?php

namespace Database\Seeders;

use App\Models\EmergencyContact;
use App\Models\Province;
use App\Models\Zone;
use Database\Seeders\Concerns\ReadsSeedJson;
use Illuminate\Database\Seeder;

/**
 * Danh bạ khẩn cấp từ database/data/emergency_contacts.json.
 * Idempotent theo (tỉnh, loại, số điện thoại); province_code null = toàn quốc.
 */
class ContactSeeder extends Seeder
{
    use ReadsSeedJson;

    public function run(): void
    {
        $provinces = Province::pluck('id', 'code');
        $zones = Zone::pluck('id', 'code');

        foreach ($this->readJson('emergency_contacts.json') as $i => $row) {
            if (empty($row['phone']) || empty($row['contact_type'])) {
                continue;
            }
            $contact = EmergencyContact::updateOrCreate([
                'province_id' => $provinces[$row['province_code'] ?? ''] ?? null,
                'contact_type' => $row['contact_type'],
                'phone' => $row['phone'],
            ], [
                'zone_id' => $zones[$row['zone_code'] ?? ''] ?? null,
                'phone_alt' => $row['phone_alt'] ?? null,
                'address' => $row['address'] ?? null,
                'lat' => $row['lat'] ?? null,
                'lng' => $row['lng'] ?? null,
                'hours' => $row['hours'] ?? null,
                'languages' => $row['languages'] ?? null,
                'is_verified' => (bool) ($row['is_verified'] ?? false),
                'verified_at' => $row['verified_at'] ?? null,
                'source_name' => $row['source_name'] ?? null,
                'source_url' => $row['source_url'] ?? null,
                'sort_order' => (int) ($row['sort_order'] ?? $i),
            ]);
            $contact->syncTranslations(array_filter(
                $this->translationsFrom($row, ['name' => 'name', 'note' => 'note']),
                fn ($t) => ! empty($t['name']),
            ));
        }
    }
}
