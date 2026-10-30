<?php

use App\Models\FacilityWorkforceData;
use App\Models\SubmitProgramReport;
use App\Models\User;

// Absolute URL: APP_URL in .env carries a sub-path (/qfhsis) that would prefix relative ones.
const FACILITY_URL = 'http://localhost/api/reports/facility-workforce';

function facilityScope(array $extra = []): array
{
    return array_merge([
        'year' => '2026',
        'region_code' => 'R1',
        'province_code' => 'P1',
        'municipality_code' => 'M1',
        'barangay_codes' => ['B1', 'B2'],
    ], $extra);
}

function facilityUser(string $role = 'BHS'): User
{
    return User::factory()->create(['role' => $role, 'status' => 'Active']);
}

test('edited facility and workforce data is saved and loaded back', function () {
    $user = facilityUser();
    $rows = [
        'barangays' => ['male' => '', 'female' => '', 'total' => '12', 'remarks' => 'complete'],
        'nurses' => ['male' => '1', 'female' => '4', 'total' => '5', 'remarks' => ''],
    ];

    $this->actingAs($user)->putJson(FACILITY_URL, facilityScope() + ['rows' => $rows])
        ->assertOk()->assertJsonPath('data.rows.nurses.total', '5');

    // Same address, barangays in a different order -> same record.
    $this->actingAs($user)
        ->getJson(FACILITY_URL . '?' . http_build_query(facilityScope(['barangay_codes' => ['B2', 'B1']])))
        ->assertOk()->assertJsonPath('data.rows.barangays.remarks', 'complete');

    // Saving again updates in place instead of creating a duplicate.
    $rows['nurses']['total'] = '6';
    $this->actingAs($user)->putJson(FACILITY_URL, facilityScope(['barangay_codes' => ['B2', 'B1']]) + ['rows' => $rows])->assertOk();
    expect(FacilityWorkforceData::count())->toBe(1)
        ->and(FacilityWorkforceData::first()->data['nurses']['total'])->toBe('6');

    // A different address or year is a separate record.
    $this->actingAs($user)->getJson(FACILITY_URL . '?' . http_build_query(facilityScope(['year' => '2025'])))
        ->assertOk()->assertJsonPath('data.rows', []);
});

test('roles outside the submit chain cannot edit', function () {
    $this->actingAs(facilityUser('DOH'))->putJson(FACILITY_URL, facilityScope() + ['rows' => []])->assertForbidden();
});

test('a confirmed annual report locks the facility data', function () {
    $user = facilityUser();
    SubmitProgramReport::create([
        'user_id' => $user->id, 'form' => 'a1', 'month' => '12', 'year' => '2026',
        'region_code' => 'R1', 'province_code' => 'P1', 'municipality_code' => 'M1',
        'barangay_codes' => ['B1', 'B2'], 'status' => 'confirmed',
    ]);

    $this->actingAs($user)->putJson(FACILITY_URL, facilityScope(['barangay_codes' => ['B2', 'B1']]) + ['rows' => []])
        ->assertUnprocessable();
});

test('filtering adds up submitted bhs records inside the selected address', function () {
    $rowsFor = fn ($n, $f, $t) => ['nurses' => ['male' => (string) $n, 'female' => (string) $f, 'total' => (string) $t, 'remarks' => '']];

    foreach ([['B1', 1, 2, 3], ['B2', 0, 4, 4]] as [$brgy, $m, $f, $t]) {
        $bhs = facilityUser();
        $this->actingAs($bhs)->putJson(FACILITY_URL, facilityScope(['barangay_codes' => [$brgy]]) + ['rows' => $rowsFor($m, $f, $t)])->assertOk();
        SubmitProgramReport::create([
            'user_id' => $bhs->id, 'form' => 'a1', 'month' => '12', 'year' => '2026',
            'region_code' => 'R1', 'province_code' => 'P1', 'municipality_code' => 'M1',
            'barangay_codes' => [$brgy], 'status' => 'submitted',
        ]);
    }

    // Saved but never submitted -> not counted.
    $draft = facilityUser();
    $this->actingAs($draft)->putJson(FACILITY_URL, facilityScope(['barangay_codes' => ['B9']]) + ['rows' => $rowsFor(9, 9, 18)]);

    $nurse = facilityUser('Public Health Nurse');
    $all = $this->actingAs($nurse)->getJson(FACILITY_URL . '?' . http_build_query(facilityScope(['barangay_codes' => []])))->assertOk();
    expect($all->json('data.submissions'))->toBe(2)
        ->and($all->json('data.totals.nurses'))->toBe(['male' => '1', 'female' => '6', 'total' => '7']);

    $one = $this->actingAs($nurse)->getJson(FACILITY_URL . '?' . http_build_query(facilityScope(['barangay_codes' => ['B1']])))->assertOk();
    expect($one->json('data.submissions'))->toBe(1)->and($one->json('data.totals.nurses.total'))->toBe('3');
});
