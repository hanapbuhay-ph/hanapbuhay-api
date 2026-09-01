<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewVerificationRequest;
use App\Services\Admin\AdminService;
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

        $verifications = collect($paginated->items())->map(function ($profile): array {
            return [
                'id'                  => $profile->id,
                'user'                => [
                    'id'       => $profile->user->id,
                    'name'     => $profile->user->name,
                    'email'    => $profile->user->email,
                    'barangay' => $profile->user->barangay?->name,
                ],
                'verification_status' => $profile->verification_status,
                'documents'           => $profile->verificationDocuments->map(fn ($doc) => [
                    'type'      => $doc->document_type,
                    'status'    => $doc->status,
                    'file_path' => $doc->file_path,
                ])->values(),
                'updated_at'          => $profile->updated_at?->toIso8601String(),
            ];
        });

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

        $verifications = collect($paginated->items())->map(function ($profile): array {
            return [
                'id'                  => $profile->id,
                'user'                => [
                    'id'       => $profile->user->id,
                    'name'     => $profile->user->name,
                    'email'    => $profile->user->email,
                    'barangay' => $profile->user->barangay?->name,
                ],
                'verification_status' => $profile->verification_status,
                'documents'           => $profile->verificationDocuments->map(fn ($doc) => [
                    'type'      => $doc->document_type,
                    'status'    => $doc->status,
                    'file_path' => $doc->file_path,
                ])->values(),
                'updated_at'          => $profile->updated_at?->toIso8601String(),
            ];
        });

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

    /**
     * POST /api/admin/verifications/{workerProfileId}/review
     * Approve or reject a worker's verification.
     */
    public function review(ReviewVerificationRequest $request, int $workerProfileId): JsonResponse
    {
        $workerProfile = $this->adminService->reviewVerification(
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
}
