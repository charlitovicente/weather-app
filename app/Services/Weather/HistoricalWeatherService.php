<?php

namespace App\Services\Weather;

use App\Models\HistoricalWeather;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Query service for the historical weather dataset.
 *
 * Owns all filtering, query construction, and pagination so the controller
 * stays thin. Filters are applied before pagination at the database level,
 * only required columns are selected, and results are paginated to avoid
 * loading the full dataset into memory.
 */
class HistoricalWeatherService
{
    /**
     * The columns required by the UI; selecting these avoids pulling
     * unnecessary data from the database.
     *
     * @var list<string>
     */
    private const COLUMNS = [
        'id',
        'city',
        'temperature',
        'weather_description',
        'recorded_at',
    ];

    /**
     * Retrieve a filtered, paginated set of historical weather records.
     *
     * @param  array{city?: string|null, from?: string|null, to?: string|null, per_page?: int}  $filters
     * @return LengthAwarePaginator<int, HistoricalWeather>
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 50);

        return HistoricalWeather::query()
            ->select(self::COLUMNS)
            ->when(
                filled($filters['city'] ?? null),
                fn ($query) => $query->where('city', $filters['city'])
            )
            ->when(
                filled($filters['from'] ?? null),
                // Compare the raw column (no DATE() wrapper) so the recorded_at
                // index remains usable. "from" is inclusive from midnight.
                fn ($query) => $query->where('recorded_at', '>=', Carbon::parse($filters['from'])->startOfDay())
            )
            ->when(
                filled($filters['to'] ?? null),
                // Inclusive "to": everything strictly before the next day's midnight.
                fn ($query) => $query->where('recorded_at', '<', Carbon::parse($filters['to'])->addDay()->startOfDay())
            )
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
