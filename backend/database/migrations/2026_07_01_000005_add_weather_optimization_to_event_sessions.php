<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_sessions', function (Blueprint $table) {
            $table->boolean('outdoor_suitable')->default(true)->after('capacity');
            $table->timestamp('weather_optimized_at')->nullable()->after('outdoor_suitable');
            $table->time('actual_start_time')->nullable()->after('weather_optimized_at');
            $table->time('actual_end_time')->nullable()->after('actual_start_time');
        });
    }

    public function down(): void
    {
        Schema::table('event_sessions', function (Blueprint $table) {
            $table->dropColumn(['outdoor_suitable', 'weather_optimized_at', 'actual_start_time', 'actual_end_time']);
        });
    }
};
