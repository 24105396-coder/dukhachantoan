<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const ADMIN = 'admin';
    public const MANAGER = 'manager';
    public const CONTRIBUTOR = 'contributor';
    public const MEMBER = 'member';

    protected $fillable = ['code', 'name', 'description'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
