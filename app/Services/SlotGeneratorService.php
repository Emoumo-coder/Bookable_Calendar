<?php

namespace App\Services;

use App\Models\Service;
use App\Models\Booking;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class SlotGeneratorService
{
    /**
     * Generate available slots for a given service on a specific date.
     *
     * @param Service $service
     * @param string $date (Y-m-d format)
     * @return array
     */
    public function generateAvailableSlots(Service $service, string $date): array
    {
        $bookingDate = Carbon::parse($date);
        $dayOfWeek = $bookingDate->dayOfWeek;
        
        // Check if within max days in future
        $maxDate = now()->addDays($service->max_days_in_future);
        if ($bookingDate->gt($maxDate)) {
            return [];
        }
        
        // Check if service has schedule for this day
        $schedule = $service->getScheduleForDay($dayOfWeek);
        if (!$schedule) {
            return [];
        }
        
        // Check for planned off (full day)
        $isPlannedOff = $service->plannedOffs()
            ->whereDate('start_date', '<=', $bookingDate)
            ->whereDate('end_date', '>=', $bookingDate)
            ->whereNull('start_time')
            ->whereNull('end_time')
            ->exists();
            
        if ($isPlannedOff) {
            return [];
        }

        $bookings = Booking::where('service_id', $service->id)
            ->whereDate('booking_date', $bookingDate->toDateString())
            ->withCount('clients')
            ->get()
            ->groupBy(fn ($b) => $b->start_time . '-' . $b->end_time);
        
        // Generate slots
        $slots = [];
        $start = Carbon::parse($date . ' ' . $schedule->start_time);
        $end = Carbon::parse($date . ' ' . $schedule->end_time);
        
        $current = $start->copy();
        
        while ($current->addMinutes($service->slot_duration_minutes)->lte($end)) {
            $slotStart = $current->copy()->subMinutes($service->slot_duration_minutes);
            $slotEnd = $current->copy();
            
            // Skip if slot falls in a break
            $breakEnd = $this->isInBreak($service, $dayOfWeek, $slotStart, $slotEnd);
            if ($breakEnd) {
                // jump directly to end of break
                $current = $breakEnd->copy();
                continue;
            }
            
            // Skip if slot falls in planned off (partial day)
            if ($this->isInPlannedOff($service, $bookingDate, $slotStart, $slotEnd)) {
                continue;
            }
            
            // Get existing bookings for this slot
            $key = $slotStart->toTimeString() . '-' . $slotEnd->toTimeString();

            $bookedClients = $bookings->get($key)?->sum('clients_count') ?? 0;
            $availableSpots = $service->max_clients_per_slot - $bookedClients;
            
            if ($availableSpots > 0) {
                $slots[] = [
                    'start_time' => $slotStart->format('H:i'),
                    'end_time' => $slotEnd->format('H:i'),
                    'available_spots' => $availableSpots,
                ];
            }
            
            // Add cleanup break
            $current->addMinutes($service->cleanup_break_minutes);
        }
        
        return $slots;
    }
    
    /**
     * Check if a slot overlaps with any service breaks.
     *
     * @param Service $service
     * @param int $dayOfWeek
     * @param Carbon $start
     * @param Carbon $end
     * @return Carbon|null Returns the end time of the break if in break, null otherwise
     */
    private function isInBreak(Service $service, int $dayOfWeek, Carbon $start, Carbon $end): ?Carbon
    {
        $breaks = $service->serviceBreaks()
            ->where(function($query) use ($dayOfWeek) {
                $query->where('day_of_week', $dayOfWeek)
                      ->orWhereNull('day_of_week');
            })
            ->get();
        
        foreach ($breaks as $break) {
            $breakStart = Carbon::parse($start->toDateString() . ' ' . $break->start_time);
            $breakEnd = Carbon::parse($start->toDateString() . ' ' . $break->end_time);
            
            // Check if slot overlaps with break
            if ($start->lt($breakEnd) && $end->gt($breakStart)) {
                return $breakEnd; 
            }
        }
        
        return null;
    }
    
    /**
     * Check if a slot overlaps with any planned off periods.
     *
     * @param Service $service
     * @param Carbon $date
     * @param Carbon $start
     * @param Carbon $end
     * @return bool
     */
    private function isInPlannedOff(Service $service, Carbon $date, Carbon $start, Carbon $end): bool
    {
        $plannedOffs = $service->plannedOffs()
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->get();
        
        foreach ($plannedOffs as $plannedOff) {
            $offStart = Carbon::parse($date->toDateString() . ' ' . $plannedOff->start_time);
            $offEnd = Carbon::parse($date->toDateString() . ' ' . $plannedOff->end_time);
            
            if ($start->lt($offEnd) && $end->gt($offStart)) {
                return true;
            }
        }
        
        return false;
    }
}