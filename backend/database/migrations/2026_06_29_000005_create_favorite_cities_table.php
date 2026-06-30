<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('favorite_cities', function (Blueprint $table) {
            $table->id();
            $table->string('city', 100);
            $table->string('country', 10)->default('BR');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['city', 'country']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorite_cities');
    }
};
