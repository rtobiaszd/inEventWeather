<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_speaker', function (Blueprint $table) {
            $table->foreignId('session_id')->constrained('event_sessions')->cascadeOnDelete();
            $table->foreignId('speaker_id')->constrained()->cascadeOnDelete();

            $table->primary(['session_id', 'speaker_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_speaker');
    }
};
