 (function () {

            const form = document.getElementById('weather-form');

            const endpoint = form.dataset.endpoint;
            const cachedEndpoint = form.dataset.cachedEndpoint;

            const cityInput = document.getElementById('city');
            const unitsSelect = document.getElementById('units');
            const submitBtn = document.getElementById('submit-btn');
            const cachedBtn = document.getElementById('submit-prev-btn');

            const cityError = document.getElementById('city-error');
            const loading = document.getElementById('loading');
            const errorBox = document.getElementById('error');
            const errorMessage = document.getElementById('error-message');
            const result = document.getElementById('result');

            const show = (el) => el.classList.remove('hidden');
            const hide = (el) => el.classList.add('hidden');

            function resetFeedback() {
                cityError.textContent = '';
                hide(errorBox);
                hide(result);
            }

            function render(data) {
                document.getElementById('r-city').textContent =
                    data.country ? `${data.city}, ${data.country}` : data.city;
                document.getElementById('r-desc').textContent = data.weather_description;
                document.getElementById('r-temp').textContent =
                    `${Math.round(data.temperature)}${data.temperature_unit}`;

                const icon = document.getElementById('r-icon');
                if (data.icon) {
                    icon.src = `https://openweathermap.org/img/wn/${data.icon}@2x.png`;
                    icon.alt = data.weather_description;
                    show(icon);
                } else {
                    hide(icon);
                }

                document.getElementById('r-feels').textContent =
                    data.feels_like !== null ? `${Math.round(data.feels_like)}${data.temperature_unit}` : '—';
                document.getElementById('r-humidity').textContent =
                    data.humidity !== null ? `${data.humidity}%` : '—';
                document.getElementById('r-wind').textContent =
                    data.wind_speed !== null ? `${data.wind_speed} ${data.wind_speed_unit}` : '—';

                document.getElementById('r-source').textContent = `Source: ${data.source}`;
                try {
                    document.getElementById('r-time').textContent =
                        new Date(data.timestamp).toLocaleString();
                } catch (e) {
                    document.getElementById('r-time').textContent = data.timestamp;
                }

                show(result);
            }

            /**
             * Perform a weather lookup against the given URL and render the result.
             * Shared by both the "current" and "10 mins ago" (cached) buttons.
             */
            async function lookup(url, triggerBtn) {
                resetFeedback();

                const city = cityInput.value.trim();

                if (city.length < 2) {
                    cityError.textContent = 'Please enter a valid city name (at least 2 characters).';
                    return;
                }

                submitBtn.disabled = true;
                cachedBtn.disabled = true;
                show(loading);

                try {
                    const response = await fetch(url, {
                        headers: { 'Accept': 'application/json' },
                    });

                    let body;
                    try {
                        body = await response.json();
                    } catch (parseError) {
                        throw new Error('We received an unexpected response. Please try again.');
                    }

                    if (!response.ok || !body.success) {
                        // Surface validation errors from the FormRequest if present.
                        if (body.errors && body.errors.city) {
                            cityError.textContent = body.errors.city[0];
                            return;
                        }
                        throw new Error(body.message || 'Something went wrong. Please try again.');
                    }

                    render(body.data);
                } catch (networkError) {
                    errorMessage.textContent =
                        networkError.message || 'Network issue. Please check your connection and try again.';
                    show(errorBox);
                } finally {
                    hide(loading);
                    submitBtn.disabled = false;
                    cachedBtn.disabled = false;
                }
            }

            // "Get Current weather" — always hits the external API endpoint.
            form.addEventListener('submit', function (event) {
                event.preventDefault();

                const city = cityInput.value.trim();
                const units = unitsSelect.value;
                const params = new URLSearchParams({ city, units });

                lookup(`${endpoint}?${params.toString()}`, submitBtn);
            });

            // "Get 10mins ago weather" — hits the cached endpoint (cache first, API fallback).
            cachedBtn.addEventListener('click', function () {
                const city = cityInput.value.trim();
                const units = unitsSelect.value;

                if (city.length < 2) {
                    resetFeedback();
                    cityError.textContent = 'Please enter a valid city name (at least 2 characters).';
                    return;
                }

                const url = cachedEndpoint.replace('__CITY__', encodeURIComponent(city))
                    + `?${new URLSearchParams({ units }).toString()}`;

                lookup(url, cachedBtn);
            });
        })();
