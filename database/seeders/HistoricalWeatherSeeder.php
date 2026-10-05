<?php

namespace Database\Seeders;

use App\Models\HistoricalWeather;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HistoricalWeatherSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Approximate number of historical records to generate.
     */
    private const TOTAL_RECORDS = 10000;

    /**
     * Rows inserted per batch to keep memory and query size reasonable.
     */
    private const CHUNK_SIZE = 500;

    /**
     * Seed the historical_weather table with a large, diverse dataset.
     *
     * Records are built in-memory with the factory and bulk-inserted in
     * chunks for performance, rather than persisted one Eloquent model at a
     * time. This keeps `migrate:fresh --seed` fast even at 10k+ rows.
     */
    public function run(): void
    {
        $now = now();
        $remaining = self::TOTAL_RECORDS;

        while ($remaining > 0) {
            $batchSize = min(self::CHUNK_SIZE, $remaining);

            $rows = HistoricalWeather::factory()
                ->count($batchSize)
                ->make()
                ->map(function (HistoricalWeather $weather) use ($now): array {
                    return [
                        'city' => $weather->city,
                        'temperature' => $weather->temperature,
                        'weather_description' => $weather->weather_description,
                        'recorded_at' => $weather->recorded_at,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })
                ->all();

            HistoricalWeather::query()->insert($rows);

            $remaining -= $batchSize;
        }
    }
}
