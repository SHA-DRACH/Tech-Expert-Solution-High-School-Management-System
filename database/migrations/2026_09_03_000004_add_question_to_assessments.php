<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The question itself.
 *
 * An assessment recorded a title, a mark and a deadline, and nowhere to write
 * what the students were actually being asked to do. A teacher setting an essay
 * had to hand out the question on paper or read it aloud, and a parent asking
 * "what has she been set?" had no answer beyond its title.
 *
 * `instructions` is the question typed in; `attachment_path` is a question paper
 * uploaded as a PDF or a Word document. The file lives on the private disk and
 * is only ever streamed through an authorised route - the same treatment a
 * student's own submitted work already gets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->text('instructions')->nullable()->after('title');
            $table->string('attachment_path')->nullable()->after('instructions');

            // Kept so the file downloads under the name the teacher uploaded,
            // rather than the hashed one it is stored as.
            $table->string('attachment_name')->nullable()->after('attachment_path');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn(['instructions', 'attachment_path', 'attachment_name']);
        });
    }
};
