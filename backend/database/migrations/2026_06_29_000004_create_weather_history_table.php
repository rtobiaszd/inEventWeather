<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('weather_history', function (Blueprint $table) {
            $table->id();
            $table->string('city', 100);
            $table->string('country', 10)->default('BR');
            $table->decimal('temperature', 5, 2)->nullable();
            $table->decimal('feels_like', 5, 2)->nullable();
            $table->unsignedTinyInteger('humidity')->nullable();
            $table->decimal('wind_speed', 6, 2)->nullable();
            $table->string('weather_main', 50)->nullable();
            $table->string('weather_description', 150)->nullable();
            $table->unsignedTinyInteger('aqi')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('city');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_history');
    }
};
