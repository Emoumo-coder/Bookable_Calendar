<?php

use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ServiceController;
use Illuminate\Support\Facades\Route;

Route::get('/available-slots', [BookingController::class, 'availableSlots']);
Route::post('/bookings', [BookingController::class, 'store']);

Route::get('/services', [ServiceController::class, 'getServices']);