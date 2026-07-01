<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->string('hash', 64)->unique();
            $table->string('template_id', 50)->default('default');
            $table->json('metadata')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->unique('registration_id');
            $table->index('hash');
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
