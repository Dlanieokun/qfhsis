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
        Schema::create('submit_reports', function (Blueprint $table) {
            $table->id();
            
            // Foreign key linking to the users table (constrained drops report if user is deleted)
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Report details
            $table->string('month')->nullable();
            $table->string('year')->nullable();
            $table->string('municipality')->nullable();
            $table->string('province')->nullable();
            $table->string('region')->nullable();
            
            // Adds created_at and updated_at columns
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submit_reports');
    }
};