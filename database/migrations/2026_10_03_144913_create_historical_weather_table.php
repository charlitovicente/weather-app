<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('historical_weather', function (Blueprint $table) {
            $table->id();
            $table->string('city', 100);
            $table->decimal('temperature', 5, 2);
            $table->string('weather_description', 100);
            $table->timestamp('recorded_at');
            $table->timestamps();

            // Filtering/sorting by a single city.
            $table->index('city');

            // Range scans and ordering by observation time.
            $table->index('recorded_at');

            // Composite index for the common "weather for a city over a period,
            // newest first" access pattern (filter by city, sort by recorded_at).
            $table->index(['city', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historical_weather');
    }
};
