<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\AdminService;
use App\Services\Auth\AccountDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDeletionController extends Controller
{
    public function __construct(
        private readonly AccountDeletionService $deletionService,
        private readonly AdminService $adminService,
    ) {}

    /**
     * GET /api/admin/deletion-requests
     * List users who have submitted a deletion request.
     */
    public function index(): JsonResponse
    {
        $paginator = $this->deletionService->listPendingDeletions();

        $users = collect($paginator->items())->map(fn (User $u) => [
            'id'                      => $u->id,
            'name'                    => $u->name,
            'email'                   => $u->email,
            'role'                    => $u->role,
            'barangay'                => $u->barangay?->name,
            'deletion_requested_at'   => $u->deletion_requested_at?->toIso8601String(),
            'created_at'              => $u->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Deletion requests retrieved.',
            'data'    => [
                'users'      => $users,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                    'last_page'    => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * POST /api/admin/deletion-requests/{id}/process
     * Anonymise and soft-delete the user. Irreversible.
     */
    public function process(Request $request, int $id): JsonResponse
    {
        $user = User::withTrashed()->find($id);

        if (! $user || $user->deletion_requested_at === null) {
            return response()->json([
                'success' => false,
                'message' => 'Deletion request not found.',
            ], 404);
        }

        if ($user->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'This account has already been deleted.',
            ], 422);
        }

        $this->deletionService->processDeletion($user);

        $this->adminService->audit(
            $request->user(),
            'process_account_deletion',
            'User',
            $id,
            ['processed_at' => now()->toIso8601String()],
        );

        return response()->json([
            'success' => true,
            'message' => 'Account has been deleted and personal data anonymised.',
        ]);
    }
}
