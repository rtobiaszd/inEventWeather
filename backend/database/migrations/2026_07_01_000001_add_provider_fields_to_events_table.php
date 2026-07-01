<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('provider', 50)->nullable()->default('manual')->after('notes');
            $table->string('provider_id', 255)->nullable()->after('provider');

            $table->unique(['provider', 'provider_id']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->decimal('budget', 12, 2)->nullable()->default(0.00)->change();
            $table->decimal('revenue', 12, 2)->nullable()->default(0.00)->change();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['provider', 'provider_id']);
            $table->dropColumn(['provider', 'provider_id']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->decimal('budget', 12, 2)->nullable()->default(null)->change();
            $table->decimal('revenue', 12, 2)->nullable()->default(null)->change();
        });
    }
};
