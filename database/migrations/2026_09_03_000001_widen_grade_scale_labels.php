<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grade labels were capped at 5 characters, which fits "A" and "B+" but not the
 * word scales many schools use — "Distinction", "Credit", "Pass". Since each
 * school defines its own scale, the column has to hold whatever they choose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grade_scales', function (Blueprint $table) {
            $table->string('grade', 20)->change();
        });

        Schema::table('report_card_items', function (Blueprint $table) {
            $table->string('grade', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('grade_scales', function (Blueprint $table) {
            $table->string('grade', 5)->change();
        });

        Schema::table('report_card_items', function (Blueprint $table) {
            $table->string('grade', 5)->nullable()->change();
        });
    }
};
