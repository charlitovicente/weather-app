(function () {

    const form = document.getElementById('history-form');
    if (!form) {
        return;
    }

    const endpoint = form.dataset.endpoint;

    const cityInput = document.getElementById('h-city');
    const fromInput = document.getElementById('h-from');
    const toInput = document.getElementById('h-to');
    const pageInput = document.getElementById('h-page');
    const perPageSelect = document.getElementById('h-per-page');

    const searchBtn = document.getElementById('history-search-btn');
    const resetBtn = document.getElementById('history-reset-btn');
    const errorBox = document.getElementById('history-error');
    const loading = document.getElementById('history-loading');
    const summary = document.getElementById('history-summary');
    const rows = document.getElementById('history-rows');
    const pagination = document.getElementById('history-pagination');

    const show = (el) => el.classList.remove('hidden');
    const hide = (el) => el.classList.add('hidden');

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function formatDateTime(value) {
        try {
            return new Date(value).toLocaleString();
        } catch (e) {
            return value;
        }
    }

    function renderRows(data) {
        if (!data.length) {
            rows.innerHTML = '<tr><td colspan="4" class="empty-cell">No records match your filters.</td></tr>';
            return;
        }

        rows.innerHTML = data.map((record) => `
            <tr>
                <td>${escapeHtml(record.city)}</td>
                <td>${escapeHtml(record.temperature)}&deg;C</td>
                <td style="text-transform: capitalize;">${escapeHtml(record.weather_description)}</td>
                <td>${escapeHtml(formatDateTime(record.recorded_at))}</td>
            </tr>
        `).join('');
    }

    /**
     * Build the pagination controls from Laravel's standard paginator payload.
     */
    function renderPagination(payload) {
        const current = payload.current_page;
        const last = payload.last_page;
        pagination.innerHTML = '';

        if (last <= 1) {
            return;
        }

        const makeButton = (label, targetPage, { disabled = false, active = false } = {}) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.innerHTML = label;
            btn.disabled = disabled;
            if (active) {
                btn.classList.add('active');
            }
            if (!disabled && !active) {
                btn.addEventListener('click', () => load(targetPage));
            }
            return btn;
        };

        pagination.appendChild(makeButton('&laquo; Prev', current - 1, { disabled: current <= 1 }));

        // Windowed page numbers around the current page.
        const windowSize = 2;
        const start = Math.max(1, current - windowSize);
        const end = Math.min(last, current + windowSize);

        if (start > 1) {
            pagination.appendChild(makeButton('1', 1, { active: current === 1 }));
            if (start > 2) {
                const info = document.createElement('span');
                info.className = 'page-info';
                info.textContent = '…';
                pagination.appendChild(info);
            }
        }

        for (let p = start; p <= end; p++) {
            pagination.appendChild(makeButton(String(p), p, { active: p === current }));
        }

        if (end < last) {
            if (end < last - 1) {
                const info = document.createElement('span');
                info.className = 'page-info';
                info.textContent = '…';
                pagination.appendChild(info);
            }
            pagination.appendChild(makeButton(String(last), last, { active: current === last }));
        }

        pagination.appendChild(makeButton('Next &raquo;', current + 1, { disabled: current >= last }));
    }

    function renderSummary(payload) {
        if (payload.total === 0) {
            summary.textContent = 'No records found.';
            return;
        }
        summary.textContent =
            `Showing ${payload.from}–${payload.to} of ${payload.total.toLocaleString()} records (page ${payload.current_page} of ${payload.last_page}).`;
    }

    async function load(page) {
        errorBox.textContent = '';
        show(loading);
        searchBtn.disabled = true;

        if (typeof page === 'number') {
            pageInput.value = page;
        }

        const params = new URLSearchParams();
        if (cityInput.value.trim()) {
            params.set('city', cityInput.value.trim());
        }
        if (fromInput.value) {
            params.set('from', fromInput.value);
        }
        if (toInput.value) {
            params.set('to', toInput.value);
        }
        params.set('page', pageInput.value || '1');
        params.set('per_page', perPageSelect.value);

        try {
            const response = await fetch(`${endpoint}?${params.toString()}`, {
                headers: { 'Accept': 'application/json' },
            });

            const body = await response.json();

            if (response.status === 422 && body.errors) {
                const first = Object.values(body.errors)[0];
                errorBox.textContent = Array.isArray(first) ? first[0] : String(first);
                return;
            }

            if (!response.ok) {
                throw new Error(body.message || 'Unable to load records. Please try again.');
            }

            renderRows(body.data);
            renderSummary(body);
            renderPagination(body);
        } catch (error) {
            errorBox.textContent = error.message || 'Network issue. Please try again.';
            rows.innerHTML = '<tr><td colspan="4" class="empty-cell">Could not load records.</td></tr>';
            pagination.innerHTML = '';
            summary.textContent = '';
        } finally {
            hide(loading);
            searchBtn.disabled = false;
        }
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        load(1); // New search always starts at page 1.
    });

    resetBtn.addEventListener('click', function () {
        cityInput.value = '';
        fromInput.value = '';
        toInput.value = '';
        pageInput.value = '1';
        perPageSelect.value = '50';
        load(1);
    });

    // Auto-load page 1 at 50 records per page on initial page load.
    load(1);
})();
