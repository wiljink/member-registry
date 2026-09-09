<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every import is tagged with the month the data represents. Stored as the
 * first day of that month so it sorts and filters cleanly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->date('period')->nullable()->after('type');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->date('data_period')->nullable()->after('branch')->index();
        });

        Schema::table('member_loans', function (Blueprint $table) {
            $table->date('data_period')->nullable()->after('branch')->index();
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', fn (Blueprint $t) => $t->dropColumn('period'));
        Schema::table('members', fn (Blueprint $t) => $t->dropColumn('data_period'));
        Schema::table('member_loans', fn (Blueprint $t) => $t->dropColumn('data_period'));
    }
};
