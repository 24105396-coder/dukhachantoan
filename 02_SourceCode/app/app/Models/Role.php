<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const ADMIN = 'admin';
    public const AGENCY = 'agency';
    public const PARTNER = 'partner';
    public const TRAVELER = 'traveler';

    public const ALL = [self::ADMIN, self::AGENCY, self::PARTNER, self::TRAVELER];

    protected $fillable = ['code', 'name', 'description'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
