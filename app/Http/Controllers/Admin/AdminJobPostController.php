<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminJobPostController extends Controller
{
    public function __construct(private readonly AdminService $adminService) {}

    /**
     * GET /api/admin/posts
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->adminService->listJobPosts(
            $request->filled('category_id')       ? $request->integer('category_id')       : null,
            $request->filled('worker_profile_id')  ? $request->integer('worker_profile_id') : null,
        );

        $posts = collect($paginator->items())->map(fn ($p) => [
            'id'              => $p->id,
            'title'           => $p->title,
            'rate_display'    => $p->rate_display,
            'is_active'       => $p->is_active,
            'is_available'    => $p->is_available,
            'deleted_at'      => $p->deleted_at?->toIso8601String(),
            'category'        => ['id' => $p->serviceCategory?->id, 'name' => $p->serviceCategory?->name],
            'worker'          => ['id' => $p->workerProfile?->user?->id, 'name' => $p->workerProfile?->user?->name],
            'created_at'      => $p->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Job posts retrieved.',
            'data'    => [
                'posts'      => $posts,
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
     * DELETE /api/admin/posts/{id}
     * Hard-delete a job post (admin only).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->adminService->forceDeleteJobPost($request->user(), $id);

        return response()->json([
            'success' => true,
            'message' => 'Job post permanently deleted.',
        ]);
    }
}
