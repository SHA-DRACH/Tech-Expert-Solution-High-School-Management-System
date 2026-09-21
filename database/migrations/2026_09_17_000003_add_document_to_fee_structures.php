<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A PDF attached to a fee structure - the school's printed fee schedule - for
 * families to download.
 *
 * Stored on the private disk and served through authorised routes, like every
 * other document. `document_on_website` is the school's explicit decision to
 * publish that one PDF on the public Online services page; without it the file
 * reaches only signed-in students and parents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->string('document_path')->nullable()->after('description');
            $table->string('document_name')->nullable()->after('document_path');
            $table->boolean('document_on_website')->default(false)->after('document_name');
        });
    }

    public function down(): void
    {
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->dropColumn(['document_path', 'document_name', 'document_on_website']);
        });
    }
};
