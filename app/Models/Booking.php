<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    protected $fillable = [
        'service_id', 'booking_date', 'start_time', 'end_time', 'booking_reference'
    ];
    
    protected $casts = [
        'booking_date' => 'date',
    ];
    
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
    
    public function clients(): HasMany
    {
        return $this->hasMany(BookingClient::class);
    }
    
    public function getClientCountAttribute(): int
    {
        return $this->clients()->count();
    }
}
