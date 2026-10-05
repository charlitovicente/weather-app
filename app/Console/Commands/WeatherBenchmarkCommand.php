<?php

namespace App\Console\Commands;

use App\Models\HistoricalWeather;
use App\Services\Weather\HistoricalWeatherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * A lightweight, reproducible performance benchmark for the Historical
 * Weather feature. It runs a realistic filtered query against the seeded
 * dataset and reports timing/memory, then demonstrates the measurable
 * impact of two common optimizations (column selection and pagination).
 */
class WeatherBenchmarkCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'weather:benchmark
        {--city=Manila : The city to filter by}
        {--from=2024-01-01 : Start date (Y-m-d)}
        {--to=2026-12-31 : End date (Y-m-d)}
        {--per-page=50 : Records per page for the paginated query}
        {--iterations=5 : Number of timed runs per measurement}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Benchmark Historical Weather query performance against the seeded dataset.';

    /**
     * Execute the console command.
     */
    public function handle(HistoricalWeatherService $service): int
    {
        // Query logging would skew memory readings and add overhead, so keep it off.
        DB::disableQueryLog();

        $city = (string) $this->option('city');
        $from = (string) $this->option('from');
        $to = (string) $this->option('to');
        $perPage = (int) $this->option('per-page');
        $iterations = max(1, (int) $this->option('iterations'));

        $datasetSize = HistoricalWeather::query()->count();
        $matching = HistoricalWeather::query()
            ->where('city', $city)
            ->whereBetween('recorded_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->count();

        $this->printHeader($city, $from, $to, $datasetSize, $matching, $iterations);

        // ---- Primary benchmark: the real service query (filtered + paginated) ----
        $primary = $this->measure(
            fn () => $service->search([
                'city' => $city,
                'from' => $from,
                'to' => $to,
                'per_page' => $perPage,
            ]),
            $iterations
        );

        $this->components->twoColumnDetail('<fg=cyan>Dataset Size</>', number_format($datasetSize).' records');
        $this->components->twoColumnDetail('<fg=cyan>Matching Records</>', number_format($matching).' records');
        $this->components->twoColumnDetail('<fg=cyan>Execution Time</>', $this->ms($primary['time']));
        $this->components->twoColumnDetail('<fg=cyan>Memory Usage</>', $this->mb($primary['memory']));
        $this->newLine();

        $this->runOptimizationComparisons($city, $from, $to, $perPage, $iterations);

        return self::SUCCESS;
    }

    /**
     * Run and report the optimization comparisons.
     */
    private function runOptimizationComparisons(string $city, string $from, string $to, int $perPage, int $iterations): void
    {
        $this->line('<fg=yellow;options=bold>Optimization Comparison</>');
        $this->newLine();

        $fromAt = $from.' 00:00:00';
        $toAt = $to.' 23:59:59';

        // === Comparison 1: SELECT * vs SELECT only required columns ===
        $selectAll = $this->measure(
            fn () => HistoricalWeather::query()
                ->where('city', $city)
                ->whereBetween('recorded_at', [$fromAt, $toAt])
                ->orderByDesc('recorded_at')
                ->get(),
            $iterations
        );

        $selectColumns = $this->measure(
            fn () => HistoricalWeather::query()
                ->select(['id', 'city', 'temperature', 'weather_description', 'recorded_at'])
                ->where('city', $city)
                ->whereBetween('recorded_at', [$fromAt, $toAt])
                ->orderByDesc('recorded_at')
                ->get(),
            $iterations
        );

        $this->reportComparison(
            title: '1) SELECT * vs SELECT only required columns',
            measured: 'Fetching every matching row for the city/date range.',
            optimization: 'Select only the 5 columns the UI needs instead of all columns.',
            why: 'Less data is transferred from the database and hydrated into PHP objects.',
            beforeLabel: 'SELECT *            ',
            before: $selectAll,
            afterLabel: 'SELECT 5 columns    ',
            after: $selectColumns,
        );

        // === Comparison 2: Load ALL matching rows vs paginate ===
        $loadAll = $this->measure(
            fn () => HistoricalWeather::query()
                ->select(['id', 'city', 'temperature', 'weather_description', 'recorded_at'])
                ->where('city', $city)
                ->whereBetween('recorded_at', [$fromAt, $toAt])
                ->orderByDesc('recorded_at')
                ->get(),
            $iterations
        );

        $paginated = $this->measure(
            fn () => HistoricalWeather::query()
                ->select(['id', 'city', 'temperature', 'weather_description', 'recorded_at'])
                ->where('city', $city)
                ->whereBetween('recorded_at', [$fromAt, $toAt])
                ->orderByDesc('recorded_at')
                ->paginate($perPage),
            $iterations
        );

        $this->reportComparison(
            title: '2) Load ALL matching rows vs paginate',
            measured: 'Retrieving matching rows into memory for display.',
            optimization: "Paginate to {$perPage} rows per page instead of loading the full result set.",
            why: 'Only one page is hydrated into memory regardless of how many rows match.',
            beforeLabel: 'Load all matching   ',
            before: $loadAll,
            afterLabel: "Paginate ({$perPage}/page)  ",
            after: $paginated,
        );

        // === Comparison 3: index-defeating DATE() wrapper vs index-friendly range ===
        // whereDate() wraps the column in SQL DATE(), which prevents the
        // recorded_at index from being used; a plain range comparison keeps it usable.
        // Use a deliberately narrow (single-day) window so the index has rows to
        // prune — the benefit only shows when the filter is selective. Pick a day
        // that falls within the dataset's actual date span.
        $narrowDay = (string) HistoricalWeather::query()
            ->whereNotNull('recorded_at')
            ->orderBy('recorded_at')
            ->value('recorded_at')?->addDays(30)->toDateString();
        $narrowFrom = $narrowDay.' 00:00:00';
        $narrowTo = $narrowDay.' 23:59:59';

        $indexDefeating = $this->measure(
            fn () => HistoricalWeather::query()
                ->select(['id', 'city', 'temperature', 'weather_description', 'recorded_at'])
                ->whereDate('recorded_at', $narrowDay)
                ->orderByDesc('recorded_at')
                ->get(),
            $iterations
        );

        $indexFriendly = $this->measure(
            fn () => HistoricalWeather::query()
                ->select(['id', 'city', 'temperature', 'weather_description', 'recorded_at'])
                ->where('recorded_at', '>=', $narrowFrom)
                ->where('recorded_at', '<=', $narrowTo)
                ->orderByDesc('recorded_at')
                ->get(),
            $iterations
        );

        $this->reportComparison(
            title: "3) Index-defeating DATE() filter vs index-friendly range (selective: {$narrowDay})",
            measured: 'A selective single-day date filter over the whole dataset.',
            optimization: 'Compare the raw recorded_at column instead of wrapping it in SQL DATE().',
            why: 'A function around an indexed column stops MySQL from using the recorded_at index, forcing a full scan.',
            beforeLabel: 'whereDate() (no index)',
            before: $indexDefeating,
            afterLabel: 'range (uses index)    ',
            after: $indexFriendly,
        );
    }

    /**
     * Measure a callback over several iterations, returning the best (lowest)
     * time and the peak memory delta observed. Using the best run reduces
     * noise from background activity and GC pauses.
     *
     * @param  callable():mixed  $callback
     * @return array{time: float, memory: int}
     */
    private function measure(callable $callback, int $iterations): array
    {
        $bestTime = null;
        $bestMemory = null;

        for ($i = 0; $i < $iterations; $i++) {
            $memoryBefore = memory_get_usage();
            $start = hrtime(true);

            $result = $callback();

            $elapsed = (hrtime(true) - $start) / 1_000_000; // nanoseconds -> milliseconds
            $memoryUsed = memory_get_usage() - $memoryBefore;

            unset($result);

            $bestTime = $bestTime === null ? $elapsed : min($bestTime, $elapsed);
            $bestMemory = $bestMemory === null ? $memoryUsed : max($bestMemory, $memoryUsed);
        }

        return [
            'time' => (float) $bestTime,
            'memory' => (int) max(0, $bestMemory),
        ];
    }

    /**
     * Print a single before/after comparison block.
     *
     * @param  array{time: float, memory: int}  $before
     * @param  array{time: float, memory: int}  $after
     */
    private function reportComparison(
        string $title,
        string $measured,
        string $optimization,
        string $why,
        string $beforeLabel,
        array $before,
        string $afterLabel,
        array $after,
    ): void {
        $this->line("  <options=bold>{$title}</>");
        $this->line("    <fg=gray>What was measured:</> {$measured}");
        $this->line("    <fg=gray>Optimization:</>      {$optimization}");
        $this->line("    <fg=gray>Why:</>               {$why}");
        $this->newLine();

        $this->line("    Before  {$beforeLabel}  time: ".$this->ms($before['time']).'   memory: '.$this->mb($before['memory']));
        $this->line("    After   {$afterLabel}  time: ".$this->ms($after['time']).'   memory: '.$this->mb($after['memory']));

        $timeDiff = $before['time'] - $after['time'];
        $timePct = $before['time'] > 0 ? ($timeDiff / $before['time']) * 100 : 0;
        $memDiff = $before['memory'] - $after['memory'];
        $memPct = $before['memory'] > 0 ? ($memDiff / $before['memory']) * 100 : 0;

        $timeVerdict = $timeDiff >= 0
            ? sprintf('faster by %s (%.1f%%)', $this->ms(abs($timeDiff)), abs($timePct))
            : sprintf('slower by %s (%.1f%%)', $this->ms(abs($timeDiff)), abs($timePct));

        $memVerdict = $memDiff >= 0
            ? sprintf('less memory by %s (%.1f%%)', $this->mb(abs($memDiff)), abs($memPct))
            : sprintf('more memory by %s (%.1f%%)', $this->mb(abs($memDiff)), abs($memPct));

        $this->line("    <fg=green>Result:</>  {$timeVerdict}; {$memVerdict}.");
        $this->newLine();
    }

    private function printHeader(string $city, string $from, string $to, int $datasetSize, int $matching, int $iterations): void
    {
        $this->newLine();
        $this->line('<fg=yellow;options=bold>Historical Weather Performance Benchmark</>');
        $this->line("<fg=gray>Query: city = {$city}, recorded_at between {$from} and {$to}</>");
        $this->line("<fg=gray>Best of {$iterations} runs per measurement. Dataset: ".number_format($datasetSize).' records.</>');
        $this->newLine();
    }

    private function ms(float $milliseconds): string
    {
        return number_format($milliseconds, 2).' ms';
    }

    private function mb(int $bytes): string
    {
        return number_format($bytes / 1_048_576, 2).' MB';
    }
}
