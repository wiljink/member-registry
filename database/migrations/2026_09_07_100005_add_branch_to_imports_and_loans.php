<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->string('branch')->nullable()->after('type');
        });

        Schema::table('member_loans', function (Blueprint $table) {
            $table->string('branch')->nullable()->after('cid');
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', fn (Blueprint $t) => $t->dropColumn('branch'));
        Schema::table('member_loans', fn (Blueprint $t) => $t->dropColumn('branch'));
    }
};
