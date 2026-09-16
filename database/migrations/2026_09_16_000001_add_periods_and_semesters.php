<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Periods and semesters, the way a Liberian high school keeps its year.
 *
 * Six marking periods, three to a semester, and an examination at the end of
 * each semester. A period is stored as a `terms` row with a semester number
 * rather than as a new table: every mark, report card, invoice and attendance
 * figure already hangs off `term_id`, and a second table meaning the same thing
 * would split the school's history in two.
 *
 * The semester is its own row because it owns something a period does not -
 * the examination, and whether teachers may currently enter its marks. That
 * switch belongs to the administration, not to the calendar: exam marks go in
 * when the office says so, not merely because a date has passed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            // 1 or 2. Null for a school still keeping plain terms.
            $table->unsignedTinyInteger('semester')->nullable()->after('sequence');
        });

        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('number');
            $table->string('name', 50);

            $table->date('exam_starts_on')->nullable();
            $table->date('exam_ends_on')->nullable();

            // Whether teachers may enter semester examination marks right now.
            $table->boolean('exam_entry_open')->default(false);
            $table->timestamp('exam_entry_opened_at')->nullable();
            $table->foreignId('exam_entry_opened_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['academic_year_id', 'number']);
            $table->index(['school_id', 'academic_year_id']);
        });

        Schema::table('assessments', function (Blueprint $table) {
            /*
             | Set on a semester examination, whose term_id stays null. Keeping
             | the exam off the period means the third and sixth period grades
             | are what the teacher recorded in those periods, and the exam is
             | counted once - in the semester average - rather than twice.
             */
            $table->foreignId('semester_id')->nullable()->after('term_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('semester_id');
        });

        Schema::dropIfExists('semesters');

        Schema::table('terms', function (Blueprint $table) {
            $table->dropColumn('semester');
        });
    }
};
