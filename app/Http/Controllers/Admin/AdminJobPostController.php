<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkDeleteJobPostsRequest;
use App\Http\Requests\Admin\DeleteJobPostRequest;
use App\Http\Requests\Admin\StoreAdminJobPostRequest;
use App\Http\Requests\Admin\UpdateAdminJobPostRequest;
use App\Http\Requests\JobPost\UploadJobPostImagesRequest;
use App\Models\JobPost;
use App\Models\JobPostImage;
use App\Services\Admin\AdminService;
use App\Services\JobPost\JobPostImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminJobPostController extends Controller
{
    public function __construct(
        private readonly AdminService $adminService,
        private readonly JobPostImageService $imageService,
    ) {}

    public function store(StoreAdminJobPostRequest $request): JsonResponse
    {
        $post = $this->adminService->createJobPost(
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Job post created.',
            'data' => ['job_post' => $post->load(['serviceCategory', 'images'])],
        ], 201);
    }

    public function update(UpdateAdminJobPostRequest $request, int|string $id): JsonResponse
    {
        $post = $this->adminService->updateJobPost(
            $request->user(),
            (int) $id,
            $request->validated(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Job post updated.',
            'data' => ['job_post' => $post->load(['serviceCategory', 'images'])],
        ]);
    }

    public function uploadImages(UploadJobPostImagesRequest $request, int $id): JsonResponse
    {
        $post = JobPost::withTrashed()->findOrFail($id);
        $existing = $post->images()->count();
        $incoming = count($request->file('images', []));

        if ($existing + $incoming > JobPostImageService::MAX_IMAGES) {
            return response()->json([
                'success' => false,
                'message' => 'A post may have at most '.JobPostImageService::MAX_IMAGES.' images.',
            ], 422);
        }

        $this->imageService->upload($post, $request->file('images'));
        $post->load('images');

        return response()->json([
            'success' => true,
            'data' => ['images' => $this->imageService->formatImages($post->images)],
        ], 201);
    }

    public function deleteImage(int $id, int $imageId): JsonResponse
    {
        $post = JobPost::withTrashed()->findOrFail($id);
        $image = $post->images()->whereKey($imageId)->firstOrFail();
        $this->imageService->delete($image);

        return response()->json(['success' => true, 'message' => 'Image deleted.']);
    }

    /**
     * GET /api/admin/posts
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->adminService->listJobPosts(
            $request->filled('category_id')       ? $request->integer('category_id')       : null,
            $request->filled('worker_profile_id')  ? $request->integer('worker_profile_id') : null,
            $request->filled('barangay_id')         ? $request->integer('barangay_id')        : null,
            $request->query('status'),
            $request->query('verification'),
            $request->query('search'),
        );

        $posts = collect($paginator->items())->map(fn ($p) => [
            'id'              => $p->id,
            'title'           => $p->title,
            'rate_display'    => $p->rate_display,
            'is_active'       => $p->is_active,
            'is_available'    => $p->is_available,
            'deleted_at'      => $p->deleted_at?->toIso8601String(),
            'category'        => ['id' => $p->serviceCategory?->id, 'name' => $p->serviceCategory?->name],
            'worker'          => [
                'id'                   => $p->workerProfile?->user?->id,
                'name'                 => $p->workerProfile?->user?->name,
                'barangay'             => $p->workerProfile?->user?->barangay?->name,
                'verification_status'  => $p->workerProfile?->verification_status,
            ],
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
     * GET /api/admin/posts/{id}
     */
    public function show(int|string $id): JsonResponse
    {
        $post = $this->adminService->getJobPost($id);
        $worker = $post->workerProfile?->user;

        return response()->json([
            'success' => true,
            'message' => 'Job post retrieved.',
            'data'    => [
                'job_post' => [
                    'id'                   => $post->id,
                    'title'                => $post->title,
                    'description'          => $post->description,
                    'rate_amount'          => (float) $post->rate_amount,
                    'rate_type'             => $post->rate_type,
                    'rate_display'          => $post->rate_display,
                    'is_active'             => $post->is_active,
                    'is_available'          => $post->is_available,
                    'deleted_at'            => $post->deleted_at?->toIso8601String(),
                    'created_at'            => $post->created_at?->toIso8601String(),
                    'bookings_count'        => $post->bookings_count,
                    'category'              => [
                        'id'   => $post->serviceCategory?->id,
                        'name' => $post->serviceCategory?->name,
                        'icon' => $post->serviceCategory?->icon,
                    ],
                    'worker' => [
                        'id'                   => $worker?->id,
                        'name'                 => $worker?->name,
                        'email'                => $worker?->email,
                        'mobile_number'        => $worker?->mobile_number,
                        'profile_photo_url'    => $worker?->profile_photo_path
                            ? asset('storage/' . $worker->profile_photo_path)
                            : null,
                        'barangay'             => $worker?->barangay?->name,
                        'verification_status'  => $post->workerProfile?->verification_status,
                        'average_rating'       => (float) ($post->workerProfile?->average_rating ?? 0),
                        'total_reviews'        => $post->workerProfile?->total_reviews ?? 0,
                    ],
                    'images' => $post->images->map(fn ($image) => [
                        'id'             => $image->id,
                        'image_url'      => asset('storage/' . $image->image_path),
                        'thumbnail_url'  => $image->thumbnail_path
                            ? asset('storage/' . $image->thumbnail_path)
                            : null,
                        'display_order'  => $image->display_order,
                    ])->values(),
                ],
            ],
        ]);
    }

    /**
     * DELETE /api/admin/posts/{id}
     * Hard-delete a job post (admin only).
     */
    public function destroy(DeleteJobPostRequest $request, int|string $id): JsonResponse
    {
        $this->adminService->forceDeleteJobPost($request->user(), $id, $request->validated('reason'));

        return response()->json([
            'success' => true,
            'message' => 'Job post permanently deleted.',
        ]);
    }

    public function bulkDestroy(BulkDeleteJobPostsRequest $request): JsonResponse
    {
        $postIds = $request->validated('post_ids');
        $this->adminService->forceDeleteJobPosts(
            $request->user(),
            $postIds,
            $request->validated('reason'),
        );

        return response()->json([
            'success' => true,
            'message' => count($postIds).' job posts permanently deleted.',
            'data' => ['deleted_ids' => $postIds],
        ]);
    }
}
