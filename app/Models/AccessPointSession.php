<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessPointSession extends Model
{
    protected $fillable = [
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
}
