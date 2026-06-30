<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('status', 20)->default('planned')->after('description');
            $table->decimal('budget', 12, 2)->nullable()->after('status');
            $table->decimal('revenue', 12, 2)->nullable()->after('budget');
            $table->decimal('ticket_price', 10, 2)->nullable()->after('revenue');
            $table->string('organizer', 255)->nullable()->after('ticket_price');
            $table->string('organizer_contact', 255)->nullable()->after('organizer');
            $table->string('venue', 255)->nullable()->after('organizer_contact');
            $table->date('end_date')->nullable()->after('event_date');
            $table->time('end_time')->nullable()->after('event_time');
            $table->string('banner_url', 500)->nullable()->after('end_time');
            $table->string('tags', 500)->nullable()->after('banner_url');
            $table->text('notes')->nullable()->after('tags');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'status', 'budget', 'revenue', 'ticket_price',
                'organizer', 'organizer_contact', 'venue',
                'end_date', 'end_time', 'banner_url', 'tags', 'notes',
            ]);
        });
    }
};
