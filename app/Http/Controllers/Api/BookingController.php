<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingRequest;
use App\Http\Requests\AvailableSlotsRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\SlotResource;
use App\Models\Service;
use App\Services\SlotGeneratorService;
use App\Services\BookingValidationService;
use App\Models\Booking;
use App\Models\BookingClient;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    private SlotGeneratorService $slotGenerator;
    private BookingValidationService $validator;
    
    public function __construct(
        SlotGeneratorService $slotGenerator,
        BookingValidationService $validator
    ) {
        $this->slotGenerator = $slotGenerator;
        $this->validator = $validator;
    }
    
    /**
     * GET /api/available-slots
     * Returns available slots for a specific service and date
     */
    public function availableSlots(AvailableSlotsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        $service = Service::where('slug', $validated['service_slug'])->firstOrFail();
        
        $slots = $this->slotGenerator->generateAvailableSlots($service, $validated['date']);
        
        return response()->json([
            'data' => [
                'service' => new ServiceResource($service),
                'date' => $validated['date'],
                'slots' => SlotResource::collection($slots),
            ],
            'message' => 'Available slots retrieved successfully',
        ]);
    }
    
    /**
     * POST /api/bookings
     * Creates a new booking
     */
    public function store(BookingRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        $service = Service::where('slug', $validated['service_slug'])->firstOrFail();
        
        // Additional validation
        $this->validator->validateOrFail($service, $validated);
        
        // Create booking within transaction
        $booking = DB::transaction(function () use ($service, $validated) {
            $booking = Booking::create([
                'service_id' => $service->id,
                'booking_date' => $validated['booking_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'booking_reference' => app(
                    \App\Services\BookingSequenceService::class
                )->generate(),
            ]);

            $booking->clients()->createMany($validated['clients']);
            
            return $booking->load(['service', 'clients']);
        });
        
        return (new BookingResource($booking))
            ->additional([
                'message' => 'Booking created successfully',
            ])
            ->response()
            ->setStatusCode(201);
    }
}
