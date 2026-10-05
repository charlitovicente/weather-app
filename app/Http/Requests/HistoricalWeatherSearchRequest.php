<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HistoricalWeatherSearchRequest extends FormRequest
{
    /**
     * The maximum number of records allowed per page.
     */
    public const MAX_PER_PAGE = 100;

    /**
     * The default number of records per page.
     */
    public const DEFAULT_PER_PAGE = 50;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from.date_format' => 'The "from" date must be in YYYY-MM-DD format.',
            'to.date_format' => 'The "to" date must be in YYYY-MM-DD format.',
            'to.after_or_equal' => 'The "to" date must be the same as or later than the "from" date.',
            'per_page.max' => 'You may request at most '.self::MAX_PER_PAGE.' records per page.',
        ];
    }

    /**
     * The validated, normalized filter values for the query.
     *
     * @return array{city: string|null, from: string|null, to: string|null, per_page: int}
     */
    public function filters(): array
    {
        $city = $this->validated('city');

        return [
            'city' => filled($city) ? trim($city) : null,
            'from' => $this->validated('from') ?: null,
            'to' => $this->validated('to') ?: null,
            'per_page' => (int) ($this->validated('per_page') ?? self::DEFAULT_PER_PAGE),
        ];
    }
}
