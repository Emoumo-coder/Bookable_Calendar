<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingClient;
use App\Models\PlannedOff;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected Service $service;
    protected string $validDate;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->service = Service::where('slug', 'men-haircut')->firstOrFail();
        $this->validDate = $this->getValidTestDate();
    }

    /**
     * Get a valid test date that's NOT Sunday and NOT a public holiday
     */
    private function getValidTestDate(): string
    {
        $date = Carbon::now();
        
        // Skip to next valid day
        for ($i = 0; $i < 10; $i++) {
            $testDate = $date->copy()->addDays($i);
            
            // Check if it's Sunday (0)
            if ($testDate->dayOfWeek === 0) {
                continue;
            }
            
            // Check if it's a public holiday (3rd day from now)
            $publicHoliday = Carbon::now()->addDays(3);
            if ($testDate->isSameDay($publicHoliday)) {
                continue;
            }
            
            // Check if service has schedule for this day
            $schedule = $this->service->getScheduleForDay($testDate->dayOfWeek);
            if (!$schedule) {
                continue;
            }
            
            return $testDate->format('Y-m-d');
        }
        
        // Fallback: use a known working date
        return '2025-12-15'; // A Monday
    }
    
    #[Test]
    public function it_returns_available_slots_for_a_service_and_date(): void
    {
        $response = $this->getJson("/api/available-slots?service_slug=men-haircut&date={$this->validDate}");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'service' => ['id', 'name', 'slug', 'description', 'slot_duration', 'cleanup_break',
                    'max_clients_per_slot', 'max_days_in_future', 'is_active'],
                    'date',
                    'slots' => ['*' => ['start_time', 'end_time', 'available_spots', 'is_available']]
                ],
                'message'
            ]);
    }
    
    #[Test]
    public function it_creates_a_booking_successfully(): void
    {
        // First get available slots to ensure we book a valid one
        $availableResponse = $this->getJson("/api/available-slots?service_slug=men-haircut&date={$this->validDate}");
        $slots = $availableResponse->json('data.slots');
        
        $this->assertNotEmpty($slots, 'No available slots for date: ' . $this->validDate);
        
        $slot = $slots[0];
        $data = [
            'service_slug' => $this->service->slug,
            'booking_date' => $this->validDate,
            'start_time' => $slot['start_time'],
            'end_time' => $slot['end_time'],
            'clients' => [
                [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john@example.com',
                ]
            ]
        ];
        
        $response = $this->postJson('/api/bookings', $data);
        
        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'booking_reference',
                    'booking_date',
                    'start_time',
                    'end_time',
                    'service' => [
                        'id', 'name', 'slug', 'description', 'slot_duration', 'cleanup_break',
                        'max_clients_per_slot', 'max_days_in_future', 'is_active'
                    ],
                    'clients' => [
                        '*' => ['id', 'first_name', 'last_name', 'full_name', 'email', 'created_at']
                    ],
                    'client_count',
                    'created_at',
                    'updated_at',
                ],
                'message'
            ]);
        
        // Verify booking was created
        $this->assertDatabaseHas('bookings', [
            'booking_date' => Carbon::parse($data['booking_date'])->format('Y-m-d 00:00:00'),
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
        ]);
        
        // Verify client was created
        $this->assertDatabaseHas('booking_clients', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);
    }
    
    #[Test]
    public function it_validates_booking_for_past_date(): void
    {
        $pastDate = Carbon::now()->subDays(2)->format('Y-m-d');
        $data = [
            'service_slug' => $this->service->slug,
            'booking_date' => $pastDate,
            'start_time' => '08:00',
            'end_time' => '08:10',
            'clients' => [
                [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john@example.com',
                ]
            ]
        ];
        
        $response = $this->postJson('/api/bookings', $data);
        
        $response->assertStatus(422)
            ->assertJsonStructure(['errors', 'message']);
    }
    
    #[Test]
    public function it_validates_booking_for_full_slot(): void
    {
        $availableResponse = $this->getJson("/api/available-slots?service_slug=men-haircut&date={$this->validDate}");
        $slots = $availableResponse->json('data.slots');
        $slot = $slots[0];
        // Book all 3 spots for a specific slot
        $booking = Booking::create([
            'service_id' => $this->service->id,
            'booking_date' => $this->validDate,
            'start_time' => $slot['start_time'],
            'end_time' => $slot['end_time'],
            'booking_reference' => 'TEST-123',
        ]);
        
        // Create 3 clients for this booking
        for ($i = 0; $i < 3; $i++) {
            BookingClient::create([
                'booking_id' => $booking->id,
                'first_name' => "Client{$i}",
                'last_name' => "Test",
                'email' => "client{$i}@test.com",
            ]);
        }
        
        $data = [
            'service_slug' => $this->service->slug,
            'booking_date' => $this->validDate,
            'start_time' => $slot['start_time'],
            'end_time' => $slot['end_time'],
            'clients' => [
                [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john@example.com',
                ]
            ]
        ];
        
        $response = $this->postJson('/api/bookings', $data);
        
        $response->assertStatus(422)
            ->assertJsonFragment(['Only 0 spot(s) available for this slot']);
    }
    
    #[Test]
    public function it_allows_booking_multiple_clients_in_one_request(): void
    {
        $availableResponse = $this->getJson("/api/available-slots?service_slug=men-haircut&date={$this->validDate}");
        $slots = $availableResponse->json('data.slots');
        $slot = $slots[0];
        $data = [
            'service_slug' => $this->service->slug,
            'booking_date' => $this->validDate,
            'start_time' => $slot['start_time'],
            'end_time' => $slot['end_time'],
            'clients' => [
                [
                    'first_name' => 'Parent',
                    'last_name' => 'One',
                    'email' => 'parent1@example.com',
                ],
                [
                    'first_name' => 'Child',
                    'last_name' => 'One',
                    'email' => 'child1@example.com',
                ],
                [
                    'first_name' => 'Child',
                    'last_name' => 'Two',
                    'email' => 'child2@example.com',
                ]
            ]
        ];
        
        $response = $this->postJson('/api/bookings', $data);
        
        $response->assertStatus(201);
        
        // Verify all 3 clients were created
        $this->assertDatabaseCount('booking_clients', 3);
    }
    
    #[Test]
    public function it_rejects_booking_before_opening_hours(): void
    {
        $data = [
            'service_slug' => $this->service->slug,
            'booking_date' => $this->validDate,
            'start_time' => '07:00', // Before 08:00 opening
            'end_time' => '07:10',
            'clients' => [
                [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john@example.com',
                ]
            ]
        ];
        
        $response = $this->postJson('/api/bookings', $data);
        
        $response->assertStatus(422)
            ->assertJsonFragment(['Requested slot is not available']);
    }

    #[Test]
    public function it_rejects_booking_at_invalid_time(): void
    {
        $data = [
            'service_slug' => $this->service->slug,
            'booking_date' => $this->validDate,
            'start_time' => '08:02', // Not fitting in 10-minute slot (8:00, 8:10, 8:20...)
            'end_time' => '08:12',
            'clients' => [
                [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john@example.com',
                ]
            ]
        ];
        
        $response = $this->postJson('/api/bookings', $data);
        
        $response->assertStatus(422)
            ->assertJsonFragment(['Requested slot is not available']);
    }
    
    #[Test]
    public function it_rejects_booking_during_lunch_break(): void
    {
        $data = [
            'service_slug' => $this->service->slug,
            'booking_date' => $this->validDate,
            'start_time' => '12:15', // During lunch break 12:00-13:00
            'end_time' => '12:25',
            'clients' => [
                [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john@example.com',
                ]
            ]
        ];
        
        $response = $this->postJson('/api/bookings', $data);
        
        $response->assertStatus(422)
            ->assertJsonFragment(['Requested slot is not available']);
    }
    
    #[Test]
    public function it_rejects_booking_on_sunday(): void
    {
        // Find the next Sunday
        $sunday = now()->next(0);
        
        $data = [
            'service_slug' => $this->service->slug,
            'booking_date' => $sunday->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '10:10',
            'clients' => [
                [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john@example.com',
                ]
            ]
        ];
        
        $response = $this->postJson('/api/bookings', $data);
        
        $response->assertStatus(422)
            ->assertJsonFragment(['Requested slot is not available']);
    }

    #[Test]
    public function it_rejects_booking_on_public_holiday(): void
    {
        // Find the public holiday (3rd day from now)
        $publicHoliday = Carbon::now()->addDays(3);
        
        $data = [
            'service_slug' => $this->service->slug,
            'booking_date' => $publicHoliday->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '10:10',
            'clients' => [
                [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john@example.com',
                ]
            ]
        ];
        
        $response = $this->postJson('/api/bookings', $data);
        
        $response->assertStatus(422)
            ->assertJsonFragment(['Requested slot is not available']);
    }

    #[Test]
    public function it_rejects_booking_during_partial_planned_off(): void
    {
        $date = $this->validDate;

        PlannedOff::create([
            'service_id' => $this->service->id,
            'name' => 'Staff Meeting',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '11:00',
            'end_time' => '12:00',
        ]);

        $data = [
            'service_slug' => $this->service->slug,
            'booking_date' => $date,
            'start_time' => '11:10',
            'end_time' => '11:20',
            'clients' => [
                [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john@example.com',
                ]
            ]
        ];

        $this->postJson('/api/bookings', $data)
            ->assertStatus(422)
            ->assertJsonFragment(['Requested slot is not available']);
    }
    
    #[Test]
    public function it_generates_unique_booking_references(): void
    {
        $slots = $this->getJson(
            "/api/available-slots?service_slug={$this->service->slug}&date={$this->validDate}"
        )->json('data.slots');

        $slot = $slots[0];
        $data = [
            'service_slug' => $this->service->slug,
            'booking_date' => $this->validDate,
            'start_time' => $slot['start_time'],
            'end_time' => $slot['end_time'],
            'clients' => [
                [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john@example.com',
                ]
            ]
        ];
        
        $response1 = $this->postJson('/api/bookings', $data);
        $response2 = $this->postJson('/api/bookings', $data);

        $ref1 = $response1->json('data.booking_reference');
        $ref2 = $response2->json('data.booking_reference');
        
        $this->assertNotEquals($ref1, $ref2);
    }

    #[Test]
    public function it_rejects_booking_after_max_days_in_future(): void
    {
        $maxDate = Carbon::now()->addDays($this->service->max_days_in_future + 1);
        
        $data = [
            'service_slug' => $this->service->slug,
            'booking_date' => $maxDate->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '10:10',
            'clients' => [
                [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john@example.com',
                ]
            ]
        ];
        
        $response = $this->postJson('/api/bookings', $data);
        
        $response->assertStatus(422)
            ->assertJsonFragment(["Cannot book more than {$this->service->max_days_in_future} days in advance"]);
    }
}