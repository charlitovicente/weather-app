<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    configureOpenWeatherMapForTesting();
});

it('returns standardized weather data on success', function () {
    Http::fake([
        '*/weather*' => Http::response(fakeOpenWeatherMapPayload(), 200),
    ]);

    $response = $this->getJson('/weather?city=Manila&units=metric');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.city', 'Manila')
        ->assertJsonPath('data.weather_description', 'scattered clouds')
        ->assertJsonPath('data.source', 'external')
        ->assertJsonStructure([
            'success',
            'data' => [
                'city', 'country', 'temperature', 'feels_like', 'humidity',
                'wind_speed', 'weather_description', 'icon', 'units',
                'temperature_unit', 'wind_speed_unit', 'timestamp', 'source',
            ],
        ]);
});

it('rejects a missing city with a validation error', function () {
    $this->getJson('/weather')
        ->assertStatus(422)
        ->assertJsonValidationErrors('city');
});

it('rejects an invalid city name with invalid characters', function () {
    $this->getJson('/weather?city=12345')
        ->assertStatus(422)
        ->assertJsonValidationErrors('city');
});

it('rejects an unsupported units value', function () {
    $this->getJson('/weather?city=Manila&units=kelvinish')
        ->assertStatus(422)
        ->assertJsonValidationErrors('units');
});

it('returns 404 when the external API cannot find the city', function () {
    Http::fake([
        '*/weather*' => Http::response(['cod' => '404', 'message' => 'city not found'], 404),
    ]);

    $this->getJson('/weather?city=Nowhereville')
        ->assertStatus(404)
        ->assertJsonPath('success', false);
});

it('returns 500 when the API key is rejected', function () {
    Http::fake([
        '*/weather*' => Http::response(['cod' => 401, 'message' => 'Invalid API key'], 401),
    ]);

    $this->getJson('/weather?city=Manila')
        ->assertStatus(500)
        ->assertJsonPath('success', false);
});

it('surfaces a configuration error when the API key is missing', function () {
    config()->set('services.openweathermap.key', '');

    $this->getJson('/weather?city=Manila')
        ->assertStatus(500)
        ->assertJsonPath('success', false);

    Http::assertNothingSent();
});

it('returns 429 when the API is rate limited', function () {
    Http::fake([
        '*/weather*' => Http::response(['cod' => 429, 'message' => 'rate limit'], 429),
    ]);

    $this->getJson('/weather?city=Manila')
        ->assertStatus(429)
        ->assertJsonPath('success', false);
});

it('returns 503 when the external API has a server error', function () {
    Http::fake([
        '*/weather*' => Http::response('upstream boom', 500),
    ]);

    $this->getJson('/weather?city=Manila')
        ->assertStatus(503)
        ->assertJsonPath('success', false);
});

it('returns 503 when the external API connection times out', function () {
    Http::fake(function () {
        throw new ConnectionException('cURL error 28: Operation timed out');
    });

    $this->getJson('/weather?city=Manila')
        ->assertStatus(503)
        ->assertJsonPath('success', false);
});

it('returns 502 when the API response shape is unexpected', function () {
    Http::fake([
        '*/weather*' => Http::response(['unexpected' => 'shape'], 200),
    ]);

    $this->getJson('/weather?city=Manila')
        ->assertStatus(502)
        ->assertJsonPath('success', false);
});
