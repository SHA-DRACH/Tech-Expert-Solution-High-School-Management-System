<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scholarships and fee waivers.
 *
 * A scholarship is a standing decision about a child's fees, not a one-off
 * adjustment to a single bill, so it lives on its own rather than as a number
 * typed into an invoice. That distinction matters in practice: the award has to
 * survive the invoice it discounted, be answerable a year later ("who approved
 * this, and on what terms?"), and apply again automatically the next time fees
 * are raised.
 *
 * The award is stored either as a percentage or as a fixed amount because
 * schools genuinely use both - "full tuition", "half fees", "L$5,000 off the
 * boarding fee" - and converting one into the other at the point of award would
 * silently break the moment fees change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scholarships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('sponsor')->nullable();
            $table->string('reference')->nullable();

            // 'percentage' or 'amount' - exactly one of the two columns below
            // carries the award, enforced by the model rather than the schema
            // so the message a bursar sees is a sentence, not a driver error.
            $table->string('type', 20)->default('percentage');
            $table->decimal('percentage', 5, 2)->nullable();
            $table->unsignedBigInteger('amount_minor')->nullable();

            /*
             | Both nullable on purpose. A null year means the award stands
             | until it is ended; a null term means it applies to every term in
             | the year it covers. Schools award both ways.
             */
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status', 20)->default('active')->index();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();

            $table->text('notes')->nullable();

            // Who granted it. Kept even if that account is later removed -
            // "nobody knows who approved this" is the failure mode here.
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['school_id', 'student_id']);
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scholarships');
    }
};
