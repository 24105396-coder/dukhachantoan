<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thời tiết (Open-Meteo, do dịch vụ Python ghi) và điểm rủi ro theo vùng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weather_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->timestamp('observed_at')->nullable();
            $table->decimal('temp_c', 5, 2)->nullable();
            $table->decimal('apparent_c', 5, 2)->nullable();
            $table->unsignedTinyInteger('humidity')->nullable();
            $table->decimal('wind_kph', 6, 2)->nullable();
            $table->decimal('gust_kph', 6, 2)->nullable();
            $table->unsignedSmallInteger('wind_dir')->nullable();
            $table->decimal('rain_mm', 7, 2)->nullable();
            $table->unsignedSmallInteger('weather_code')->nullable();   // mã WMO
            $table->string('source', 60)->default('openmeteo');
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
            $table->unique(['zone_id', 'observed_at']);
            $table->index('fetched_at');
        });

        Schema::create('weather_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->date('forecast_date');
            $table->decimal('tmin_c', 5, 2)->nullable();
            $table->decimal('tmax_c', 5, 2)->nullable();
            $table->decimal('rain_mm', 7, 2)->nullable();
            $table->unsignedTinyInteger('rain_prob')->nullable();
            $table->decimal('wind_max_kph', 6, 2)->nullable();
            $table->decimal('gust_max_kph', 6, 2)->nullable();
            $table->unsignedSmallInteger('weather_code')->nullable();
            $table->string('source', 60)->default('openmeteo');
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
            $table->unique(['zone_id', 'forecast_date']);
        });

        Schema::create('risk_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->timestamp('computed_at');
            $table->unsignedTinyInteger('score');                       // 0..100
            $table->unsignedTinyInteger('level');                       // 0..4
            $table->json('factors')->nullable();                        // {weather, alerts, trend}
            $table->string('source', 60)->default('analytics');
            $table->timestamps();
            $table->index(['zone_id', 'computed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_scores');
        Schema::dropIfExists('weather_forecasts');
        Schema::dropIfExists('weather_observations');
    }
};
