<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Documents were reachable only through their parent admission, which left a
 * direct lookup by id unscoped. Storing the owning school on the row lets
 * SchoolScope filter it like every other tenant-owned record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_documents', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->after('review_note')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->index(['school_id', 'status']);
        });

        DB::statement('UPDATE admission_documents SET school_id = (SELECT school_id FROM admissions WHERE admissions.id = admission_documents.admission_id)');
    }

    public function down(): void
    {
        Schema::table('admission_documents', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['school_id', 'reviewed_by', 'reviewed_at']);
        });
    }
};
