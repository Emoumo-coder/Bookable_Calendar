<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleTemplate extends Model
{
    protected $fillable = ['service_id', 'day_of_week', 'start_time', 'end_time'];
    
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
