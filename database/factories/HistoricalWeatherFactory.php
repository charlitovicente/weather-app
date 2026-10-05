<?php

namespace Database\Factories;

use App\Models\HistoricalWeather;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HistoricalWeather>
 */
class HistoricalWeatherFactory extends Factory
{
    /**
     * A diverse set of Philippine cities and municipalities, including the
     * National Capital Region (NCR) and locations across the country, to
     * support realistic filtering, aggregation, and search scenarios.
     *
     * @var list<string>
     */
    public const CITIES = [
        // National Capital Region (NCR)
        'Manila',
        'Quezon City',
        'Makati',
        'Taguig',
        'Pasig',
        'Mandaluyong',
        'Parañaque',
        'Caloocan',
        'Las Piñas',
        'Muntinlupa',
        'Marikina',
        'Pasay',
        'Valenzuela',
        'Malabon',
        'Navotas',
        'San Juan',
        'Pateros',

        // Luzon
        'Baguio',
        'Bicol',
        'Dagupan',
        'Angeles',
        'Olongapo',
        'Batangas',
        'Lucena',
        'Vigan',
        'Laoag',
        'Tuguegarao',
        'Puerto Princesa',

        // Visayas
        'Cebu',
        'Iloilo',
        'Bacolod',
        'Tacloban',
        'Dumaguete',
        'Tagbilaran',

        // Mindanao
        'Davao',
        'Bukidnon',
        'Cagayan de Oro',
        'Zamboanga',
        'General Santos',
        'Butuan',
        'Cotabato',
        'Iligan',
    ];

    /**
     * Realistic weather descriptions mirroring OpenWeatherMap-style labels.
     *
     * @var list<string>
     */
    public const DESCRIPTIONS = [
        'clear sky',
        'few clouds',
        'scattered clouds',
        'broken clouds',
        'overcast clouds',
        'light rain',
        'moderate rain',
        'heavy intensity rain',
        'thunderstorm',
        'light intensity shower rain',
        'mist',
        'haze',
        'drizzle',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'city' => fake()->randomElement(self::CITIES),
            // Tropical climate range (°C), spread wide enough for meaningful sorting.
            'temperature' => fake()->randomFloat(2, 20, 38),
            'weather_description' => fake()->randomElement(self::DESCRIPTIONS),
            // Observations spread across the past ~2 years for date-range testing.
            'recorded_at' => fake()->dateTimeBetween('-2 years', 'now'),
        ];
    }

    /**
     * State for records belonging to a specific city.
     */
    public function forCity(string $city): static
    {
        return $this->state(fn (array $attributes): array => [
            'city' => $city,
        ]);
    }
}
