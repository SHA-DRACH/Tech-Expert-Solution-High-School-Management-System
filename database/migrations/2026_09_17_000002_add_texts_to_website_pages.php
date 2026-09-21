<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The wording of each public page: headings, introductions, button labels.
 *
 * One JSON object per page, keyed by field (see App\Support\SiteContent). Only
 * what the school has changed is stored; everything else falls back to the
 * default, so adding a new field later needs no data migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_pages', function (Blueprint $table) {
            $table->json('texts')->nullable()->after('sections');
        });
    }

    public function down(): void
    {
        Schema::table('website_pages', function (Blueprint $table) {
            $table->dropColumn('texts');
        });
    }
};
