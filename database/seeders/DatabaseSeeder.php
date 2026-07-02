<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Default FHSIS Administrator / Municipal Health Officer account
        User::factory()->create([
            'name' => 'Ronald Mercado',
            'email' => 'admin@fhsis.gov.ph',
            'password' => Hash::make('password123'), // Securely hashes the password
            'role' => 'mho',
            'assigned_facility' => 'Palo RHU',
        ]);

        // Optional: Default Rural Health Unit Staff account for testing
        User::factory()->create([
            'name' => 'RHU Staff User',
            'email' => 'staff@fhsis.gov.ph',
            'password' => Hash::make('password123'),
            'role' => 'rhu_staff',
            'assigned_facility' => 'Barangay Campetic BHS',
        ]);
    }
}