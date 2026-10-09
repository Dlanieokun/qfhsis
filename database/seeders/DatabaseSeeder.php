<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Location tables must be filled first so the users below can reference real codes.
        $this->call(PhilippineLocationSeeder::class);

        $loc = $this->resolveLocation();

        // Roles/statuses must match the values used by UserManagement.tsx:
        // 'Administrator' | 'Doctor' | 'Public Health Nurse' | 'BHS' | 'BHW' | 'DOH'
        // 'Active' | 'Inactive'

        // Default system administrator
        $this->upsertUser([
            'name'              => 'Ronald Mercado',
            'email'             => 'admin@fhsis.gov.ph',
            'password'          => 'password123',
            'role'              => 'Administrator',
            'status'            => 'Active',
            'assigned_facility' => 'Palo RHU',
        ], $loc, includeBarangay: false);

        // Test account: barangay-level staff
        $this->upsertUser([
            'name'              => 'RHU Staff User',
            'email'             => 'staff@fhsis.gov.ph',
            'password'          => 'password123',
            'role'              => 'BHW',
            'status'            => 'Active',
            'assigned_facility' => 'Barangay Campetic BHS',
        ], $loc, includeBarangay: true);
    }

    /**
     * Insert or update a user by email (safe to re-run) using the query builder,
     * so it doesn't depend on $fillable or array/json casts on the User model.
     */
    private function upsertUser(array $attrs, array $loc, bool $includeBarangay): void
    {
        $now = now();

        $row = [
            'name'              => $attrs['name'],
            'password'          => Hash::make($attrs['password']),
            'role'              => $attrs['role'],
            'status'            => $attrs['status'],
            'assigned_facility' => $attrs['assigned_facility'],
            'contact_number'    => null,
            'email_verified_at' => $now,

            'region'            => $loc['region'],
            'region_code'       => $loc['region_code'],
            'province'          => $loc['province'],
            'province_code'     => $loc['province_code'],
            'municipality'      => $loc['municipality'],
            'municipality_code' => $loc['municipality_code'],

            // JSON columns: encode explicitly
            'barangay'          => $includeBarangay && $loc['barangay']
                ? json_encode([$loc['barangay']]) : null,
            'barangay_codes'    => $includeBarangay && $loc['barangay_code']
                ? json_encode([$loc['barangay_code']]) : null,

            'updated_at'        => $now,
        ];

        $exists = DB::table('users')->where('email', $attrs['email'])->exists();

        if ($exists) {
            DB::table('users')->where('email', $attrs['email'])->update($row);
        } else {
            DB::table('users')->insert($row + [
                'email'      => $attrs['email'],
                'created_at' => $now,
            ]);
        }
    }

    /**
     * Look up Region VIII > Leyte > Palo > Campetic from the seeded location tables.
     * Falls back to nulls if anything isn't found, so seeding never crashes.
     */
    private function resolveLocation(): array
    {
        $empty = [
            'region' => null, 'region_code' => null,
            'province' => null, 'province_code' => null,
            'municipality' => null, 'municipality_code' => null,
            'barangay' => null, 'barangay_code' => null,
        ];

        $region = DB::table('regions')->where('regDesc', 'like', '%REGION VIII%')->first()
            ?? DB::table('regions')->where('regDesc', 'like', '%Eastern Visayas%')->first();
        if (!$region) {
            return $empty;
        }

        // Exact match so "Southern Leyte" isn't picked up
        $province = DB::table('provinces')
            ->where('regCode', $region->regCode)
            ->where('provDesc', 'Leyte')
            ->first();

        $municipality = $province
            ? DB::table('municipalities')
                ->where('provCode', $province->provCode)
                ->where('citymunDesc', 'like', 'Palo%')
                ->first()
            : null;

        $barangay = $municipality
            ? DB::table('barangays')
                ->where('citymunCode', $municipality->citymunCode)
                ->where('brgyDesc', 'like', 'Campetic%')
                ->first()
            : null;

        return [
            'region'            => $region->regDesc,
            'region_code'       => $region->regCode,
            'province'          => $province->provDesc ?? null,
            'province_code'     => $province->provCode ?? null,
            'municipality'      => $municipality->citymunDesc ?? null,
            'municipality_code' => $municipality->citymunCode ?? null,
            'barangay'          => $barangay->brgyDesc ?? null,
            'barangay_code'     => $barangay->brgyCode ?? null,
        ];
    }
}