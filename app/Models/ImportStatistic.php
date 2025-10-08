<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportStatistic extends Model
{
    protected $fillable = [
        'building_id',
        'floor_id',
        'total_records_processed',
        'imported_records',
        'short_duration_filtered',
        'weak_signal_filtered',
        'import_date',
        'file_name',
    ];

    protected $casts = [
        'import_date' => 'datetime',
    ];

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function floor()
    {
        return $this->belongsTo(Floor::class);
    }

    public function getImportRateAttribute()
    {
        if ($this->total_records_processed == 0) {
            return 0;
        }
        return round(($this->imported_records / $this->total_records_processed) * 100, 1);
    }
}