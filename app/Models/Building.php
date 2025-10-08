<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Building extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
    ];

    public function floors(): HasMany
    {
        return $this->hasMany(Floor::class);
    }

    public function accessPointSessions(): HasMany
    {
        return $this->hasMany(AccessPointSession::class);
    }
}