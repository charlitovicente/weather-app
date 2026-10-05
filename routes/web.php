<?php

use App\Http\Controllers\HistoricalWeatherController;
use App\Http\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WeatherController::class, 'index'])->name('welcome');
Route::get('/weather', [WeatherController::class, 'show'])->name('weather.show');

Route::get('/api/weather/history', [HistoricalWeatherController::class, 'history'])
    ->name('weather.history');

Route::get('/api/weather/{city}/cached', [WeatherController::class, 'cached'])
    ->where('city', '[\pL\s\.\-\',]+')
    ->name('weather.cached');
