<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlannedOff extends Model
{
    protected $fillable = [
        'service_id', 'name', 'start_date', 'end_date', 'start_time', 'end_time'
    ];
    
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
    
    public function isFullDay(): bool
    {
        return is_null($this->start_time) && is_null($this->end_time);
    }
}
