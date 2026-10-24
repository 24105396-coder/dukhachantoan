<?php

namespace App\Services\Reports;

use App\Models\Province;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Phạm vi báo cáo/xuất Excel (bất biến): khoảng ngày, tỉnh, người xem, bộ lọc phụ của màn index.
 * Agency luôn bị khóa vào tỉnh của mình (bỏ qua tỉnh trên URL); admin chọn tỉnh tùy ý (id hoặc mã).
 */
final class ReportScope
{
    /** Khoảng mặc định khi không truyền from/to. */
    public const DEFAULT_DAYS = 90;

    /** Bộ lọc phụ được nhận (trùng tên bộ lọc các màn admin index). */
    public const EXTRA_FILTERS = ['status', 'severity', 'hazard', 'channel', 'level', 'response_status', 'type', 'verified', 'action'];

    private function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly ?Province $province,
        public readonly User $user,
        public readonly array $extra,
    ) {
    }

    /** $input: dữ liệu đã validate {from?, to?, province?, ...EXTRA_FILTERS}. */
    public static function fromInput(User $user, array $input): self
    {
        $tz = config('app.timezone');
        $to = ! empty($input['to']) ? CarbonImmutable::parse($input['to'], $tz) : CarbonImmutable::now($tz);
        $from = ! empty($input['from']) ? CarbonImmutable::parse($input['from'], $tz) : $to->subDays(self::DEFAULT_DAYS);
        $extra = array_filter(
            array_intersect_key($input, array_flip(self::EXTRA_FILTERS)),
            fn ($v) => $v !== null && $v !== ''
        );

        return new self($from->startOfDay(), $to->endOfDay(), self::resolveProvince($user, $input['province'] ?? null), $user, $extra);
    }

    public function provinceId(): ?int
    {
        return $this->province?->id;
    }

    /** Giá trị bộ lọc phụ (null nếu không truyền). */
    public function extra(string $key): ?string
    {
        return isset($this->extra[$key]) ? (string) $this->extra[$key] : null;
    }

    /** Query string chuẩn để dựng lại URL (bảng báo cáo → nút xuất). */
    public function query(): array
    {
        return array_filter([
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'province' => $this->province?->id,
        ], fn ($v) => $v !== null) + $this->extra;
    }

    /** Khoảng [from, to] dạng chuỗi cho truy vấn tham số hóa. */
    public function range(): array
    {
        return [$this->from->toDateTimeString(), $this->to->toDateTimeString()];
    }

    private static function resolveProvince(User $user, mixed $requested): ?Province
    {
        if ($user->isAgency()) {
            return $user->province_id ? Province::query()->find($user->province_id) : null;
        }
        if ($requested === null || $requested === '') {
            return null;
        }

        return ctype_digit((string) $requested)
            ? Province::query()->find((int) $requested)
            : Province::query()->where('code', (string) $requested)->first();
    }
}
