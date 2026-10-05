<?php

use App\Models\HistoricalWeather;

describe('historical weather filtering and pagination', function () {
    it('returns a paginated list with the standard paginator shape', function () {
        HistoricalWeather::factory()->count(120)->create();

        $this->getJson('/api/weather/history')
            ->assertOk()
            ->assertJsonStructure([
                'current_page', 'data', 'per_page', 'total', 'last_page', 'from', 'to',
            ])
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('per_page', 50)
            ->assertJsonPath('total', 120)
            ->assertJsonPath('last_page', 3);
    });

    it('defaults to 50 records per page', function () {
        HistoricalWeather::factory()->count(60)->create();

        $response = $this->getJson('/api/weather/history')->assertOk();

        expect(count($response->json('data')))->toBe(50);
    });

    it('respects a custom per_page within limits', function () {
        HistoricalWeather::factory()->count(60)->create();

        $this->getJson('/api/weather/history?per_page=25')
            ->assertOk()
            ->assertJsonPath('per_page', 25)
            ->assertJsonCount(25, 'data');
    });

    it('navigates to a specific page', function () {
        HistoricalWeather::factory()->count(60)->create();

        $this->getJson('/api/weather/history?page=2&per_page=50')
            ->assertOk()
            ->assertJsonPath('current_page', 2)
            ->assertJsonCount(10, 'data');
    });

    it('filters by city', function () {
        HistoricalWeather::factory()->count(10)->forCity('Davao')->create();
        HistoricalWeather::factory()->count(10)->forCity('Baguio')->create();

        $response = $this->getJson('/api/weather/history?city=Davao')->assertOk();

        expect($response->json('total'))->toBe(10);
        expect(collect($response->json('data'))->pluck('city')->unique()->all())->toBe(['Davao']);
    });

    it('filters by an inclusive date range', function () {
        HistoricalWeather::factory()->forCity('Manila')->create(['recorded_at' => '2026-03-15 12:00:00']);
        HistoricalWeather::factory()->forCity('Manila')->create(['recorded_at' => '2026-03-31 23:30:00']);
        HistoricalWeather::factory()->forCity('Manila')->create(['recorded_at' => '2026-04-01 00:30:00']);

        $response = $this->getJson('/api/weather/history?from=2026-03-01&to=2026-03-31')->assertOk();

        expect($response->json('total'))->toBe(2);
    });

    it('filters by city, date range, and pagination together with correct metadata', function () {
        // 70 Manila records inside the target window.
        HistoricalWeather::factory()->count(70)->forCity('Manila')->create(['recorded_at' => '2026-01-15 10:00:00']);
        // Noise that must be excluded: wrong city and out-of-range dates.
        HistoricalWeather::factory()->count(20)->forCity('Cebu')->create(['recorded_at' => '2026-01-15 10:00:00']);
        HistoricalWeather::factory()->count(15)->forCity('Manila')->create(['recorded_at' => '2026-09-15 10:00:00']);

        $response = $this->getJson('/api/weather/history?city=Manila&from=2026-01-01&to=2026-01-31&per_page=50&page=1')
            ->assertOk()
            ->assertJsonPath('total', 70)
            ->assertJsonPath('per_page', 50)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('last_page', 2)
            ->assertJsonCount(50, 'data');

        // Every returned row must honor the city filter.
        expect(collect($response->json('data'))->pluck('city')->unique()->all())->toBe(['Manila']);
    });

    it('returns an empty result set cleanly when nothing matches', function () {
        HistoricalWeather::factory()->count(5)->forCity('Cebu')->create();

        $this->getJson('/api/weather/history?city=NonexistentCity')
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonCount(0, 'data');
    });

    it('returns only the columns required by the UI', function () {
        HistoricalWeather::factory()->create();

        $response = $this->getJson('/api/weather/history?per_page=1')->assertOk();

        expect(array_keys($response->json('data.0')))
            ->toBe(['id', 'city', 'temperature', 'weather_description', 'recorded_at']);
    });
});

describe('historical weather request validation', function () {
    it('rejects an invalid "from" date format', function () {
        $this->getJson('/api/weather/history?from=03-2026')
            ->assertStatus(422)
            ->assertJsonValidationErrors('from');
    });

    it('rejects an illogical date range where to is before from', function () {
        $this->getJson('/api/weather/history?from=2026-06-01&to=2026-01-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors('to');
    });

    it('rejects per_page above the maximum', function () {
        $this->getJson('/api/weather/history?per_page=500')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    });

    it('rejects a non-positive per_page', function () {
        $this->getJson('/api/weather/history?per_page=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    });

    it('rejects a negative page number', function () {
        $this->getJson('/api/weather/history?page=-1')
            ->assertStatus(422)
            ->assertJsonValidationErrors('page');
    });

    it('rejects a non-integer per_page', function () {
        $this->getJson('/api/weather/history?per_page=abc')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    });
});
