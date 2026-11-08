<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class AppVersionController extends Controller
{
    /**
     * The latest APK always lives at this public path so the existing
     * download link (APK_PATH in app-sidebar.tsx) keeps pointing at it.
     */
    private const APK_RELATIVE_PATH = 'downloads/qfhsis.apk';

    /**
     * POST /api/app-version/upload   (auth:sanctum, administrators only)
     *
     * multipart/form-data:
     *   version_name  string, e.g. "1.0.4" (must be new)
     *   apk           the .apk file (max 40 MB — matches php.ini upload_max_filesize)
     */
    public function upload(Request $request): JsonResponse
    {
        if (! $request->user()?->isAdministrator()) {
            return response()->json(['message' => 'Only administrators can upload app versions.'], 403);
        }

        try {
            $version = $this->storeUpload($request);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'APK uploaded successfully.',
            'data'    => $this->present($version),
        ], 201);
    }

    /**
     * GET /api/app-version/latest   (public)
     *
     * Optional query param:
     *   current  the version installed on the device, e.g. "1.0.3".
     *            When given, the response includes "update_available".
     */
    public function latest(Request $request): JsonResponse
    {
        $version = AppVersion::latest('id')->first();

        if (! $version) {
            return response()->json(['message' => 'No app version has been uploaded yet.', 'data' => null], 404);
        }

        $data = $this->present($version);

        if ($request->filled('current')) {
            $data['current_version']  = (string) $request->query('current');
            $data['update_available'] = version_compare($version->version_name, $data['current_version'], '>');
        }

        return response()->json(['data' => $data]);
    }

    /**
     * GET /fhsis/app-version   (web session, administrators only)
     */
    public function page(Request $request): Response
    {
        abort_unless($request->user()?->isAdministrator(), 403, 'Only administrators can manage app versions.');

        $versions = AppVersion::query()
            ->leftJoin('users', 'users.id', '=', 'app_versions.uploaded_by')
            ->orderByDesc('app_versions.id')
            ->get(['app_versions.*', 'users.name as uploader_name'])
            ->map(fn (AppVersion $v) => $this->present($v) + [
                'id'            => $v->id,
                'uploaded_by'   => $v->uploader_name,
            ]);

        return Inertia::render('AppVersionUpload', [
            'versions' => $versions,
        ]);
    }

    /**
     * POST /fhsis/app-version   (web session, administrators only)
     */
    public function uploadFromWeb(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdministrator(), 403, 'Only administrators can manage app versions.');

        try {
            $version = $this->storeUpload($request);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Version {$version->version_name} uploaded successfully.");
    }

    /**
     * Validates the request, swaps in the new APK and records the version.
     *
     * @throws RuntimeException when the file cannot be written
     */
    private function storeUpload(Request $request): AppVersion
    {
        $validated = $request->validate([
            'version_name' => ['required', 'string', 'max:50', 'regex:/^[0-9A-Za-z.\-_+]+$/', 'unique:app_versions,version_name'],
            'apk'          => ['required', 'file', 'extensions:apk', 'max:40960'],
        ], [
            'apk.extensions' => 'The file must be an .apk file.',
            'apk.max'        => 'The APK must not be larger than 40 MB.',
            'version_name.unique' => 'This version has already been uploaded.',
        ]);

        $file      = $validated['apk'];
        $directory = public_path(dirname(self::APK_RELATIVE_PATH));
        $target    = public_path(self::APK_RELATIVE_PATH);
        $tempPath  = $directory . DIRECTORY_SEPARATOR . basename(self::APK_RELATIVE_PATH) . '.uploading';

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $checksum = hash_file('sha256', $file->getRealPath());
        $size     = $file->getSize();

        // Write to a temp file first, then swap it in, so a download in
        // progress never sees a half-written APK.
        $file->move($directory, basename($tempPath));
        if (! rename($tempPath, $target)) {
            @unlink($tempPath);

            throw new RuntimeException('Failed to save the APK file.');
        }

        return AppVersion::create([
            'version_name' => $validated['version_name'],
            'file_path'    => self::APK_RELATIVE_PATH,
            'file_size'    => $size,
            'checksum'     => $checksum,
            'uploaded_by'  => $request->user()->id,
        ]);
    }

    private function present(AppVersion $version): array
    {
        return [
            'version_name' => $version->version_name,
            'download_url' => $version->downloadUrl(),
            'file_size'    => $version->file_size,
            'checksum'     => $version->checksum,
            'uploaded_at'  => $version->created_at?->toIso8601String(),
        ];
    }
}
