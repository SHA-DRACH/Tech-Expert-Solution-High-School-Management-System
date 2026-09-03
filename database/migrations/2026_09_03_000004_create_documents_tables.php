<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Document management (spec section 42).
 *
 * One polymorphic store for everything a school files against a person or a
 * record: student documents, teacher qualifications, staff contracts,
 * certificates and anything else. Admission documents keep their own table
 * because they carry the admissions review workflow; approved ones are copied
 * across when an applicant is enrolled.
 *
 * Files live on the private disk and are only ever streamed through an
 * authorized route.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The kinds of document a school files, and its own rules for them.
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            // students | teachers | staff | general
            $table->string('applies_to', 20)->default('general');
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(false);
            // Whether the school expects this document to have an expiry date.
            $table->boolean('expires')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['school_id', 'name', 'applies_to'], 'document_type_unique');
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->morphs('documentable');
            $table->foreignId('document_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 180);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            // pending | verified | rejected | requires_correction
            $table->string('status', 30)->default('pending');
            $table->text('review_note')->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'expires_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_types');
    }
};
