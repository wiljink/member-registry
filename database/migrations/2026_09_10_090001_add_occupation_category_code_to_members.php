<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raw "Occupation Category" value from the CIC members extract (T_CIF.CIFCode2,
 * USERLOOKUP 62 — code or decoded label). Rewritten on every members import and
 * decoded once into the manual `occupation_category` column while that is null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('occupation_category_code')->nullable()->after('civil_status_code');
        });
    }

    public function down(): void
    {
        Schema::table('members', fn (Blueprint $table) => $table->dropColumn('occupation_category_code'));
    }
};
