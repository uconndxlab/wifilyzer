<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessPointPosition extends Model
{
    protected $fillable = [
        'floor_id',
        'ap_name',
        'x_percentage',
        'y_percentage'
    ];

    public function floor()
    {
        return $this->belongsTo(Floor::class);
    }
}
