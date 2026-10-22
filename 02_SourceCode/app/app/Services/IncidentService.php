<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Alert;
use App\Models\HazardType;
use App\Models\IncidentReport;
use App\Models\MediaAsset;
use App\Models\Trip;
use App\Models\User;
use App\Models\Zone;
use App\Support\UploadPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Du khách báo sự cố thực địa; cơ quan duyệt (verified/rejected) và có thể tạo cảnh báo nháp từ sự cố đã xác minh.
 * Ảnh sự cố: MIME thật kiểm bằng UploadPolicy, tên tệp ngẫu nhiên, lưu disk public/incidents.
 */
class IncidentService
{
    /** Bán kính mặc định (km) của cảnh báo tạo từ sự cố có tọa độ. */
    public const ALERT_RADIUS_KM = 10;

    public function __construct(private readonly AlertService $alerts, private readonly AuditLogger $audit)
    {
    }

    public function report(?User $user, array $data, ?UploadedFile $photo = null): IncidentReport
    {
        return DB::transaction(function () use ($user, $data, $photo) {
            $incident = IncidentReport::create([
                'user_id' => $user?->id,
                'trip_id' => ! empty($data['trip_code']) && $user
                    ? Trip::query()->visibleTo($user)->where('code', $data['trip_code'])->value('id') : null,
                'zone_id' => Zone::query()->where('code', $data['zone_code'])->valueOrFail('id'),
                'hazard_type_id' => HazardType::query()->where('code', $data['hazard_code'])->valueOrFail('id'),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'lat' => $data['lat'] ?? null,
                'lng' => $data['lng'] ?? null,
                'status' => 'pending',
            ]);
            if ($photo) {
                $incident->forceFill(['photo_media_id' => $this->storePhoto($incident, $photo, $user)->id])->save();
            }
            $this->audit->log('incident.report', $incident, ['zone' => $data['zone_code'], 'hazard' => $data['hazard_code']], $user);

            return $incident;
        });
    }

    public function review(IncidentReport $incident, User $actor, string $status, ?string $note): IncidentReport
    {
        $incident->forceFill([
            'status' => $status,
            'review_note' => $note,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ])->save();
        $this->audit->log('incident.review', $incident, ['status' => $status], $actor);

        return $incident;
    }

    /** Cảnh báo nháp từ sự cố đã xác minh: cùng loại rủi ro, vùng; có tọa độ thì thêm hình tròn 10 km. */
    public function createAlert(IncidentReport $incident, User $actor): Alert
    {
        if ($incident->status !== 'verified') {
            throw new BusinessRuleException(__('messages.incidents.verify_first'));
        }
        $incident->loadMissing('zone', 'hazardType');
        $alert = $this->alerts->create([
            'source_code' => 'manual',
            'hazard_code' => $incident->hazardType->code,
            'severity' => max(2, (int) $incident->hazardType->default_severity),
            'zone_codes' => [$incident->zone->code],
            'geometry' => $incident->lat !== null ? [
                'type' => 'Circle', 'center' => ['lat' => $incident->lat, 'lng' => $incident->lng], 'radius_km' => self::ALERT_RADIUS_KM,
            ] : null,
            'starts_at' => now(),
            'ends_at' => now()->addDay(),
            'source_name' => __('messages.incidents.alert_source', ['org' => $actor->organization ?: $actor->name]),
            'is_simulated' => (bool) $incident->is_simulated,
            'raw_payload' => ['incident_id' => $incident->id],
            'translations' => ['vi' => ['headline' => $incident->title, 'description' => $incident->description]],
        ], $actor);
        $this->audit->log('incident.create_alert', $incident, ['alert' => $alert->code], $actor);

        return $alert;
    }

    private function storePhoto(IncidentReport $incident, UploadedFile $photo, ?User $user): MediaAsset
    {
        $extension = UploadPolicy::extensionFor('image', $photo) ?? 'jpg';
        $path = $photo->storeAs('incidents', UploadPolicy::randomName($extension), 'public');
        [$width, $height] = @getimagesize(Storage::disk('public')->path($path)) ?: [null, null];

        return MediaAsset::create([
            'subject_type' => $incident->getMorphClass(),
            'subject_id' => $incident->id,
            'media_type' => 'image',
            'path' => $path,
            'mime' => $photo->getMimeType(),
            'size_bytes' => $photo->getSize(),
            'width' => $width,
            'height' => $height,
            'license' => 'user_upload',
            'uploaded_by' => $user?->id,
        ]);
    }
}
