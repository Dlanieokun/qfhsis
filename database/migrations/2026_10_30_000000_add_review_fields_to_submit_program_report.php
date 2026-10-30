<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Review workflow: a submission is "submitted" until the reviewer one level
     * up confirms or rejects it. Rejections carry the reviewer's remarks.
     */
    public function up(): void
    {
        Schema::table('submit_program_report', function (Blueprint $table) {
            $table->text('remarks')->nullable()->after('status');
            $table->foreignId('reviewed_by')->nullable()->after('remarks')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('submit_program_report', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['remarks', 'reviewed_at']);
        });
    }
};
