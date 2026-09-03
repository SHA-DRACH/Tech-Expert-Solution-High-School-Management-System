<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives an assessment a start and an end time, not just a due date.
 *
 * A date alone cannot say "the test opens at 09:00 and closes at 10:30", which
 * is what an examination or a timed class test actually is, and it left a
 * student's assignment screen unable to tell them whether something had opened
 * yet at all.
 *
 * `due_on` is migrated into `ends_at` rather than kept alongside it. Two columns
 * both meaning "when is this due" is two answers to one question, and the pair
 * would drift the first time a screen updated one and not the other.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->timestamp('starts_at')->nullable()->after('weight');
            $table->timestamp('ends_at')->nullable()->after('starts_at');
        });

        /*
         | The existing date becomes the end of that day, which is what a "due
         | date" with no time has always meant to the people using it: hand it
         | in any time before the day is out.
         */
        DB::table('assessments')
            ->whereNotNull('due_on')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('assessments')
                        ->where('id', $row->id)
                        ->update(['ends_at' => Carbon::parse($row->due_on)->endOfDay()]);
                }
            });

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn('due_on');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->date('due_on')->nullable()->after('weight');
        });

        DB::table('assessments')
            ->whereNotNull('ends_at')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('assessments')
                        ->where('id', $row->id)
                        ->update(['due_on' => Carbon::parse($row->ends_at)->toDateString()]);
                }
            });

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn(['starts_at', 'ends_at']);
        });
    }
};
