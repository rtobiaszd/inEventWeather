<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            // MySQL: converte ENUM('indoor','outdoor') para VARCHAR
            DB::statement("ALTER TABLE events MODIFY COLUMN type VARCHAR(50) NOT NULL DEFAULT 'outdoor'");
        } else {
            // PostgreSQL: enum() cria VARCHAR(255) com CHECK constraint
            // Basta remover o check e ajustar o comprimento
            DB::statement("ALTER TABLE events DROP CONSTRAINT IF EXISTS events_type_check");
            DB::statement("ALTER TABLE events ALTER COLUMN type TYPE VARCHAR(50)");
            DB::statement("ALTER TABLE events ALTER COLUMN type SET DEFAULT 'outdoor'");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE events MODIFY COLUMN type ENUM('indoor','outdoor') NOT NULL DEFAULT 'outdoor'");
        } else {
            DB::statement("ALTER TABLE events ALTER COLUMN type TYPE VARCHAR(255)");
            DB::statement("ALTER TABLE events ADD CONSTRAINT events_type_check CHECK (type IN ('indoor', 'outdoor'))");
        }
    }
};
