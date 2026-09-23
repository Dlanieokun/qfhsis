<?php

namespace App\Http\Controllers;

use App\Models\SubmitProgramReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SubmitProgramReportController extends Controller
{
    /**
     * List submitted reports, optionally filtered by the query params
     * used elsewhere in the app (form, year, month, region, province,
     * municipality, barangay). Non-Administrator/DOH users only ever
     * see their own submissions.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = SubmitProgramReport::query()->with('user:id,name,email,role');

        if (!in_array($user?->role, ['Administrator', 'DOH'], true)) {
            $query->where('user_id', $user?->id);
        }

        if ($request->filled('form')) {
            $query->where('form', $request->string('form'));
        }
        if ($request->filled('year')) {
            $query->where('year', $request->string('year'));
        }
        if ($request->filled('month')) {
            $query->where('month', $request->string('month'));
        }
        if ($request->filled('region')) {
            $query->where('region_code', $request->string('region'));
        }
        if ($request->filled('province')) {
            $query->where('province_code', $request->string('province'));
        }
        if ($request->filled('municipality')) {
            $query->where('municipality_code', $request->string('municipality'));
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
     * Store (or update, if the same user already submitted this form for
     * this reporting period) a program report submission.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'form' => ['required', Rule::in(['m1', 'q1', 'm2', 'a1', 'mo'])],
            'month' => ['required', 'string', 'size:2'],
            'year' => ['required', 'string', 'size:4'],
            'region_code' => ['nullable', 'string'],
            'province_code' => ['nullable', 'string'],
            'municipality_code' => ['nullable', 'string'],
            'barangay_codes' => ['nullable', 'array'],
            'barangay_codes.*' => ['string'],
        ]);

        // Trust the authenticated session for who's submitting rather than
        // any user_id the client sends.
        $userId = Auth::id();

        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $report = SubmitProgramReport::updateOrCreate(
            [
                'user_id' => $userId,
                'form' => $validated['form'],
                'month' => $validated['month'],
                'year' => $validated['year'],
            ],
            [
                'region_code' => $validated['region_code'] ?? null,
                'province_code' => $validated['province_code'] ?? null,
                'municipality_code' => $validated['municipality_code'] ?? null,
                'barangay_codes' => $validated['barangay_codes'] ?? [],
                'status' => 'submitted',
            ]
        );

        return response()->json([
            'message' => 'Report submitted successfully.',
            'data' => $report,
        ], 201);
    }

    /**
     * Show a single submission.
     */
    public function show(SubmitProgramReport $submitProgramReport): JsonResponse
    {
        $submitProgramReport->load('user:id,name,email,role');

        return response()->json(['data' => $submitProgramReport]);
    }

    /**
     * Delete a submission (e.g. so it can be resubmitted from scratch).
     */
    public function destroy(Request $request, SubmitProgramReport $submitProgramReport): JsonResponse
    {
        $user = $request->user();

        $isOwner = $submitProgramReport->user_id === $user?->id;
        $isPrivileged = in_array($user?->role, ['Administrator', 'DOH'], true);

        if (!$isOwner && !$isPrivileged) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $submitProgramReport->delete();

        return response()->json(['message' => 'Report deleted.']);
    }
}