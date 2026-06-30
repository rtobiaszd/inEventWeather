<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Converte enum para varchar sem perder dados existentes
        DB::statement("ALTER TABLE events MODIFY COLUMN type VARCHAR(50) NOT NULL DEFAULT 'outdoor'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE events MODIFY COLUMN type ENUM('indoor','outdoor') NOT NULL DEFAULT 'outdoor'");
    }
};
