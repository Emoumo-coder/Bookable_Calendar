<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'slot_duration_minutes',
        'cleanup_break_minutes', 'max_clients_per_slot', 'max_days_in_future', 'is_active'
    ];
    
    public function scheduleTemplates(): HasMany
    {
        return $this->hasMany(ScheduleTemplate::class);
    }
    
    public function serviceBreaks(): HasMany
    {
        return $this->hasMany(ServiceBreak::class);
    }
    
    public function plannedOffs(): HasMany
    {
        return $this->hasMany(PlannedOff::class);
    }
    
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    public function getScheduleForDay(int $dayOfWeek): ?ScheduleTemplate
    {
        return $this->scheduleTemplates()->where('day_of_week', $dayOfWeek)->first();
    }
}
