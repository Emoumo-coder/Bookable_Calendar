<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceBreak extends Model
{
    protected $fillable = ['service_id', 'name', 'day_of_week', 'start_time', 'end_time'];
    
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
