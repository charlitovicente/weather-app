<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WeatherLookupRequest extends FormRequest
{
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
            'city' => ['required', 'string', 'min:2', 'max:100', 'regex:/^[\pL\s\.\-\',]+$/u'],
            'units' => ['sometimes', 'string', Rule::in(['metric', 'imperial', 'standard'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'city.required' => 'Please enter a city name.',
            'city.min' => 'The city name is too short.',
            'city.max' => 'The city name is too long.',
            'city.regex' => 'The city name contains invalid characters.',
            'units.in' => 'The selected unit system is not supported.',
        ];
    }

    /**
     * The validated city name, trimmed.
     */
    public function city(): string
    {
        return trim((string) $this->validated('city'));
    }

    /**
     * The validated unit system, falling back to the configured default.
     */
    public function units(): string
    {
        return (string) ($this->validated('units') ?? config('services.openweathermap.units', 'metric'));
    }
}
