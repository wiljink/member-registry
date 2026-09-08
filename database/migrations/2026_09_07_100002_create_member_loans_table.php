<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_loans', function (Blueprint $table) {
            $table->id();
            $table->string('cid')->index();
            $table->string('contract_no');
            $table->string('role')->nullable();
            $table->string('contract_type')->nullable();
            $table->string('contract_phase')->nullable();
            $table->string('currency')->nullable();
            $table->date('start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->date('last_payment_date')->nullable();
            $table->decimal('financed_amount', 18, 2)->nullable();
            $table->decimal('monthly_payment', 18, 2)->nullable();
            $table->decimal('last_payment_amount', 18, 2)->nullable();
            $table->decimal('outstanding_balance', 18, 2)->nullable();
            $table->decimal('overdue_amount', 18, 2)->nullable();
            $table->integer('installments_number')->nullable();
            $table->integer('outstanding_payments_no')->nullable();
            $table->integer('overdue_payments_no')->nullable();
            $table->integer('overdue_days')->nullable();
            $table->string('purpose_of_credit')->nullable();
            $table->json('guarantors')->nullable();
            $table->json('linked_subjects')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['cid', 'contract_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_loans');
    }
};
