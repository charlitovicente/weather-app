<?php

namespace App\Services\Weather;

use App\DataTransferObjects\WeatherData;
use App\Exceptions\WeatherServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * OpenWeatherMap implementation of the weather service.
 *
 * Owns all communication with the OpenWeatherMap "current weather" endpoint:
 * request building, timeouts, retries, response validation, structured
 * logging, and translation of failures into {@see WeatherServiceException}.
 *
 * @see https://openweathermap.org/current
 */
class OpenWeatherMapService implements WeatherServiceInterface
{
    /**
     * Source label for data fetched fresh from the OpenWeatherMap API.
     */
    private const SOURCE_EXTERNAL = 'external';

    /**
     * Source label for data served from Laravel's cache.
     */
    private const SOURCE_CACHE = 'cache';

    private const SUPPORTED_UNITS = ['metric', 'imperial', 'standard'];

    public function __construct(private readonly HttpFactory $http) {}

    public function getCurrentWeather(string $city, ?string $units = null): WeatherData
    {
        $city = trim($city);
        $units = $this->resolveUnits($units);
        $apiKey = (string) config('services.openweathermap.key');

        if ($apiKey === '') {
            Log::error('OpenWeatherMap API key is missing from configuration.');

            throw WeatherServiceException::invalidApiKey();
        }

        $response = $this->requestWeather($city, $units, $apiKey);

        return $this->transform($response, $city, $units);
    }

    public function getCachedWeather(string $city, ?string $units = null): WeatherData
    {
        $city = trim($city);
        $units = $this->resolveUnits($units);
        $cacheKey = $this->cacheKey($city, $units);

        $cached = Cache::get($cacheKey);

        if ($cached instanceof WeatherData) {
            Log::info('Weather served from cache.', [
                'city' => $city,
                'units' => $units,
                'cache_key' => $cacheKey,
            ]);

            return $cached->withSource(self::SOURCE_CACHE);
        }

        try {
            $weather = $this->getCurrentWeather($city, $units);
        } catch (WeatherServiceException $exception) {
            // Fallback: if a stale copy somehow exists, prefer it over failing.
            $stale = Cache::get($cacheKey);

            if ($stale instanceof WeatherData) {
                Log::warning('External weather lookup failed; serving stale cache.', [
                    'city' => $city,
                    'units' => $units,
                    'reason' => $exception->getMessage(),
                ]);

                return $stale->withSource(self::SOURCE_CACHE);
            }

            throw $exception;
        }

        $ttl = (int) config('services.openweathermap.cache_ttl', 600);
        Cache::put($cacheKey, $weather, $ttl);

        Log::info('Weather fetched from external API and cached.', [
            'city' => $city,
            'units' => $units,
            'cache_key' => $cacheKey,
            'ttl' => $ttl,
        ]);

        return $weather;
    }

    /**
     * Build a deterministic, collision-resistant cache key per city and units.
     */
    private function cacheKey(string $city, string $units): string
    {
        $normalizedCity = Str::of($city)->lower()->squish()->toString();

        return 'weather:current:'.md5($normalizedCity.'|'.$units);
    }

    /**
     * Perform the HTTP request with timeout and retry handling.
     */
    private function requestWeather(string $city, string $units, string $apiKey): Response
    {
        $baseUrl = rtrim((string) config('services.openweathermap.base_url'), '/');
        $timeout = (int) config('services.openweathermap.timeout', 10);
        $retryTimes = (int) config('services.openweathermap.retry_times', 2);
        $retrySleep = (int) config('services.openweathermap.retry_sleep', 200);

        try {
            return $this->http
                ->timeout($timeout)
                ->retry($retryTimes, $retrySleep, function (Throwable $exception): bool {
                    // Only retry transient connection problems, not 4xx client errors.
                    return $exception instanceof ConnectionException;
                }, throw: false)
                ->acceptJson()
                ->get("{$baseUrl}/weather", [
                    'q' => $city,
                    'appid' => $apiKey,
                    'units' => $units,
                    'lang' => (string) config('services.openweathermap.lang', 'en'),
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('OpenWeatherMap request failed to connect.', [
                'city' => $city,
                'message' => $exception->getMessage(),
            ]);

            throw WeatherServiceException::unavailable($exception);
        }
    }

    /**
     * Validate the HTTP response and map it to a {@see WeatherData} DTO.
     */
    private function transform(Response $response, string $city, string $units): WeatherData
    {
        if ($response->failed()) {
            $this->throwForFailedResponse($response, $city);
        }

        $payload = $response->json();

        if (! is_array($payload) || ! isset($payload['main']['temp'], $payload['weather'][0])) {
            Log::error('OpenWeatherMap returned an unexpected payload shape.', [
                'city' => $city,
                'status' => $response->status(),
            ]);

            throw WeatherServiceException::unexpectedResponse();
        }

        Log::info('OpenWeatherMap current weather retrieved.', [
            'city' => $city,
            'resolved_city' => $payload['name'] ?? null,
            'units' => $units,
        ]);

        return WeatherData::fromOpenWeatherMap($payload, $units, self::SOURCE_EXTERNAL);
    }

    /**
     * Translate a non-2xx response into a user-safe exception.
     *
     * @return never
     */
    private function throwForFailedResponse(Response $response, string $city): void
    {
        $status = $response->status();

        Log::warning('OpenWeatherMap returned an error response.', [
            'city' => $city,
            'status' => $status,
            'body' => $response->json('message') ?? $response->body(),
        ]);

        throw match ($status) {
            401, 403 => WeatherServiceException::invalidApiKey(),
            404 => WeatherServiceException::cityNotFound($city),
            429 => WeatherServiceException::rateLimited(),
            default => $status >= 500
                ? WeatherServiceException::unavailable()
                : WeatherServiceException::unexpectedResponse(),
        };
    }

    /**
     * Resolve and validate the requested units, falling back to the default.
     */
    private function resolveUnits(?string $units): string
    {
        $units ??= (string) config('services.openweathermap.units', 'metric');

        return in_array($units, self::SUPPORTED_UNITS, true) ? $units : 'metric';
    }
}
