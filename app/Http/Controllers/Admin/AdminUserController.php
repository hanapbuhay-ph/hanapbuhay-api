<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Services\Admin\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly AdminService $adminService,
    ) {}

    /**
     * GET /api/admin/users
     * List all users with optional role, is_active, and search filters.
     */
    public function index(Request $request): JsonResponse
    {
        $paginated = $this->adminService->listUsers(
            $request->query('role'),
            $request->query('is_active'),
            $request->query('search'),
        );

        $users = collect($paginated->items())->map(function ($user): array {
            return [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'role'       => $user->role,
                'barangay'   => $user->barangay?->name,
                'is_active'  => $user->is_active,
                'created_at' => $user->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved.',
            'data'    => [
                'users'      => $users,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ],
        ], 200);
    }

    /**
     * GET /api/admin/users/{id}
     * View a single user's full detail, including worker_profile if applicable.
     */
    public function show(int $id): JsonResponse
    {
        $user = $this->adminService->getUser($id);

        $data = [
            'id'                => $user->id,
            'name'              => $user->name,
            'email'             => $user->email,
            'role'              => $user->role,
            'mobile_number'     => $user->mobile_number,
            'profile_photo_path'=> $user->profile_photo_path,
            'barangay_id'       => $user->barangay_id,
            'is_active'         => $user->is_active,
            'is_google_account' => $user->is_google_account,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'created_at'        => $user->created_at?->toIso8601String(),
            'updated_at'        => $user->updated_at?->toIso8601String(),
        ];

        if ($user->role === 'worker' && $user->workerProfile) {
            $data['worker_profile'] = [
                'verification_status' => $user->workerProfile->verification_status,
                'average_rating'      => $user->workerProfile->average_rating,
                'total_reviews'       => $user->workerProfile->total_reviews,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'User retrieved.',
            'data'    => $data,
        ], 200);
    }

    /**
     * PATCH /api/admin/users/{id}/toggle-active
     * Toggle a user's is_active flag. Admin cannot deactivate themselves.
     */
    public function toggleActive(Request $request, int $id): JsonResponse
    {
        try {
            $user = $this->adminService->toggleActive($id, $request->user()->id);
        } catch (BusinessRuleException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $message = $user->is_active ? 'User reactivated.' : 'User deactivated.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => [
                'user_id'   => $user->id,
                'is_active' => $user->is_active,
            ],
        ], 200);
    }
}
