# Weather Application

A Laravel application that combines API integration, historical data management, query optimization, and performance benchmarking to evaluate system efficiency.

Built with **Laravel 12** on **PHP 8.2**, backed by **MySQL**.

## Setup

**Prerequisites:** PHP 8.2+, Composer, and a running **MySQL** server. By default the app connects to a database named `exercise_1` (see `DB_*` in `.env.example`); create that database, or adjust the `DB_*` values to match your environment, before running the migrations.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

The seeder populates roughly **10,000 historical weather records** across a diverse set of Philippine cities. After `php artisan serve`, open the URL it prints (default `http://127.0.0.1:8000`).

Front-end assets live in `public/custom/` and are loaded directly, so no build step is required. If you change `APP_URL`, make sure you browse the app at that same host and port so the asset URLs resolve.

### Weather API key

The OpenWeatherMap key is read from `OWM_API_KEY` in `.env`. A working key is already provided in `.env.example`. Related tunables (all optional):

```
OWM_API_KEY=...            # OpenWeatherMap API key
OWM_UNITS=metric           # metric | imperial | standard
OWM_TIMEOUT=10             # HTTP timeout (seconds)
OWM_RETRY_TIMES=2          # retries on transient connection failures
OWM_CACHE_TTL=600          # cached-weather lifetime (seconds = 10 minutes)
```

## Running Tests

```bash
php artisan test
```

The application runs on **MySQL** (see the setup prerequisites). The test suite, however, overrides the database connection to an in-memory **SQLite** database via `phpunit.xml` and fakes the OpenWeatherMap API, so tests need no MySQL setup, no network access, and no live credentials. This override applies only while testing — it does not change how the application runs.

## Running the Benchmark

```bash
php artisan weather:benchmark
```

Options (all optional): `--city`, `--from`, `--to`, `--per-page`, `--iterations`. For example:

```bash
php artisan weather:benchmark --city=Cebu --from=2025-01-01 --to=2025-12-31 --iterations=10
```

The command prints the dataset size, matching records, execution time, and memory usage, followed by before/after optimization comparisons.

## Endpoints

| Method | URI | Purpose |
|---|---|---|
| GET | `/` | Weather UI (live lookup, cached lookup, historical search) |
| GET | `/weather?city={city}&units={units}` | Live current weather (always hits the external API) |
| GET | `/api/weather/{city}/cached` | Current weather with a 10-minute cache (`source: cache` or `external`) |
| GET | `/api/weather/history` | Paginated historical records; filters: `city`, `from`, `to`, `page`, `per_page` |

---

## Technical Explanation

### Application Structure

Responsibilities are separated into distinct layers so each piece has a single job and the business logic stays out of the controllers:

- **Controllers** (`app/Http/Controllers`) — thin HTTP adapters. `WeatherController` handles live and cached lookups; `HistoricalWeatherController` handles the history search. They validate input (via Form Requests), call a service, and shape the JSON response. They contain no query or API logic.
- **Form Requests** (`app/Http/Requests`) — all request validation. `WeatherLookupRequest` validates the city/units; `HistoricalWeatherSearchRequest` validates the filters (date formats, logical date range, pagination bounds).
- **Services** (`app/Services/Weather`) — the business logic layer.
  - `OpenWeatherMapService` (behind the `WeatherServiceInterface`) owns all communication with OpenWeatherMap: request building, timeout, retries, caching, logging, and translating failures into exceptions. The interface is bound to the implementation in `AppServiceProvider`, so the provider can be swapped or mocked easily.
  - `HistoricalWeatherService` owns the query/filtering/pagination layer for the dataset.
- **Query layer** — handled inside `HistoricalWeatherService` using Eloquent's query builder (filter → order → paginate). This project keeps the query logic in the service rather than a separate repository class, which is proportional to the app's size.
- **Models** (`app/Models`) — `HistoricalWeather` is the Eloquent model with `fillable` fields and `casts()` (`temperature` → decimal, `recorded_at` → datetime).
- **Standardized response (DTO)** (`app/DataTransferObjects/WeatherData`) — plays the role an API Resource would: it decouples the app from OpenWeatherMap's raw JSON shape and produces one clean, consistent structure (`city`, `temperature`, `weather_description`, `timestamp`, `source`, etc.). A DTO was chosen over an API Resource because the weather data does not originate from an Eloquent model.
- **Exceptions** (`app/Exceptions/WeatherServiceException`) — carries a user-safe message plus an HTTP status, so controllers return clean, consistent errors without leaking internals.

**Request flow:** Blade (UI) → Controller → Service → Cache / External API or Database → Service → Controller → Blade (UI).

### Dataset Handling

The ~10,000 seeded records are never loaded into memory all at once:

- The history endpoint always **paginates** (default 50, max 100 per page), so only one page is hydrated per request regardless of how many rows match.
- Filters (`city`, `from`, `to`) are applied in the SQL `WHERE` clause **before** pagination, so the database — not PHP — does the narrowing.
- Queries **select only the five columns the UI needs** (`id`, `city`, `temperature`, `weather_description`, `recorded_at`) instead of `SELECT *`.
- A typical filtered page runs in exactly **two queries** (a count and a select), avoiding N+1 problems.
- The seeder itself inserts in **chunks of 500** rather than creating 10,000 models one at a time, keeping `migrate:fresh --seed` fast.

### Database Optimization

The `historical_weather` table defines three indexes to support the filtering and sorting patterns the UI uses:

- `city` — fast single-city filtering.
- `recorded_at` — fast date-range scans and newest-first ordering.
- a composite `(city, recorded_at)` — covers the most common access pattern: a city's records over a date range, sorted by time.

A key query decision supports these indexes: date filtering uses **raw column comparisons** (`recorded_at >= ?` / `recorded_at < ?`) instead of `whereDate()`. Wrapping the column in SQL's `DATE()` function would prevent MySQL from using the `recorded_at` index; the raw-boundary approach keeps the index usable while still treating the `to` date as inclusive (it compares against the start of the following day).

### Benchmark

**What was tested.** A realistic history query — records for a city (default *Manila*) within a date range — run against the full ~10,000-row dataset. The command reports dataset size, matching record count, execution time, and memory usage, then runs three before/after comparisons.

**How it was measured.** Each measurement is wrapped with `hrtime()` for timing and `memory_get_usage()` deltas for memory, and repeated for several iterations with the best (lowest) run reported to reduce noise. Query logging is disabled during measurement so it doesn't skew the figures.

**What optimization was applied, and the results.** Three practical optimizations are demonstrated (exact numbers vary by machine and database engine):

1. **`SELECT *` vs selecting only the required columns** — roughly **15% faster** and uses less memory, because fewer columns are transferred and hydrated.
2. **Loading all matching rows vs paginating** — roughly **40% faster** and about **80% less memory** (e.g. ~0.39 MB down to ~0.08 MB), since only one page is held in memory.
3. **Index-defeating `whereDate()` vs an index-friendly range** on a selective single-day filter — roughly **84% faster** (e.g. ~5.6 ms down to ~0.9 ms), because the raw-column comparison lets MySQL use the `recorded_at` index instead of scanning the whole table.

The third comparison is the most significant and directly validates the indexing decision described above. The goal of the benchmark is clarity and demonstrating sound practice rather than chasing the lowest possible numbers.
