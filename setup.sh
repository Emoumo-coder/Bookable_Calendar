#!/bin/bash

# Copy .env.example to .env
cp .env.example .env

# Install Composer dependencies
composer install

# Create the SQLite database file
touch database/database.sqlite

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Run seeders
php artisan db:seed

# Run tests
php artisan test