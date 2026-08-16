<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infant_deaths', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id')->default(0)->index();
            $table->string('date_of_registration')->nullable();   // mm/dd/yyyy
            $table->string('full_name')->nullable();              // LastName, FirstName, MI
            $table->string('complete_address')->nullable();
            $table->integer('age')->default(0);                   // age in months
            $table->string('sex')->nullable();                    // M / F
            $table->text('remarks')->nullable();
            $table->boolean('synced')->default(false);
            $table->bigInteger('sync_timestamp')->default(0);
            $table->timestamps();

            $table->foreign('profile_id')
                  ->references('id')
                  ->on('household_profiles')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infant_deaths');
    }
};