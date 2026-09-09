<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inputs and bookkeeping for auto-deriving Type / Kind / MIGS / Active.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->date('last_savings_txn_date')->nullable()->after('savings_as_of');
            $table->date('last_share_txn_date')->nullable()->after('last_savings_txn_date');
            $table->unsignedInteger('overdue_installments_12mo')->default(0)->after('delinquent_loan_count');
            // which of {membership_type, membership_kind, migs_status, activity_status}
            // a user has hand-overridden — the importer leaves those alone.
            $table->json('classification_locked')->nullable()->after('completion_status');
        });

        Schema::table('member_loans', function (Blueprint $table) {
            $table->unsignedInteger('overdue_installments_12mo')->default(0)->after('overdue_payments_no');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['last_savings_txn_date', 'last_share_txn_date', 'overdue_installments_12mo', 'classification_locked']);
        });
        Schema::table('member_loans', fn (Blueprint $t) => $t->dropColumn('overdue_installments_12mo'));
    }
};
