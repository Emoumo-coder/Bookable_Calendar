<?php

namespace Database\Seeders;

use App\Models\PlannedOff;
use App\Models\ScheduleTemplate;
use App\Models\Service;
use App\Models\ServiceBreak;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Men Haircut Service
        $menHaircut = Service::create([
            'name' => 'Men Haircut',
            'slug' => 'men-haircut',
            'slot_duration_minutes' => 10,
            'cleanup_break_minutes' => 5,
            'max_clients_per_slot' => 3,
            'max_days_in_future' => 7,
        ]);
        
        // Schedule for Men Haircut
        $menSchedule = [
            ['day' => 1, 'start' => '08:00', 'end' => '20:00'], // Monday
            ['day' => 2, 'start' => '08:00', 'end' => '20:00'], // Tuesday
            ['day' => 3, 'start' => '08:00', 'end' => '20:00'], // Wednesday
            ['day' => 4, 'start' => '08:00', 'end' => '20:00'], // Thursday
            ['day' => 5, 'start' => '08:00', 'end' => '20:00'], // Friday
            ['day' => 6, 'start' => '10:00', 'end' => '22:00'], // Saturday
            // Sunday off (no schedule)
        ];
        
        foreach ($menSchedule as $schedule) {
            ScheduleTemplate::create([
                'service_id' => $menHaircut->id,
                'day_of_week' => $schedule['day'],
                'start_time' => $schedule['start'],
                'end_time' => $schedule['end'],
            ]);
        }
        
        // Breaks for Men Haircut
        ServiceBreak::create([
            'service_id' => $menHaircut->id,
            'name' => 'Lunch Break',
            'day_of_week' => null, // Every day
            'start_time' => '12:00',
            'end_time' => '13:00',
        ]);
        
        ServiceBreak::create([
            'service_id' => $menHaircut->id,
            'name' => 'Cleaning Break',
            'day_of_week' => null,
            'start_time' => '15:00',
            'end_time' => '16:00',
        ]);
        
        // Planned off (public holiday) - 3rd day from now
        $publicHolidayDate = Carbon::now()->addDays(3);
        PlannedOff::create([
            'service_id' => $menHaircut->id,
            'name' => 'Public Holiday',
            'start_date' => $publicHolidayDate->toDateString(),
            'end_date' => $publicHolidayDate->toDateString(),
            'start_time' => null,
            'end_time' => null, // Full day off
        ]);
        
        // Women Haircut Service
        $womenHaircut = Service::create([
            'name' => 'Women Haircut',
            'slug' => 'women-haircut',
            'slot_duration_minutes' => 60,
            'cleanup_break_minutes' => 10,
            'max_clients_per_slot' => 3,
            'max_days_in_future' => 7,
        ]);
        
        // Schedule for Women Haircut (same as men)
        foreach ($menSchedule as $schedule) {
            ScheduleTemplate::create([
                'service_id' => $womenHaircut->id,
                'day_of_week' => $schedule['day'],
                'start_time' => $schedule['start'],
                'end_time' => $schedule['end'],
            ]);
        }
        
        // Breaks for Women Haircut (same as men)
        ServiceBreak::create([
            'service_id' => $womenHaircut->id,
            'name' => 'Lunch Break',
            'day_of_week' => null,
            'start_time' => '12:00',
            'end_time' => '13:00',
        ]);
        
        ServiceBreak::create([
            'service_id' => $womenHaircut->id,
            'name' => 'Cleaning Break',
            'day_of_week' => null,
            'start_time' => '15:00',
            'end_time' => '16:00',
        ]);
        
        // Planned off for Women Haircut
        PlannedOff::create([
            'service_id' => $womenHaircut->id,
            'name' => 'Public Holiday',
            'start_date' => $publicHolidayDate->toDateString(),
            'end_date' => $publicHolidayDate->toDateString(),
            'start_time' => null,
            'end_time' => null,
        ]);
    }
}
