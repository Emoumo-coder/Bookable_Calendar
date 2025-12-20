# Copy .env.example to .env
Copy-Item -Path ".env.example" -Destination ".env" -Force

# Install Composer dependencies
composer install

# Create the SQLite database file
New-Item -Path "database/database.sqlite" -ItemType File -Force | Out-Null

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Run seeders
php artisan db:seed

# Run tests
php artisan test
