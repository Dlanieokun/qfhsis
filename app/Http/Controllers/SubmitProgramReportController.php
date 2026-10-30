<?php

namespace App\Http\Controllers;

use App\Models\SubmitProgramReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Report submission + review workflow.
 *
 *   BHS  --submits-->  Public Health Nurse  --submits-->  PHO  --submits-->  DOH
 *        <-confirms/rejects-               <-confirms/rejects-        <-confirms/rejects-
 *
 * - BHS can only submit.
 * - A Public Health Nurse reviews BHS submissions in their municipality, and can
 *   submit the consolidated report once every BHS submission is confirmed.
 * - The PHO reviews Public Health Nurse submissions in their province, and can
 *   submit the consolidated report once every nurse submission is confirmed.
 * - DOH reviews PHO submissions in their region.
 * - Administrator sees everything and can submit, but is outside the review chain.
 */
class SubmitProgramReportController extends Controller
{
    private const PRIVILEGED_ROLES = ['Administrator', 'DOH'];

    // Roles that may submit a report.
    private const SUBMITTER_ROLES = ['BHS', 'Public Health Nurse', 'PHO', 'Administrator'];

    // Submitter role => the role that reviews its submissions.
    private const REVIEWER_FOR = [
        'BHS'                 => 'Public Health Nurse',
        'Public Health Nurse' => 'PHO',
        'PHO'                 => 'DOH',
    ];

    // Reviewer role => the location column that scopes what they review.
    private const SCOPE_FIELD = [
        'Public Health Nurse' => 'municipality_code',
        'PHO'                 => 'province_code',
        'DOH'                 => 'region_code',
    ];

    /**
     * List submitted reports, optionally filtered by form, year, month, status,
     * region, province, municipality, barangay.
     *
     * - Default: the user's own submissions (Administrator/DOH see all, unless mine=1).
     * - review=1: the submissions the user is responsible for reviewing.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($request->boolean('review')) {
            if (!isset(self::SCOPE_FIELD[$user?->role])) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
            $query = $this->reviewableQuery($user);
        } else {
            $query = SubmitProgramReport::query();

            if ($request->boolean('mine') || !in_array($user?->role, self::PRIVILEGED_ROLES, true)) {
                $query->where('user_id', $user?->id);
            }
        }

        $query->with(['user:id,name,email,role', 'reviewer:id,name,role']);

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
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
     * already submitted this form for this month/year/address.
     *
     * The location (region/province/municipality/barangays) is whatever the
     * user had selected in the filter when they clicked Submit.
     *
     * A rejected submission can be resubmitted (it goes back to "submitted" and
     * the review is cleared); a confirmed one is locked.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user?->role, self::SUBMITTER_ROLES, true)) {
            return response()->json(['message' => 'Your role cannot submit reports.'], 403);
        }

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

        // A consolidated report can only go up once everything below it is confirmed.
        $readiness = $this->readiness($user, $validated['form'], $validated['month'], $validated['year']);
        if ($readiness['applies'] && !$readiness['ready']) {
            return response()->json([
                'message' => $readiness['message'],
                'data'    => $readiness,
            ], 422);
        }

        // A report is identified by user + form + month + year + address.
        $barangays = $this->normalizeCodes($validated['barangay_codes'] ?? null);

        $report = SubmitProgramReport::query()
            ->where('user_id', $user->id)
            ->where('form', $validated['form'])
            ->where('month', $validated['month'])
            ->where('year', $validated['year'])
            ->where('region_code', $validated['region_code'] ?? null)
            ->where('province_code', $validated['province_code'] ?? null)
            ->where('municipality_code', $validated['municipality_code'] ?? null)
            ->get()
            ->first(fn ($r) => $this->normalizeCodes($r->barangay_codes) === $barangays);

        if ($report && $report->status === 'confirmed') {
            return response()->json(['message' => 'This report has already been confirmed.'], 422);
        }

        $report ??= new SubmitProgramReport([
            'user_id'           => $user->id,
            'form'              => $validated['form'],
            'month'             => $validated['month'],
            'year'              => $validated['year'],
            'region_code'       => $validated['region_code'] ?? null,
            'province_code'     => $validated['province_code'] ?? null,
            'municipality_code' => $validated['municipality_code'] ?? null,
            'barangay_codes'    => $validated['barangay_codes'] ?? null,
        ]);

        // (Re)submitting always puts the report back in the reviewer's queue.
        $report->status      = 'submitted';
        $report->remarks     = null;
        $report->reviewed_by = null;
        $report->reviewed_at = null;
        $report->save();

        $report->load(['user:id,name,email,role', 'reviewer:id,name,role']);

        return response()->json(
            ['data' => $report],
            $report->wasRecentlyCreated ? 201 : 200
        );
    }

    /**
     * Confirm or reject a submission from the level below. Rejecting needs remarks.
     */
    public function review(Request $request, SubmitProgramReport $submitProgramReport): JsonResponse
    {
        $user = $request->user();

        if (!isset(self::SCOPE_FIELD[$user?->role])
            || !$this->reviewableQuery($user)->whereKey($submitProgramReport->id)->exists()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'action'  => ['required', Rule::in(['confirm', 'reject'])],
            'remarks' => [
                Rule::requiredIf($request->input('action') === 'reject'),
                'nullable', 'string', 'max:1000',
            ],
        ]);

