<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messaging (spec section 45) and the public website CMS (section 49).
 *
 * Messages are threaded, and who may read a thread is decided by the
 * participants table rather than by scanning message bodies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject', 180);
            // open | closed
            $table->string('status', 20)->default('open');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'last_message_at']);
        });

        Schema::create('message_thread_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['message_thread_id', 'user_id']);
            $table->index(['school_id', 'user_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->string('attachment_path')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'message_thread_id', 'created_at']);
        });

        /*
         | The public website. Each page is a row of structured content the
         | school edits from the dashboard, so no developer is needed to change
         | the mission statement or add a news item.
         */
        Schema::create('website_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            // home | about | academics | admissions | contact | news | custom
            $table->string('key', 40);
            $table->string('title', 180);
            $table->string('slug', 180);
            $table->text('summary')->nullable();
            // Ordered blocks of content, each {heading, body, image_path}.
            $table->json('sections')->nullable();
            $table->string('hero_image_path')->nullable();
            $table->string('meta_description', 300)->nullable();
            $table->boolean('is_published')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['school_id', 'key']);
            $table->unique(['school_id', 'slug']);
        });

        Schema::create('news_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 180);
            $table->string('slug', 200);
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->string('image_path')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->unique(['school_id', 'slug']);
            $table->index(['school_id', 'is_published', 'published_at']);
        });

        Schema::create('gallery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180)->nullable();
            $table->string('caption', 300)->nullable();
            $table->string('image_path');
            $table->string('album', 80)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['school_id', 'album', 'position']);
        });

        Schema::create('social_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 40);
            $table->string('url');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['school_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_links');
        Schema::dropIfExists('gallery_items');
        Schema::dropIfExists('news_posts');
        Schema::dropIfExists('website_pages');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('message_thread_user');
        Schema::dropIfExists('message_threads');
    }
};
