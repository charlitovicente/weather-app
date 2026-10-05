<?php

namespace App\Http\Controllers;

use App\Exceptions\WeatherServiceException;
use App\Http\Requests\WeatherLookupRequest;
use App\Services\Weather\WeatherServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WeatherController extends Controller
{
    public function __construct(private readonly WeatherServiceInterface $weatherService) {}

    /**
     * Render the welcome page that hosts the weather lookup UI.
     */
    public function index(): View
    {
        return view('welcome', [
            'defaultUnits' => (string) config('services.openweathermap.units', 'metric'),
        ]);
    }

    /**
     * Look up current weather for a city and return a standardized JSON payload.
     *
     * The controller stays thin: it delegates all business logic to the
     * weather service and only shapes the HTTP response.
     */
    public function show(WeatherLookupRequest $request): JsonResponse
    {
        try {
            $weather = $this->weatherService->getCurrentWeather(
                $request->city(),
                $request->units(),
            );

            return response()->json([
                'success' => true,
                'data' => $weather->toArray(),
            ]);
        } catch (WeatherServiceException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], $exception->statusCode);
        }
    }

    /**
     * Look up current weather for a city using the 10-minute cache.
     *
     * Returns the identical payload shape as {@see show()}; only the
     * "source" field differs ("cache" vs "external"). All cache/API
     * decisions live in the service to keep the controller thin.
     */
    public function cached(Request $request, string $city): JsonResponse
    {
        $validated = $request->validate([
            'units' => ['sometimes', 'string', Rule::in(['metric', 'imperial', 'standard'])],
        ]);

        $units = $validated['units'] ?? (string) config('services.openweathermap.units', 'metric');

        try {
            $weather = $this->weatherService->getCachedWeather($city, $units);

            return response()->json([
                'success' => true,
                'data' => $weather->toArray(),
            ]);
        } catch (WeatherServiceException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], $exception->statusCode);
        }
    }
}
