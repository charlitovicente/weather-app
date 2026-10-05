<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    configureOpenWeatherMapForTesting();
});

describe('cached weather workflow (Controller -> Service -> Cache)', function () {
    it('fetches from the external API on a cache miss and marks the source external', function () {
        Http::fake(['*/weather*' => Http::response(fakeOpenWeatherMapPayload(['name' => 'Cebu']), 200)]);

        $this->getJson('/api/weather/Cebu/cached')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.city', 'Cebu')
            ->assertJsonPath('data.source', 'external')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'city', 'country', 'temperature', 'weather_description',
                    'units', 'timestamp', 'source',
                ],
            ]);

        // The external provider should have been contacted exactly once.
        Http::assertSentCount(1);
    });

    it('writes the result to the cache after a miss', function () {
        Http::fake(['*/weather*' => Http::response(fakeOpenWeatherMapPayload(['name' => 'Cebu']), 200)]);

        expect(Cache::has('weather:current:'.md5('cebu|metric')))->toBeFalse();

        $this->getJson('/api/weather/Cebu/cached')->assertOk();

        // The service is expected to have populated the per-city/units cache entry.
        expect(Cache::has('weather:current:'.md5('cebu|metric')))->toBeTrue();
    });

    it('serves subsequent requests from cache without calling the API again', function () {
        Http::fake(['*/weather*' => Http::response(fakeOpenWeatherMapPayload(['name' => 'Cebu']), 200)]);

        // First call: cache miss -> external.
        $this->getJson('/api/weather/Cebu/cached')->assertJsonPath('data.source', 'external');

        // Second call: cache hit -> cache, with no additional outbound request.
        $this->getJson('/api/weather/Cebu/cached')
            ->assertOk()
            ->assertJsonPath('data.source', 'cache')
            ->assertJsonPath('data.city', 'Cebu');

        Http::assertSentCount(1);
    });

    it('returns the same data on a cache hit, differing only by the source field', function () {
        Http::fake(['*/weather*' => Http::response(fakeOpenWeatherMapPayload(['name' => 'Cebu']), 200)]);

        $external = $this->getJson('/api/weather/Cebu/cached')->json('data');
        $cached = $this->getJson('/api/weather/Cebu/cached')->json('data');

        expect($cached['source'])->toBe('cache')
            ->and($external['source'])->toBe('external');

        // Everything except "source" must be identical between the two responses.
        unset($external['source'], $cached['source']);
        expect($cached)->toEqual($external);
    });

    it('returns an error when the city is not cached and the API fails', function () {
        Http::fake(['*/weather*' => Http::response(['message' => 'city not found'], 404)]);

        $this->getJson('/api/weather/Nowhereville/cached')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    });

    it('rejects an unsupported units value on the cached endpoint', function () {
        $this->getJson('/api/weather/Cebu/cached?units=bogus')
            ->assertStatus(422)
            ->assertJsonValidationErrors('units');
    });
});
