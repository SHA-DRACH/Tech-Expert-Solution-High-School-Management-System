<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('domain')->nullable()->unique()->after('slug');
            $table->string('short_name', 50)->nullable()->after('name');
            $table->string('favicon_path')->nullable()->after('logo_path');
            $table->string('website')->nullable()->after('phone');
            $table->string('timezone', 64)->default('Africa/Monrovia')->after('secondary_color');
            $table->string('student_number_prefix', 12)->nullable()->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['domain', 'short_name', 'favicon_path', 'website', 'timezone', 'student_number_prefix']);
        });
    }
};
