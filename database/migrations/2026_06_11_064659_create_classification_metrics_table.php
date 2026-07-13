<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prevent MySQL constraint checks from throwing 1824 errors
        Schema::disableForeignKeyConstraints();

        Schema::create('classification_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('household_profiles')->onDelete('cascade');
            $table->string('q1_age')->nullable();
            $table->string('q1_class')->nullable();
            $table->string('q2_age')->nullable();
            $table->string('q2_class')->nullable();
            $table->string('q3_age')->nullable();
            $table->string('q3_class')->nullable();
            $table->string('q4_age')->nullable();
            $table->string('q4_class')->nullable();
            $table->boolean('isSynced')->default(false);
            $table->unsignedBigInteger('updatedAt')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('classification_metrics');
        Schema::enableForeignKeyConstraints();
    }
};