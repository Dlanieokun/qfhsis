<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A report is now identified by form + period + address, so the same
     * user may submit the same form/period for different addresses.
     * Uniqueness per address is enforced in the controller (barangay_codes
     * is JSON and can't be part of a unique index).
     */
    public function up(): void
    {
        Schema::table('submit_program_report', function (Blueprint $table) {
            // MySQL needs an index on the user_id FK before the unique one can go.
            $table->index('user_id', 'submit_program_report_user_index');
            $table->dropUnique('submit_program_report_unique_period');
            $table->index(['user_id', 'form', 'month', 'year'], 'submit_program_report_period_index');
        });
    }

    public function down(): void
    {
        Schema::table('submit_program_report', function (Blueprint $table) {
            $table->dropIndex('submit_program_report_period_index');
            $table->unique(['user_id', 'form', 'month', 'year'], 'submit_program_report_unique_period');
            $table->dropIndex('submit_program_report_user_index');
        });
    }
};
