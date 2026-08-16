<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('morbidity_records', function (Blueprint $table) {
            $table->id();
            $table->string('household_id')->nullable()->index();  // householdId (string key)
            $table->string('disease_name')->nullable();
            $table->string('report_year', 4)->nullable();
            $table->string('report_month', 2)->nullable();
            $table->boolean('is_synced')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('morbidity_records');
    }
};