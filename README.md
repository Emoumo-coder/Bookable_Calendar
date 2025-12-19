# Bookable Calendar API

A robust appointment booking system built with Laravel.

## Setup

1. Clone repository
2. Install dependencies: `composer install`
3. Copy `.env.example` to `.env`
4. Generate key: `php artisan key:generate`
5. Configure database in `.env`
6. Run migrations: `php artisan migrate`
7. Seed database: `php artisan db:seed`
8. Run tests: `php artisan test`

## API Endpoints

### GET /api/available-slots
Parameters:
- `service_slug` (required): Service identifier (men-haircut, women-haircut)
- `date` (required): Date in YYYY-MM-DD format

### POST /api/bookings
Body:
```json
{
    "service_slug": "men-haircut",
    "booking_date": "2024-01-15",
    "start_time": "08:00",
    "end_time": "08:10",
    "clients": [
        {
            "first_name": "John",
            "last_name": "Doe",
            "email": "john@example.com"
        }
    ]
}