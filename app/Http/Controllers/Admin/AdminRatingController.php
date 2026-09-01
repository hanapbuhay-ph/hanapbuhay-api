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
        $paginator = $this->adminService->listRatings(
            $request->filled('worker_id')  ? $request->integer('worker_id')  : null,
            $request->filled('client_id')  ? $request->integer('client_id')  : null,
            $request->filled('score')      ? $request->integer('score')      : null,
            $request->filled('direction')  ? $request->string('direction')->toString() : null,
            $request->filled('search')     ? $request->string('search')->toString()    : null,
        );

        $ratings = collect($paginator->items())->map(fn ($r) => [
            'id'           => $r->id,
            'score'        => $r->score,
            'comment'      => $r->comment,
            'rated_by'     => ['id' => $r->ratedByUser?->id, 'name' => $r->ratedByUser?->name],
            'rated_user'   => ['id' => $r->ratedUser?->id,   'name' => $r->ratedUser?->name],
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
     * DELETE /api/admin/ratings/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->adminService->deleteRating($request->user(), $id);

        return response()->json([
            'success' => true,
            'message' => 'Rating deleted and worker average recalculated.',
        ]);
    }
}
