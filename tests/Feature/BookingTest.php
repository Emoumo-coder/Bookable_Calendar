<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingClient;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }
    
    #[Test]
    public function it_returns_available_slots_for_a_service_and_date(): void
    {
        $tomorrow = now()->addDay()->format('Y-m-d');
        
        $response = $this->getJson("/api/available-slots?service_slug=men-haircut&date={$tomorrow}");
        
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
        $data = [
            'service_slug' => 'men-haircut',
            'booking_date' => now()->addDay()->format('Y-m-d'),
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
        
        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'booking_reference',
                    'booking_date',
                    'start_time',
                    'end_time',
                    'service',
                    'clients',
                    'client_count',
                    'status',
                    'created_at'
                ],
                'message'
            ]);
        
        // Verify booking was created
        $this->assertDatabaseHas('bookings', [
            'booking_date' => $data['booking_date'],
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
        $data = [
            'service_slug' => 'men-haircut',
            'booking_date' => now()->subDay()->format('Y-m-d'),
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
        $service = Service::where('slug', 'men-haircut')->first();
        $tomorrow = now()->addDay()->format('Y-m-d');
        
        // Book all 3 spots for a specific slot
        $booking = Booking::create([
            'service_id' => $service->id,
            'booking_date' => $tomorrow,
            'start_time' => '08:00',
            'end_time' => '08:10',
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
            'service_slug' => 'men-haircut',
            'booking_date' => $tomorrow,
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
            ->assertJsonFragment(['Only 0 spot(s) available for this slot']);
    }
    
    #[Test]
    public function it_allows_booking_multiple_clients_in_one_request(): void
    {
        $data = [
            'service_slug' => 'men-haircut',
            'booking_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '09:10',
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
            'service_slug' => 'men-haircut',
            'booking_date' => now()->addDay()->format('Y-m-d'),
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
    public function it_rejects_booking_during_lunch_break(): void
    {
        $data = [
            'service_slug' => 'men-haircut',
            'booking_date' => now()->addDay()->format('Y-m-d'),
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
        $sunday = now();
        while ($sunday->dayOfWeek !== 0) { // 0 = Sunday
            $sunday->addDay();
        }
        
        $data = [
            'service_slug' => 'men-haircut',
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
    public function it_generates_unique_booking_references(): void
    {
        $data = [
            'service_slug' => 'men-haircut',
            'booking_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '08:20',
            'end_time' => '08:30',
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
}