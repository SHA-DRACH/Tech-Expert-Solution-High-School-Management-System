<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs student and application number generation. Counting existing rows is
 * both racy under concurrent submissions and wrong once a record is removed,
 * so each school keeps an explicit counter per series and year.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('sequence_key', 40);
            $table->string('period', 16);
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();

            $table->unique(['school_id', 'sequence_key', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
