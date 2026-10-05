<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Build a fake OpenWeatherMap "current weather" API payload for tests.
 *
 * Centralized here so every weather test shares one realistic fixture and
 * can override just the fields it cares about.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function fakeOpenWeatherMapPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Manila',
        'sys' => ['country' => 'PH'],
        'main' => ['temp' => 30.5, 'feels_like' => 34.0, 'humidity' => 70],
        'wind' => ['speed' => 3.2],
        'weather' => [['description' => 'scattered clouds', 'icon' => '03d']],
        'dt' => 1735900000,
    ], $overrides);
}

/**
 * Point the OpenWeatherMap config at deterministic test values.
 */
function configureOpenWeatherMapForTesting(): void
{
    config()->set('services.openweathermap.key', 'test-key');
    config()->set('services.openweathermap.base_url', 'https://api.openweathermap.org/data/2.5');
}
