<?php

namespace App\DataTransferObjects;

use Carbon\CarbonImmutable;

/**
 * Standardized, application-friendly representation of a weather reading.
 *
 * This decouples the rest of the application from the raw OpenWeatherMap
 * response shape, so controllers and views depend only on this contract.
 */
readonly class WeatherData
{
    public function __construct(
        public string $city,
        public ?string $country,
        public float $temperature,
        public ?float $feelsLike,
        public ?int $humidity,
        public ?float $windSpeed,
        public string $weatherDescription,
        public ?string $icon,
        public string $units,
        public CarbonImmutable $timestamp,
        public string $source,
    ) {}

    /**
     * Build the DTO from a decoded OpenWeatherMap "current weather" payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromOpenWeatherMap(array $payload, string $units, string $source): self
    {
        $weather = $payload['weather'][0] ?? [];

        return new self(
            city: (string) ($payload['name'] ?? 'Unknown'),
            country: $payload['sys']['country'] ?? null,
            temperature: (float) ($payload['main']['temp'] ?? 0),
            feelsLike: isset($payload['main']['feels_like']) ? (float) $payload['main']['feels_like'] : null,
            humidity: isset($payload['main']['humidity']) ? (int) $payload['main']['humidity'] : null,
            windSpeed: isset($payload['wind']['speed']) ? (float) $payload['wind']['speed'] : null,
            weatherDescription: (string) ($weather['description'] ?? 'unknown'),
            icon: $weather['icon'] ?? null,
            units: $units,
            timestamp: isset($payload['dt'])
                ? CarbonImmutable::createFromTimestampUTC((int) $payload['dt'])
                : CarbonImmutable::now('UTC'),
            source: $source,
        );
    }

    /**
     * Return a copy of this reading with a different source label.
     *
     * Used by the caching layer to distinguish "external" (fresh from the API)
     * from "cache" (served from storage) without mutating the stored value.
     */
    public function withSource(string $source): self
    {
        return new self(
            city: $this->city,
            country: $this->country,
            temperature: $this->temperature,
            feelsLike: $this->feelsLike,
            humidity: $this->humidity,
            windSpeed: $this->windSpeed,
            weatherDescription: $this->weatherDescription,
            icon: $this->icon,
            units: $this->units,
            timestamp: $this->timestamp,
            source: $source,
        );
    }

    /**
     * The temperature unit symbol derived from the request units.
     */
    public function temperatureUnit(): string
    {
        return match ($this->units) {
            'imperial' => '°F',
            'standard' => 'K',
            default => '°C',
        };
    }

    /**
     * The wind speed unit derived from the request units.
     */
    public function windSpeedUnit(): string
    {
        return $this->units === 'imperial' ? 'mph' : 'm/s';
    }

    /**
     * The standardized array contract consumed by controllers and views.
     *
     * @return array{
     *     city: string,
     *     country: string|null,
     *     temperature: float,
     *     feels_like: float|null,
     *     humidity: int|null,
     *     wind_speed: float|null,
     *     weather_description: string,
     *     icon: string|null,
     *     units: string,
     *     temperature_unit: string,
     *     wind_speed_unit: string,
     *     timestamp: string,
     *     source: string
     * }
     */
    public function toArray(): array
    {
        return [
            'city' => $this->city,
            'country' => $this->country,
            'temperature' => $this->temperature,
            'feels_like' => $this->feelsLike,
            'humidity' => $this->humidity,
            'wind_speed' => $this->windSpeed,
            'weather_description' => $this->weatherDescription,
            'icon' => $this->icon,
            'units' => $this->units,
            'temperature_unit' => $this->temperatureUnit(),
            'wind_speed_unit' => $this->windSpeedUnit(),
            'timestamp' => $this->timestamp->toIso8601ZuluString(),
            'source' => $this->source,
        ];
    }
}
