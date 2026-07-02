<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class PhilippineLocationSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Regions
        $regions = json_decode(File::get(database_path('data/regions.json')), true);
        foreach ($regions as $item) {
            DB::table('regions')->updateOrInsert(
                ['regCode' => $item['reg_code']],
                ['regDesc' => $item['name']]
            );
        }

        // 2. Seed Provinces
        $provinces = json_decode(File::get(database_path('data/provinces.json')), true);
        // Map: prov_code => reg_code, needed later for municipalities/barangays
        $provToReg = [];
        foreach ($provinces as $item) {
            $provToReg[$item['prov_code']] = $item['reg_code'];

            DB::table('provinces')->updateOrInsert(
                ['provCode' => $item['prov_code']],
                ['provDesc' => $item['name'], 'regCode' => $item['reg_code']]
            );
        }

        // 3. Seed Municipalities
        // municipalities.json only has: name, prov_code, mun_code (no reg_code)
        $municipalities = json_decode(File::get(database_path('data/municipalities.json')), true);
        // Map: mun_code => prov_code, needed later for barangays
        $munToProv = [];
        foreach ($municipalities as $item) {
            $provCode = $item['prov_code'] ?? substr($item['mun_code'], 0, 4);
            $regCode = $provToReg[$provCode] ?? substr($provCode, 0, 2);

            $munToProv[$item['mun_code']] = $provCode;

            DB::table('municipalities')->updateOrInsert(
                ['citymunCode' => $item['mun_code']],
                [
                    'citymunDesc' => $item['name'],
                    'provCode' => $provCode,
                    'regCode' => $regCode,
                ]
            );
        }

        // 4. Seed Barangays
        // barangays.json only has: name, mun_code (no brgy_code, no prov_code, no reg_code)
        $barangays = json_decode(File::get(database_path('data/barangays.json')), true);
        // Generate a sequential brgy_code per municipality: mun_code + 2-digit sequence
        $seqPerMun = [];
        foreach ($barangays as $item) {
            $munCode = $item['mun_code'];

            $seqPerMun[$munCode] = ($seqPerMun[$munCode] ?? 0) + 1;
            $brgyCode = $munCode . str_pad((string) $seqPerMun[$munCode], 2, '0', STR_PAD_LEFT);

            $provCode = $munToProv[$munCode] ?? substr($munCode, 0, 4);
            $regCode = $provToReg[$provCode] ?? substr($provCode, 0, 2);

            DB::table('barangays')->updateOrInsert(
                ['brgyCode' => $brgyCode],
                [
                    'brgyDesc' => $item['name'],
                    'citymunCode' => $munCode,
                    'provCode' => $provCode,
                    'regCode' => $regCode,
                ]
            );
        }
    }
}