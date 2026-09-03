<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A photograph on the student record.
 *
 * Section 38 puts the student's photo on the report card, and it is the thing
 * that makes a printed card belong to a child rather than to a row in a table -
 * it is what a parent looks at first and what stops two children with the same
 * name being confused for one another. Teachers already had `photo_path`;
 * students did not have the column at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('nationality');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