        if ($submitProgramReport->status !== 'submitted') {
            return response()->json(['message' => 'This submission has already been reviewed.'], 422);
        }

        $submitProgramReport->status      = $validated['action'] === 'confirm' ? 'confirmed' : 'rejected';
        $submitProgramReport->remarks     = $validated['remarks'] ?? null;
        $submitProgramReport->reviewed_by = $user->id;
        $submitProgramReport->reviewed_at = now();
        $submitProgramReport->save();

        $submitProgramReport->load(['user:id,name,email,role', 'reviewer:id,name,role']);

        return response()->json(['data' => $submitProgramReport]);
    }

    /**
     * Whether the current user can submit the consolidated report for this
     * form + period yet (applies to Public Health Nurse and PHO only).
     */
    public function readinessCheck(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'form'  => ['required', 'string', Rule::in(['m1', 'q1', 'm2', 'a1', 'mo'])],
            'month' => ['required', 'string'],
            'year'  => ['required', 'string'],
        ]);

        $month = str_pad($validated['month'], 2, '0', STR_PAD_LEFT);

        return response()->json([
            'data' => $this->readiness($request->user(), $validated['form'], $month, $validated['year']),
        ]);
    }

    /**
     * Show a single submission.
     */
    public function show(Request $request, SubmitProgramReport $submitProgramReport): JsonResponse
    {
        if (!$this->canAccess($request, $submitProgramReport)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $submitProgramReport->load(['user:id,name,email,role', 'reviewer:id,name,role']);

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
     * Submissions a reviewer is responsible for: reports from the level below,
     * located inside the reviewer's own municipality / province / region.
     */
    private function reviewableQuery(User $user): Builder
    {
        $field = self::SCOPE_FIELD[$user->role];
        $submitterRole = array_search($user->role, self::REVIEWER_FOR, true);

        $query = SubmitProgramReport::query()
            ->whereHas('user', fn ($q) => $q->where('role', $submitterRole));

        // A reviewer without a location on their profile has nothing in scope.
        return $user->{$field}
            ? $query->where($field, $user->{$field})
            : $query->whereRaw('1 = 0');
    }

    /**
     * For a Public Health Nurse (BHS below) or PHO (nurses below): is every active
     * user one level down, within the user's area, confirmed for this form/period?
     *
     * @return array{applies: bool, ready: bool, required: int, confirmed: int, pending: string[], message: string}
     */
    private function readiness(?User $user, string $form, string $month, string $year): array
    {
        $role = $user?->role;
        $lowerRole = array_search($role, self::REVIEWER_FOR, true);

        // Only reviewer-level submitters (PHN, PHO) consolidate lower-level reports.
        if (!in_array($role, ['Public Health Nurse', 'PHO'], true) || $lowerRole === false) {
            return ['applies' => false, 'ready' => true, 'required' => 0, 'confirmed' => 0, 'pending' => [], 'message' => ''];
        }

        $field = self::SCOPE_FIELD[$role];
        $lowerLabel = $lowerRole === 'BHS' ? 'BHS' : $lowerRole;

        $lowerUsers = $user->{$field}
            ? User::query()
                ->where('role', $lowerRole)
                ->where('status', 'Active')
                ->where($field, $user->{$field})
                ->get(['id', 'name'])
            : collect();

        $confirmedIds = $lowerUsers->isEmpty()
            ? collect()
            : SubmitProgramReport::query()
                ->whereIn('user_id', $lowerUsers->pluck('id'))
                ->where('form', $form)
                ->where('month', $month)
                ->where('year', $year)
                ->where('status', 'confirmed')
                ->pluck('user_id')
                ->unique();

        $pending = $lowerUsers->whereNotIn('id', $confirmedIds)->pluck('name')->values()->all();
        $ready = $lowerUsers->isNotEmpty() && $pending === [];

        $message = match (true) {
            $lowerUsers->isEmpty() => "No active {$lowerLabel} accounts were found in your area to consolidate.",
            !$ready                => "Every {$lowerLabel} submission must be confirmed first. Still waiting on: " . implode(', ', $pending) . '.',
            default                => '',
        };

        return [
            'applies'   => true,
            'ready'     => $ready,
            'required'  => $lowerUsers->count(),
            'confirmed' => $lowerUsers->count() - count($pending),
            'pending'   => $pending,
            'message'   => $message,
        ];
    }

    /**
     * Sorted, de-duplicated string list so barangay sets compare regardless of order.
     */
    private function normalizeCodes(?array $codes): array
    {
        $codes = array_values(array_unique(array_map('strval', $codes ?? [])));
        sort($codes);

        return $codes;
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
