<?php

namespace App\Services\Weather;

use App\DataTransferObjects\WeatherData;
use App\Exceptions\WeatherServiceException;

interface WeatherServiceInterface
{
    /**
     * Fetch current weather for the given city.
     *
     * @param  string  $city  The city name to look up.
     * @param  string|null  $units  One of "metric", "imperial", or "standard". Null uses the configured default.
     *
     * @throws WeatherServiceException When the lookup fails for any reason.
     */
    public function getCurrentWeather(string $city, ?string $units = null): WeatherData;

    /**
     * Fetch current weather for the given city, using a short-lived cache.
     *
     * On a cache hit the returned DTO reports its source as "cache"; otherwise
     * the data is fetched from the external API (source "external") and stored
     * for a 10-minute window before being returned.
     *
     * @param  string  $city  The city name to look up.
     * @param  string|null  $units  One of "metric", "imperial", or "standard". Null uses the configured default.
     *
     * @throws WeatherServiceException When the lookup fails and no cached value is available.
     */
    public function getCachedWeather(string $city, ?string $units = null): WeatherData;
}
