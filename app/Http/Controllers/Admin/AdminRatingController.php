<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRatingController extends Controller
{
    public function __construct(private readonly AdminService $adminService) {}

    /**
     * GET /api/admin/ratings
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rated_user_id' => ['sometimes', 'integer', 'min:1'],
        ]);

        $paginator = $this->adminService->listRatings(
            $request->filled('worker_id')  ? $request->integer('worker_id')  : null,
            $request->filled('client_id')  ? $request->integer('client_id')  : null,
            $request->filled('score')      ? $request->integer('score')      : null,
            $request->filled('direction')  ? $request->string('direction')->toString() : null,
            $request->filled('search')     ? $request->string('search')->toString()    : null,
            $validated['rated_user_id'] ?? null,
        );

        $ratings = collect($paginator->items())->map(fn ($r) => [
            'id'           => $r->id,
            'score'        => $r->score,
            'comment'      => $r->comment,
            'rated_by'     => [
                'id'                => $r->ratedByUser?->id,
                'name'              => $r->ratedByUser?->name,
                'profile_photo_url' => self::profilePhotoUrl($r->ratedByUser?->profile_photo_path),
            ],
            'rated_user'   => [
                'id'                => $r->ratedUser?->id,
                'name'              => $r->ratedUser?->name,
                'profile_photo_url' => self::profilePhotoUrl($r->ratedUser?->profile_photo_path),
            ],
            'booking'      => ['id' => $r->booking?->id,     'booking_code' => $r->booking?->booking_code],
            'created_at'   => $r->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ratings retrieved.',
            'data'    => [
                'ratings'    => $ratings,
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
     * GET /api/admin/ratings/users
     */
    public function users(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['sometimes', 'in:client,worker'],
            'search' => ['sometimes', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'sort_by' => ['sometimes', 'in:name,average_rating'],
            'sort_direction' => ['sometimes', 'in:asc,desc'],
        ]);
        $paginator = $this->adminService->listRatingRecipients(
            $validated['role'] ?? null,
            isset($validated['search']) ? trim($validated['search']) : null,
            $validated['sort_by'] ?? 'average_rating',
            $validated['sort_direction'] ?? 'desc',
        );
        $users = collect($paginator->items())->map(fn ($user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'barangay' => $user->barangay?->name,
            'profile_photo_url' => self::profilePhotoUrl($user->profile_photo_path),
            'is_active' => $user->is_active,
            'reviews_received_count' => $user->received_ratings_count,
            'average_rating' => round((float) ($user->received_ratings_avg_score ?? 0), 2),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Review recipients retrieved.',
            'data' => [
                'users' => $users,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    private static function profilePhotoUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
            ? $path
            : app(\App\Services\Media\MediaStorageService::class)->publicUrl($path);
    }

    /**
     * DELETE /api/admin/ratings/{id}
     */
    public function destroy(Request $request, int|string $id): JsonResponse
    {
        $this->adminService->deleteRating($request->user(), $id, $request->input('reason'));

        return response()->json([
            'success' => true,
            'message' => 'Rating deleted and worker average recalculated.',
        ]);
    }
}
