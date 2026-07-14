<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('filariasis_registry_table', function (Blueprint $table) {
            $table->id();
            $table->foreignId('userId')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('profileId')->constrained('household_profiles')->onDelete('cascade');
            $table->string('date_of_registration')->nullable();
            $table->string('family_serial_number')->nullable();
            $table->string('name')->nullable();
            $table->string('address')->nullable();
            $table->string('date_of_birth')->nullable();
            $table->integer('age')->nullable();
            $table->string('age_group')->nullable();
            $table->string('sex')->nullable();
            
            $table->boolean('nbe_performed')->default(false);
            $table->boolean('rdt_performed')->default(false);
            $table->string('date_nbe_rdt')->nullable();
            $table->string('blood_test_result')->nullable();
            
            $table->string('lymphedema_examined_first_time')->nullable();
            $table->boolean('has_lymphedema')->default(false);
            
            $table->string('elephantiasis_examined_first_time')->nullable();
            $table->boolean('has_elephantiasis')->default(false);
            
            $table->string('hydrocele_examined_first_time')->nullable();
            $table->boolean('has_hydrocele')->default(false);
            
            $table->string('albendazole_date_given')->nullable();
            $table->string('dec_date_given')->nullable();
            $table->string('ivermectin_date_given')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('isSynced')->default(false);
            $table->boolean('newInsert')->default(true);
            $table->unsignedBigInteger('updatedAt')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('filariasis_registry_table');
    }
};
