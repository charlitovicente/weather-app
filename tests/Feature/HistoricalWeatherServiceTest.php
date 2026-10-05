<?php

use App\Models\HistoricalWeather;
use App\Services\Weather\HistoricalWeatherService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Integration tests for the service layer in isolation from the HTTP stack.
 *
 * These resolve the service through the container (demonstrating dependency
 * injection) and assert the query/filtering/pagination contract directly.
 */
beforeEach(function () {
    $this->service = app(HistoricalWeatherService::class);
});

it('resolves from the container via dependency injection', function () {
    expect($this->service)->toBeInstanceOf(HistoricalWeatherService::class);
});

it('returns a length-aware paginator', function () {
    HistoricalWeather::factory()->count(10)->create();

    $result = $this->service->search(['per_page' => 5]);

    expect($result)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($result->total())->toBe(10)
        ->and($result->perPage())->toBe(5)
        ->and($result->count())->toBe(5);
});

it('applies city and date-range filters together', function () {
    HistoricalWeather::factory()->forCity('Manila')->create(['recorded_at' => '2026-02-10 09:00:00']);
    HistoricalWeather::factory()->forCity('Manila')->create(['recorded_at' => '2026-05-10 09:00:00']); // out of range
    HistoricalWeather::factory()->forCity('Davao')->create(['recorded_at' => '2026-02-15 09:00:00']);  // wrong city

    $result = $this->service->search([
        'city' => 'Manila',
        'from' => '2026-02-01',
        'to' => '2026-02-28',
        'per_page' => 50,
    ]);

    expect($result->total())->toBe(1)
        ->and($result->first()->city)->toBe('Manila');
});

it('orders results by most recent first', function () {
    HistoricalWeather::factory()->forCity('Cebu')->create(['recorded_at' => '2026-01-01 00:00:00']);
    HistoricalWeather::factory()->forCity('Cebu')->create(['recorded_at' => '2026-06-01 00:00:00']);

    $result = $this->service->search(['city' => 'Cebu']);

    expect($result->first()->recorded_at->format('Y-m-d'))->toBe('2026-06-01');
});
