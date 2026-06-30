<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city', 100);
            $table->string('country', 10)->default('BR');
            $table->date('event_date');
            $table->time('event_time');
            $table->enum('type', ['indoor', 'outdoor'])->default('outdoor');
            $table->unsignedInteger('expected_audience')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('event_date');
            $table->index('city');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
