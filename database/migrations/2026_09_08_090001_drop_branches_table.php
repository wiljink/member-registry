<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Branches are no longer a managed picklist — the branch list is derived on the
 * fly from the distinct `Branch Code` values present in the imported registry data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('branches');
    }

    public function down(): void
    {
        Schema::create('branches', function ($table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
