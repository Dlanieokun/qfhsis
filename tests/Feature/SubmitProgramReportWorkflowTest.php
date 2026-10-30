<?php

use App\Models\SubmitProgramReport;
use App\Models\User;

// Absolute URL: APP_URL in .env carries a sub-path (/qfhsis) that would prefix relative ones.
const REPORT_URL = 'http://localhost/api/reports/submit-program-report';

function makeUser(string $role, array $area = []): User
{
    return User::factory()->create(array_merge([
        'role'              => $role,
        'status'            => 'Active',
        'region_code'       => 'R1',
        'province_code'     => 'P1',
        'municipality_code' => 'M1',
    ], $area));
}

function period(array $extra = []): array
{
    return array_merge([
        'form'              => 'm1',
        'month'             => '03',
        'year'              => '2026',
        'region_code'       => 'R1',
        'province_code'     => 'P1',
        'municipality_code' => 'M1',
        'barangay_codes'    => ['B1'],
    ], $extra);
}

test('bhs can submit but cannot review', function () {
    $bhs = makeUser('BHS');

    $this->actingAs($bhs)->postJson(REPORT_URL, period())->assertCreated()
        ->assertJsonPath('data.status', 'submitted');

    $report = SubmitProgramReport::first();
    $this->actingAs($bhs)->postJson(REPORT_URL . "/{$report->id}/review", ['action' => 'confirm'])->assertForbidden();
    $this->actingAs($bhs)->getJson(REPORT_URL . '?review=1')->assertForbidden();
});

test('nurse confirms or rejects bhs submissions in their municipality only', function () {
    $bhs = makeUser('BHS');
    $otherBhs = makeUser('BHS', ['municipality_code' => 'M2']);
    $nurse = makeUser('Public Health Nurse');

    $this->actingAs($bhs)->postJson(REPORT_URL, period());
    $this->actingAs($otherBhs)->postJson(REPORT_URL, period(['municipality_code' => 'M2']));

    $inbox = $this->actingAs($nurse)->getJson(REPORT_URL . '?review=1')->assertOk();
    expect($inbox->json('data.data'))->toHaveCount(1);

    $mine = SubmitProgramReport::where('user_id', $bhs->id)->first();
    $theirs = SubmitProgramReport::where('user_id', $otherBhs->id)->first();

    $this->actingAs($nurse)->postJson(REPORT_URL . "/{$theirs->id}/review", ['action' => 'confirm'])->assertForbidden();

    // Rejecting needs remarks.
    $this->actingAs($nurse)->postJson(REPORT_URL . "/{$mine->id}/review", ['action' => 'reject'])
        ->assertUnprocessable();
    $this->actingAs($nurse)->postJson(REPORT_URL . "/{$mine->id}/review", ['action' => 'reject', 'remarks' => 'Wrong counts'])
        ->assertOk()->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.remarks', 'Wrong counts');

    // The BHS can resubmit after rejection, which clears the review.
    $this->actingAs($bhs)->postJson(REPORT_URL, period())->assertOk()
        ->assertJsonPath('data.status', 'submitted')->assertJsonPath('data.remarks', null);

    $this->actingAs($nurse)->postJson(REPORT_URL . "/{$mine->id}/review", ['action' => 'confirm'])
        ->assertOk()->assertJsonPath('data.status', 'confirmed');

    // Confirmed reports are locked and cannot be reviewed twice.
    $this->actingAs($bhs)->postJson(REPORT_URL, period())->assertUnprocessable();
    $this->actingAs($nurse)->postJson(REPORT_URL . "/{$mine->id}/review", ['action' => 'reject', 'remarks' => 'x'])
        ->assertUnprocessable();
});

test('nurse can only submit the consolidated report once every bhs is confirmed', function () {
    $bhsA = makeUser('BHS');
    $bhsB = makeUser('BHS');
    $nurse = makeUser('Public Health Nurse');

    $this->actingAs($nurse)->postJson(REPORT_URL, period())->assertUnprocessable();

    foreach ([$bhsA, $bhsB] as $bhs) {
        $this->actingAs($bhs)->postJson(REPORT_URL, period())->assertCreated();
    }
    SubmitProgramReport::where('user_id', $bhsA->id)->update(['status' => 'confirmed']);

    $check = $this->actingAs($nurse)->getJson(REPORT_URL . '/readiness?form=m1&month=03&year=2026')->assertOk();
    expect($check->json('data.ready'))->toBeFalse()
        ->and($check->json('data.pending'))->toBe([$bhsB->name]);
    $this->actingAs($nurse)->postJson(REPORT_URL, period())->assertUnprocessable();

    SubmitProgramReport::where('user_id', $bhsB->id)->update(['status' => 'confirmed']);
    $this->actingAs($nurse)->postJson(REPORT_URL, period())->assertCreated();
});

test('pho reviews nurses in their province and doh reviews pho in their region', function () {
    $nurse = makeUser('Public Health Nurse');
    $otherProvinceNurse = makeUser('Public Health Nurse', ['province_code' => 'P2', 'municipality_code' => 'M9']);
    $pho = makeUser('PHO');
    $doh = makeUser('DOH', ['province_code' => null, 'municipality_code' => null]);

    // Nurses are not gated here (no BHS in these areas) so seed their reports directly.
    foreach ([$nurse, $otherProvinceNurse] as $n) {
        SubmitProgramReport::create([
            'user_id' => $n->id, 'form' => 'm1', 'month' => '03', 'year' => '2026',
            'region_code' => 'R1', 'province_code' => $n->province_code,
            'municipality_code' => $n->municipality_code, 'status' => 'submitted',
        ]);
    }

    $inbox = $this->actingAs($pho)->getJson(REPORT_URL . '?review=1')->assertOk();
    expect($inbox->json('data.data'))->toHaveCount(1);

    $report = SubmitProgramReport::where('user_id', $nurse->id)->first();
    $this->actingAs($pho)->postJson(REPORT_URL . "/{$report->id}/review", ['action' => 'confirm'])->assertOk();

    // PHO can now consolidate; the other province's nurse is not theirs, so only 1 required.
    $this->actingAs($pho)->postJson(REPORT_URL, period())->assertCreated();

    $dohInbox = $this->actingAs($doh)->getJson(REPORT_URL . '?review=1')->assertOk();
    expect($dohInbox->json('data.data'))->toHaveCount(1);

    $phoReport = SubmitProgramReport::where('user_id', $pho->id)->first();
    $this->actingAs($doh)->postJson(REPORT_URL . "/{$phoReport->id}/review", ['action' => 'reject', 'remarks' => 'Recheck'])
        ->assertOk()->assertJsonPath('data.status', 'rejected');

    // Nurses and BHS never review PHO reports; the nurse cannot touch it.
    $this->actingAs($nurse)->postJson(REPORT_URL . "/{$phoReport->id}/review", ['action' => 'confirm'])->assertForbidden();
});
