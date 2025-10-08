<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessPointSession extends Model
{
    protected $fillable = [
        'building_id',
        'floor_id',
        'client_username_hash',
        'client_affiliation',
        'client_ip_address',
        'client_mac_address',
        'association_time',
        'ap_name',
        'ssid',
        'session_duration',
        'avg_session_throughput',
        'endpoint_type',
        'disassociation_time',
        'bytes_sent',
        'bytes_received',
        'rssi',
    ];

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }
}
