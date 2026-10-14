<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewVerificationRequest;
use App\Models\WorkerProfile;
use App\Services\Admin\AdminService;
use App\Services\Media\MediaStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function __construct(
        private readonly AdminService $adminService,
    ) {}

    /**
     * GET /api/admin/verifications
     * List all worker profiles with their verification status.
     */
    public function index(Request $request): JsonResponse
    {
        $paginated = $this->adminService->listVerifications(
            $request->query('status'),
        );

        $verifications = collect($paginated->items())->map(
            fn (WorkerProfile $profile): array => $this->serializeVerification($profile),
        );

        return response()->json([
            'success' => true,
            'message' => 'Verifications retrieved.',
            'data'    => [
                'verifications' => $verifications,
                'pagination'    => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ],
        ], 200);
    }

    /**
     * GET /api/admin/verifications/pending  (spec §K2 alias)
     * Shortcut that returns only pending verifications.
     */
    public function pending(Request $request): JsonResponse
    {
        $paginated = $this->adminService->listVerifications('pending');

        $verifications = collect($paginated->items())->map(
            fn (WorkerProfile $profile): array => $this->serializeVerification($profile),
        );

        return response()->json([
            'success' => true,
            'message' => 'Pending verifications retrieved.',
            'data'    => [
                'verifications' => $verifications,
                'pagination'    => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ],
        ]);
    }

    public function show(string $workerProfileId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Verification retrieved.',
            'data'    => [
                'verification' => $this->serializeVerification(
                    $this->adminService->findVerification($workerProfileId),
                ),
            ],
        ]);
    }

    /**
     * POST /api/admin/verifications/{workerProfileId}/review
     * Approve or reject a worker's verification.
     */
    public function review(ReviewVerificationRequest $request, string $workerProfileId): JsonResponse
    {
        $workerProfile = $this->adminService->reviewVerification(
            $request->user(),
            $workerProfileId,
            $request->validated('action'),
            $request->validated('admin_notes'),
        );

        $action  = $request->validated('action');
        $message = $action === 'approve'
            ? 'Worker verification approved.'
            : 'Worker verification rejected.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => [
                'worker_profile_id'   => $workerProfile->id,
                'verification_status' => $workerProfile->verification_status,
                'admin_notes'         => $request->validated('admin_notes'),
            ],
        ], 200);
    }

    private function serializeVerification(WorkerProfile $profile): array
    {
        return [
            'id'                  => $profile->id,
            'worker_profile_id'   => $profile->id,
            'user'                => [
                'id'       => $profile->user?->id,
                'name'     => $profile->user?->name,
                'email'    => $profile->user?->email,
                'barangay' => $profile->user?->barangay?->name,
                'profile_photo_url' => self::profilePhotoUrl($profile->user?->profile_photo_path),
            ],
            'verification_status' => $profile->verification_status,
            'trust_tier'          => $profile->trust_tier,
            'remarks'             => $profile->verification_remarks,
            'updated_at'          => $profile->updated_at?->toIso8601String(),
            'history'             => $profile->verificationHistory->map(fn ($entry) => [
                'id'         => $entry->id,
                'status'     => $entry->status,
                'remarks'    => $entry->remarks,
                'changed_at' => $entry->created_at?->toIso8601String(),
                'changed_by' => $entry->changedBy?->name ?? 'Worker',
            ])->values(),
            'documents'           => $profile->verificationDocuments->map(fn ($doc) => [
                'id'       => $doc->id,
                'type'     => $doc->document_type,
                'status'   => $doc->status,
                'file_url' => app(MediaStorageService::class)->privateUrl($doc->file_path),
            ])->values(),
        ];
    }

    private static function profilePhotoUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
            ? $path
            : app(MediaStorageService::class)->publicUrl($path);
    }
}
