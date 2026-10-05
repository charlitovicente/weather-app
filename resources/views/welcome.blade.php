<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Weather &middot; {{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    <link rel="stylesheet" href="{{asset('custom/style.css')}}">
   
</head>
<body>
    <div class="shell">
        <h1 class="title">Weather</h1>
        <p class="subtitle">Live conditions from OpenWeatherMap.</p>

        <div class="card">
            <form
                id="weather-form"
                novalidate
                data-endpoint="{{ route('weather.show') }}"
                data-cached-endpoint="{{ route('weather.cached', ['city' => '__CITY__']) }}"
            >
                <div>
                    <label for="city">City</label>
                    <input
                        type="text"
                        id="city"
                        name="city"
                        placeholder="e.g. Manila"
                        autocomplete="off"
                        aria-describedby="city-error"
                        required
                    >
                    <div class="field-error" id="city-error" role="alert"></div>
                </div>

                <div class="row">
                    <div style="flex: 1;">
                        <label for="units">Units</label>
                        <select id="units" name="units">
                            <option value="metric" @selected(($defaultUnits ?? 'metric') === 'metric')>Celsius (°C)</option>
                            <option value="imperial" @selected(($defaultUnits ?? 'metric') === 'imperial')>Fahrenheit (°F)</option>
                            <option value="standard" @selected(($defaultUnits ?? 'metric') === 'standard')>Kelvin (K)</option>
                        </select>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <button type="submit" id="submit-btn">Get Current weather</button>
                    <button type="button" id="submit-prev-btn">Get 10mins <small> ago</small> weather</button>
                </div>
            </form>

            <div class="state">
                <div class="loading hidden" id="loading">
                    <span class="spinner" aria-hidden="true"></span>
                    <span>Fetching current conditions&hellip;</span>
                </div>

                <div class="alert hidden" id="error" role="alert">
                    <span>&#9888;</span>
                    <span id="error-message"></span>
                </div>

                <div class="result hidden" id="result">
                    <div class="result-head">
                        <div>
                            <div class="result-city" id="r-city"></div>
                            <div class="result-desc" id="r-desc"></div>
                        </div>
                        <div style="text-align: right;">
                            <img class="result-icon hidden" id="r-icon" alt="" />
                            <div class="result-temp" id="r-temp"></div>
                        </div>
                    </div>

                    <div class="metrics">
                        <div class="metric">
                            <div class="metric-label">Feels like</div>
                            <div class="metric-value" id="r-feels"></div>
                        </div>
                        <div class="metric">
                            <div class="metric-label">Humidity</div>
                            <div class="metric-value" id="r-humidity"></div>
                        </div>
                        <div class="metric">
                            <div class="metric-label">Wind</div>
                            <div class="metric-value" id="r-wind"></div>
                        </div>
                    </div>

                    <div class="meta">
                        <span id="r-source"></span>
                        <span id="r-time"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card history-card">
            <h2 class="section-title">Historical Weather</h2>
            <p class="subtitle">Search the seeded historical dataset.</p>

            <form id="history-form" novalidate data-endpoint="{{ route('weather.history') }}">
                <div class="filter-grid">
                    <div>
                        <label for="h-city">City</label>
                        <input type="text" id="h-city" name="city" placeholder="e.g. Manila" autocomplete="off">
                    </div>
                    <div>
                        <label for="h-from">Date From</label>
                        <input type="date" id="h-from" name="from">
                    </div>
                    <div>
                        <label for="h-to">Date To</label>
                        <input type="date" id="h-to" name="to">
                    </div>
                    <div>
                        <label for="h-page">Page</label>
                        <input type="number" id="h-page" name="page" min="1" value="1">
                    </div>
                    <div>
                        <label for="h-per-page">Per Page</label>
                        <select id="h-per-page" name="per_page">
                            <option value="25">25</option>
                            <option value="50" selected>50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" id="history-search-btn">Search</button>
                    <button type="button" id="history-reset-btn" class="btn-secondary">Reset</button>
                </div>

                <div class="field-error" id="history-error" role="alert"></div>
            </form>

            <div class="history-state">
                <div class="loading hidden" id="history-loading">
                    <span class="spinner" aria-hidden="true"></span>
                    <span>Loading records&hellip;</span>
                </div>

                <div class="history-summary" id="history-summary"></div>

                <div class="table-wrap">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>City</th>
                                <th>Temperature</th>
                                <th>Weather Description</th>
                                <th>Recorded Date/Time</th>
                            </tr>
                        </thead>
                        <tbody id="history-rows">
                            <tr><td colspan="4" class="empty-cell">No records yet.</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="pagination" id="history-pagination"></div>
            </div>
        </div>
    </div>

    <script src="{{ asset('custom/index.js') }}"></script>
    <script src="{{ asset('custom/history.js') }}"></script>
</body>
</html>
