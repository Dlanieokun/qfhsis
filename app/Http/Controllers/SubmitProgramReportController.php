<?php

namespace App\Http\Controllers;

use App\Models\SubmitProgramReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubmitProgramReportController extends Controller
{
    private const PRIVILEGED_ROLES = ['Administrator', 'DOH'];

    /**
     * List submitted reports, optionally filtered by form, year, month,
     * region, province, municipality, barangay. Non-Administrator/DOH
     * users only ever see their own submissions.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = SubmitProgramReport::query()->with('user:id,name,email,role');

        if (!in_array($user?->role, self::PRIVILEGED_ROLES, true)) {
            $query->where('user_id', $user?->id);
        }

        if ($request->filled('form')) {
            $query->where('form', (string) $request->input('form'));
        }
        if ($request->filled('year')) {
            $query->where('year', (string) $request->input('year'));
        }
        if ($request->filled('month')) {
            $query->where('month', str_pad((string) $request->input('month'), 2, '0', STR_PAD_LEFT));
        }
        if ($request->filled('region')) {
            $query->where('region_code', (string) $request->input('region'));
        }
        if ($request->filled('province')) {
            $query->where('province_code', (string) $request->input('province'));
        }
        if ($request->filled('municipality')) {
            $query->where('municipality_code', (string) $request->input('municipality'));
        }
        if ($request->filled('barangay')) {
            $barangays = (array) $request->input('barangay');
            $query->where(function ($q) use ($barangays) {
                foreach ($barangays as $code) {
                    $q->orWhereJsonContains('barangay_codes', $code);
                }
            });
        }

        $reports = $query->latest()->paginate(20);

        return response()->json(['data' => $reports]);
    }

    /**
     * Store a program report submission, or update it if the same user
     * already submitted this form for this month/year.
     */
    public function store(Request $request): JsonResponse
    {
        // Accept "1" as well as "01" for the month.
        if ($request->filled('month')) {
            $request->merge([
                'month' => str_pad((string) $request->input('month'), 2, '0', STR_PAD_LEFT),
            ]);
        }

        $validated = $request->validate([
            'form'              => ['required', 'string', Rule::in(['m1', 'q1', 'm2', 'a1', 'mo'])],
            'month'             => ['required', 'string', 'regex:/^(0[1-9]|1[0-2])$/'],
            'year'              => ['required', 'string', 'regex:/^\d{4}$/'],
            'region_code'       => ['nullable', 'string', 'max:255'],
            'province_code'     => ['nullable', 'string', 'max:255'],
            'municipality_code' => ['nullable', 'string', 'max:255'],
            'barangay_codes'    => ['nullable', 'array'],
            'barangay_codes.*'  => ['string'],
        ]);

        // Match exactly the unique index: user_id + form + month + year.
        $report = SubmitProgramReport::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'form'    => $validated['form'],
                'month'   => $validated['month'],
                'year'    => $validated['year'],
            ],
            [
                'region_code'       => $validated['region_code'] ?? null,
                'province_code'     => $validated['province_code'] ?? null,
                'municipality_code' => $validated['municipality_code'] ?? null,
                'barangay_codes'    => $validated['barangay_codes'] ?? null,
                'status'            => 'submitted', // never taken from the request
            ]
        );

        $report->load('user:id,name,email,role');

        return response()->json(
            ['data' => $report],
            $report->wasRecentlyCreated ? 201 : 200
        );
    }

    /**
     * Show a single submission.
     */
    public function show(Request $request, SubmitProgramReport $submitProgramReport): JsonResponse
    {
        if (!$this->canAccess($request, $submitProgramReport)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $submitProgramReport->load('user:id,name,email,role');

        return response()->json(['data' => $submitProgramReport]);
    }

    /**
     * Delete a submission (e.g. so it can be resubmitted from scratch).
     */
    public function destroy(Request $request, SubmitProgramReport $submitProgramReport): JsonResponse
    {
        if (!$this->canAccess($request, $submitProgramReport)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $submitProgramReport->delete();

        return response()->json(['message' => 'Report deleted.']);
    }

    /**
     * Owner or Administrator/DOH.
     */
    private function canAccess(Request $request, SubmitProgramReport $report): bool
    {
        $user = $request->user();

        return $report->user_id === $user?->id
            || in_array($user?->role, self::PRIVILEGED_ROLES, true);
    }
}