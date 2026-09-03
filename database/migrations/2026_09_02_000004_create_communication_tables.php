<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Announcements, events, notifications, parent requests and the per-student
 * permission switches that govern what a student account may open.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('body');
            // general | fees | examination | emergency | event
            $table->string('category', 40)->default('general');
            // Which portals see it: ["parents","students","teachers","staff","public"]
            $table->json('audience');
            $table->string('image_path')->nullable();
            $table->string('attachment_path')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 20)->default('published');
            $table->boolean('is_emergency')->default(false);
            $table->timestamps();

            $table->index(['school_id', 'status', 'published_at']);
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            // pta | examination | graduation | sports | workshop | celebration | academic
            $table->string('category', 40)->default('academic');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('location', 180)->nullable();
            $table->boolean('is_public')->default(false);
            $table->string('status', 20)->default('scheduled');
            $table->timestamps();

            $table->index(['school_id', 'starts_at']);
            $table->index(['school_id', 'is_public']);
        });

        Schema::create('parent_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            // meeting | fee_inquiry | academic_concern | correction | leave | general
            $table->string('type', 40)->default('general');
            $table->string('subject', 180);
            $table->text('body');
            // open | in_progress | answered | closed
            $table->string('status', 20)->default('open');
            $table->text('response')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'guardian_id']);
        });

        /*
         | Per-student access switches (spec section 24).
         |
         | A row with a null student_id is the school-wide default for that
         | capability; a row naming a student overrides the default for them.
         | Nothing here requires a code change to adjust.
         */
        Schema::create('student_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('ability', 60);
            $table->boolean('allowed')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'student_id', 'ability'], 'student_ability_unique');
        });

        // Laravel's database notification channel.
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('student_permissions');
        Schema::dropIfExists('parent_requests');
        Schema::dropIfExists('events');
        Schema::dropIfExists('announcements');
    }
};
