<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Floor extends Model
{
    protected $fillable = [
        'building_id',
        'name',
        'number',
        'description',
        'map_image_path',
        'map_css_class',
    ];

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function accessPointSessions(): HasMany
    {
        return $this->hasMany(AccessPointSession::class);
    }
}