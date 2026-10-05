<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Raised when the weather integration cannot return a usable result.
 *
 * Carries a user-safe message plus an HTTP-style status so the controller
 * can translate failures into clean responses without leaking internals.
 */
class WeatherServiceException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 502,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public static function cityNotFound(string $city): self
    {
        return new self(
            "We couldn't find weather data for \"{$city}\". Please check the city name and try again.",
            404,
        );
    }

    public static function invalidApiKey(): self
    {
        return new self(
            'The weather service is not configured correctly. Please contact the administrator.',
            500,
        );
    }

    public static function rateLimited(): self
    {
        return new self(
            'The weather service is receiving too many requests right now. Please try again shortly.',
            429,
        );
    }

    public static function unavailable(?Throwable $previous = null): self
    {
        return new self(
            'The weather service is temporarily unavailable. Please try again in a moment.',
            503,
            $previous,
        );
    }

    public static function unexpectedResponse(?Throwable $previous = null): self
    {
        return new self(
            'We received an unexpected response from the weather service. Please try again.',
            502,
            $previous,
        );
    }
}
