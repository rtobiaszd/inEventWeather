<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('speaker_name', 255)->nullable();
            $table->text('speaker_bio')->nullable();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('room', 100)->nullable();
            $table->string('type', 50)->default('talk');
            $table->integer('capacity')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_sessions');
    }
};
