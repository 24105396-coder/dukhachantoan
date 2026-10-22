<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Loại rủi ro (bão, lũ quét, nắng nóng…); tên/mô tả đa ngữ. */
class HazardType extends Model
{
    use Translatable;

    protected string $translationModel = HazardTypeTranslation::class;
    protected string $translationForeignKey = 'hazard_type_id';

    protected $fillable = ['code', 'icon', 'color', 'default_severity', 'sort_order'];

    protected function casts(): array
    {
        return ['default_severity' => 'integer', 'sort_order' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function guides(): HasMany
    {
        return $this->hasMany(ResponseGuide::class);
    }

    /** {code, name, icon, color} dùng trong AlertCard/GuideCard. */
    public function brief(): array
    {
        return ['code' => $this->code, 'name' => $this->name, 'icon' => $this->icon, 'color' => $this->color];
    }
}
