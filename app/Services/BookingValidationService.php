<?php

namespace App\Services;

use App\Models\Service;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class BookingValidationService
{
    private SlotGeneratorService $slotGenerator;
    
    public function __construct(SlotGeneratorService $slotGenerator)
    {
        $this->slotGenerator = $slotGenerator;
    }
    
    /**
     * Validate booking request data
     * 
     * @param Service $service
     * @param array $data
     * @return array List of error messages, empty if valid
     */
    public function validateBookingRequest(Service $service, array $data): array
    {
        $errors = [];
        
        // Validate date format
        try {
            $bookingDate = Carbon::parse($data['booking_date']);
        } catch (\Exception $e) {
            $errors[] = 'Invalid date format';
            return $errors;
        }
        
        // Check max days in future
        $maxDate = now()->addDays($service->max_days_in_future);
        if ($bookingDate->gt($maxDate)) {
            $errors[] = "Cannot book more than {$service->max_days_in_future} days in advance";
        }
        
        // Check if date is in past
        if ($bookingDate->lt(now()->startOfDay())) {
            $errors[] = 'Cannot book in the past';
        }
        
        // Validate time format
        if (!isset($data['start_time']) || !$this->isValidTime($data['start_time'])) {
            $errors[] = 'Invalid start time format';
        }
        
        if (!isset($data['end_time']) || !$this->isValidTime($data['end_time'])) {
            $errors[] = 'Invalid end time format';
        }
        
        if (count($errors) > 0) {
            return $errors;
        }
        
        // Check if slot exists in available slots
        $availableSlots = $this->slotGenerator->generateAvailableSlots($service, $data['booking_date']);
        $slotExists = false;
        
        foreach ($availableSlots as $slot) {
            if ($slot['start_time'] === $data['start_time'] && 
                $slot['end_time'] === $data['end_time']) {
                $slotExists = true;
                break;
            }
        }
        
        if (!$slotExists) {
            $errors[] = 'Requested slot is not available';
            return $errors;
        }
        
        // Check if enough spots available
        $bookedCount = Booking::where('service_id', $service->id)
            ->whereDate('booking_date', $bookingDate->toDateString())
            ->where('start_time', $data['start_time'])
            ->where('end_time', $data['end_time'])
            ->withCount('clients')
            ->get()
            ->sum('clients_count');
        
        $requestedClients = count($data['clients'] ?? []);
        $availableSpots = $service->max_clients_per_slot - $bookedCount;
        
        if ($requestedClients > $availableSpots) {
            $errors[] = "Only {$availableSpots} spot(s) available for this slot";
        }
        
        // Validate client data
        foreach ($data['clients'] ?? [] as $index => $client) {
            if (empty($client['first_name']) || empty($client['last_name'])) {
                $errors[] = "Client {$index}: First and last name are required";
            }
            
            if (empty($client['email']) || !filter_var($client['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Client {$index}: Valid email is required";
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate time format HH:MM
     * 
     * @param string $time
     * @return bool
     */
    private function isValidTime(string $time): bool
    {
        return preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time);
    }

    public function validateOrFail(Service $service, array $data): void
    {
        $errors = $this->validateBookingRequest($service, $data);

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }
}