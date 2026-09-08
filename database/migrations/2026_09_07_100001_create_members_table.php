<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();

            // ---- Key ----
            $table->string('cid')->unique();

            // ---- Source columns (rewritten on every import; read-only in the UI) ----
            $table->string('branch')->nullable();
            $table->date('subject_reference_date')->nullable();
            $table->string('title_code')->nullable();
            $table->string('last_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('suffix')->nullable();
            $table->string('gender', 5)->nullable();            // raw M / F
            $table->date('birth_date')->nullable();
            $table->string('civil_status_code')->nullable();    // raw 00M / 00S / 00W ...
            $table->string('nid')->nullable();
            $table->string('mobile1')->nullable();
            $table->string('email1')->nullable();
            $table->string('spouse_first_name')->nullable();
            $table->string('spouse_last_name')->nullable();
            $table->string('spouse_middle_name')->nullable();
            $table->text('home_address')->nullable();
            $table->string('home_postal_code')->nullable();
            $table->text('business_address_src')->nullable();
            $table->string('business_postal_code')->nullable();

            // ---- Loan roll-up (recomputed from member_loans, never from a file) ----
            $table->unsignedInteger('loan_count')->default(0);
            $table->decimal('total_financed', 18, 2)->default(0);
            $table->decimal('total_outstanding', 18, 2)->default(0);
            $table->decimal('total_overdue_amount', 18, 2)->default(0);
            $table->integer('max_overdue_days')->nullable();
            $table->unsignedInteger('delinquent_loan_count')->default(0);
            $table->date('earliest_loan_open_date')->nullable();
            $table->date('latest_loan_maturity_date')->nullable();

            // ---- Manually supplied (the CDA gaps; never overwritten by import) ----
            $table->string('tin')->nullable();
            $table->date('date_accepted')->nullable();                 // seeded from D/A token if null
            $table->date('bod_res_date_confirmed')->nullable();
            $table->string('bod_res_number')->nullable();
            $table->string('membership_type')->nullable();             // Regular / Associate
            $table->string('membership_kind')->nullable();             // Full-fledged / Non Full-fledged
            $table->string('migs_status')->nullable();                 // MIGS / Non-MIGS
            $table->string('activity_status')->nullable();             // Active / Inactive
            $table->unsignedInteger('initial_shares')->nullable();
            $table->decimal('initial_share_amount', 18, 2)->nullable();
            $table->decimal('initial_paid_up', 18, 2)->nullable();
            $table->text('present_address')->nullable();               // seeded from home_address if null
            $table->text('permanent_address')->nullable();
            $table->text('business_address')->nullable();
            $table->string('sex_assigned_at_birth')->nullable();       // seeded from gender if null
            $table->string('gender_identity')->nullable();             // seeded from gender if null
            $table->string('civil_status')->nullable();                // seeded from civil_status_code if null
            $table->string('education_attainment')->nullable();
            $table->string('occupation_category')->nullable();         // Government / Private / Self-employed / Unemployed
            $table->string('actual_occupation')->nullable();
            $table->string('occupation_status')->nullable();
            $table->string('industry')->nullable();
            $table->unsignedInteger('number_of_dependents')->nullable();
            $table->string('religion')->nullable();
            $table->decimal('annual_income', 18, 2)->nullable();
            $table->string('termination_bod_res')->nullable();
            $table->date('termination_date')->nullable();
            $table->string('ethnicity')->nullable();
            $table->boolean('is_pwd')->nullable();
            $table->string('disability')->nullable();
            $table->string('contact_number')->nullable();              // seeded from mobile1 if null
            $table->string('email_address')->nullable();               // seeded from email1 if null
            $table->decimal('share_capital_balance', 18, 2)->nullable();
            $table->date('share_capital_as_of')->nullable();
            $table->decimal('savings_balance', 18, 2)->nullable();
            $table->date('savings_as_of')->nullable();

            // ---- Meta ----
            $table->string('completion_status')->default('pending');   // pending / in_progress / complete
            $table->text('notes')->nullable();
            $table->timestamp('last_imported_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('last_name');
            $table->index('completion_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
