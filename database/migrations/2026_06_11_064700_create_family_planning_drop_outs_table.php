<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Disable foreign key constraints to prevent sequence crashes
        Schema::disableForeignKeyConstraints();

        Schema::create('family_planning_drop_outs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recordId')->constrained('family_planning_records')->onDelete('cascade');
            $table->foreignId('profileId')->constrained('household_profiles')->onDelete('cascade');
            $table->string('dropOutDate')->nullable();
            $table->string('reasonCode')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('isSynced')->default(false);
            $table->boolean('newInsert')->default(true);
            $table->unsignedBigInteger('updatedAt')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('family_planning_drop_outs');
        Schema::enableForeignKeyConstraints();
    }
};