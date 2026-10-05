<?php

namespace App\Http\Controllers;

use App\Http\Requests\HistoricalWeatherSearchRequest;
use App\Services\Weather\HistoricalWeatherService;
use Illuminate\Http\JsonResponse;

class HistoricalWeatherController extends Controller
{
    public function __construct(private readonly HistoricalWeatherService $historicalWeatherService) {}

    /**
     * Return a filtered, paginated list of historical weather records.
     *
     * The controller stays thin: validation is handled by the form request,
     * and all filtering/query/pagination logic lives in the service. The
     * response uses Laravel's standard paginator JSON shape.
     */
    public function history(HistoricalWeatherSearchRequest $request): JsonResponse
    {
        $records = $this->historicalWeatherService->search($request->filters());

        return response()->json($records);
    }
}
