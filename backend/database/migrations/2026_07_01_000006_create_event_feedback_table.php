<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('nps_score'); // 0-10
            $table->text('comment')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->unique('registration_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_feedback');
    }
};
