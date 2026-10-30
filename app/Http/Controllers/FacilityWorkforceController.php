<?php

namespace App\Http\Controllers;

use App\Models\FacilityWorkforceData;
use App\Models\SubmitProgramReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Editable "Health Facility and Workforce Data" table of the A1 (annual) report.
 * One record per year + address; saved when the A1 report is submitted.
 */
class FacilityWorkforceController extends Controller
{
    private const EDIT_ROLES = ['BHS', 'Public Health Nurse', 'PHO', 'Administrator'];

    public function show(Request $request): JsonResponse
    {
        $scope = $this->validateScope($request);

        $record = FacilityWorkforceData::where($this->identity($scope))->first();

        return response()->json([
            'success' => true,
            'data' => [
                'rows' => $record?->data ?? (object) [],
                'updated_at' => $record?->updated_at,
            ],
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! in_array($user->role, self::EDIT_ROLES, true)) {
            return response()->json(['success' => false, 'message' => 'You are not allowed to edit this data.'], 403);
        }

        $scope = $this->validateScope($request);
        $validated = $request->validate([
            'rows' => ['present', 'array', 'max:100'],
            'rows.*' => ['array'],
            'rows.*.male' => ['nullable', 'string', 'max:20'],
            'rows.*.female' => ['nullable', 'string', 'max:20'],
            'rows.*.total' => ['nullable', 'string', 'max:20'],
            'rows.*.remarks' => ['nullable', 'string', 'max:500'],
        ]);

        // The annual report is locked once it has been confirmed for this address.
        $locked = SubmitProgramReport::where('user_id', $user->id)
            ->where('form', 'a1')
            ->where('year', $scope['year'])
            ->where('region_code', $scope['region_code'])
            ->where('province_code', $scope['province_code'])
            ->where('municipality_code', $scope['municipality_code'])
            ->where('status', 'confirmed')
            ->get()
            ->contains(fn ($r) => $this->barangayKey($r->barangay_codes ?? []) === $this->barangayKey($scope['barangay_codes']));

        if ($locked) {
            return response()->json([
                'success' => false,
                'message' => 'This annual report has been confirmed and can no longer be edited.',
            ], 422);
        }

        $rows = [];
        foreach ($validated['rows'] as $key => $row) {
            $rows[(string) $key] = [
                'male' => $row['male'] ?? '',
                'female' => $row['female'] ?? '',
                'total' => $row['total'] ?? '',
                'remarks' => $row['remarks'] ?? '',
            ];
        }

        $record = FacilityWorkforceData::updateOrCreate(
            $this->identity($scope),
            [
                'barangay_codes' => $scope['barangay_codes'],
                'data' => $rows,
                'updated_by' => $user->id,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Facility and workforce data saved.',
            'data' => ['rows' => $record->data, 'updated_at' => $record->updated_at],
        ]);
    }

    private function validateScope(Request $request): array
    {
        $data = $request->validate([
            'year' => ['required', 'digits:4'],
            'region_code' => ['nullable', 'string', 'max:20'],
            'province_code' => ['nullable', 'string', 'max:20'],
            'municipality_code' => ['nullable', 'string', 'max:20'],
            'barangay_codes' => ['nullable', 'array'],
            'barangay_codes.*' => ['string', 'max:20'],
        ]);

        return [
            'year' => $data['year'],
            'region_code' => $data['region_code'] ?? '',
            'province_code' => $data['province_code'] ?? '',
            'municipality_code' => $data['municipality_code'] ?? '',
            'barangay_codes' => array_values(array_unique($data['barangay_codes'] ?? [])),
        ];
    }

    private function identity(array $scope): array
    {
        return [
            'year' => $scope['year'],
            'region_code' => $scope['region_code'],
            'province_code' => $scope['province_code'],
            'municipality_code' => $scope['municipality_code'],
            'barangay_key' => $this->barangayKey($scope['barangay_codes']),
        ];
    }

    private function barangayKey(array $codes): string
    {
        $codes = array_map('strval', $codes);
        sort($codes);

        return sha1(implode('|', array_unique($codes)));
    }
}
