<?php

namespace App\Http\Controllers\JobPost;

use App\Http\Controllers\Controller;
use App\Http\Requests\JobPost\CreateJobPostRequest;
use App\Http\Requests\JobPost\UpdateJobPostRequest;
use App\Models\JobPost;
use App\Services\JobPost\JobPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobPostController extends Controller
{
    public function __construct(private readonly JobPostService $service) {}

    /**
     * POST /api/worker/posts
     * Create a new job post for the authenticated worker.
     */
    public function store(CreateJobPostRequest $request): JsonResponse
    {
        $post = $this->service->create($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Job post created.',
            'data' => ['job_post' => $this->service->format($post->load(['serviceCategory', 'images']))],
        ], 201);
    }

    /**
     * GET /api/worker/posts
     * List the authenticated worker's own posts.
     */
    public function index(Request $request): JsonResponse
    {
        $includeInactive = filter_var($request->query('include_inactive', false), FILTER_VALIDATE_BOOLEAN);
        $posts = $this->service->list($request->user(), $includeInactive);

        return response()->json([
            'success' => true,
            'data' => [
                'posts' => $posts->load('images')->map(fn (JobPost $p) => $this->service->format($p)),
            ],
        ]);
    }

    /**
     * PUT /api/worker/posts/{postId}
     * Update an existing job post owned by the authenticated worker.
     */
    public function update(UpdateJobPostRequest $request, int $postId): JsonResponse
    {
        $post = JobPost::find($postId);

        if (! $post) {
            return response()->json(['success' => false, 'message' => 'Job post not found.'], 404);
        }

        // Ownership check
        if ($post->workerProfile->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $updated = $this->service->update($post, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Job post updated.',
            'data' => ['job_post' => $this->service->format($updated->load('images'))],
        ]);
    }

    /**
     * GET /api/posts/{postId}
     * Public post detail — active posts only.
     */
    public function show(Request $request, int $postId): JsonResponse
    {
        $post = $this->service->findForClient($postId);

        if (! $post) {
            return response()->json(['success' => false, 'message' => 'Post not found.'], 404);
        }

        $profile = $post->workerProfile;
        $worker = $profile?->user;

        return response()->json([
            'success' => true,
            'data' => [
                'job_post' => [
                    'id' => $post->id,
                    'worker_profile_id' => $profile?->id,
                    'service_category_id' => $post->service_category_id,
                    'worker' => [
                        'user_id' => $worker?->id,
                        'name' => $worker?->name,
                        'profile_photo_url' => $worker?->profile_photo_path
                            ? asset('storage/'.$worker->profile_photo_path)
                            : null,
                        'barangay' => $worker?->barangay?->name,
                        'average_rating' => (float) ($profile?->average_rating ?? 0),
                        'total_reviews' => $profile?->total_reviews ?? 0,
                        'trust_tier' => $profile?->trust_tier,
                        'verification_status' => $profile?->verification_status,
                    ],
                    'category' => [
                        'id' => $post->serviceCategory?->id,
                        'name' => $post->serviceCategory?->name,
                        'icon' => $post->serviceCategory?->icon,
                    ],
                    'title' => $post->title,
                    'description' => $post->description,
                    'rate_amount' => (float) $post->rate_amount,
                    'rate_type' => $post->rate_type,
                    'rate_display' => $post->rate_display,
                    'is_available' => $post->is_available,
                    'is_active' => $post->is_active,
                    'images' => $post->images->map(fn ($img) => [
                        'id' => $img->id,
                        'image_url' => asset('storage/'.$img->image_path),
                        'thumbnail_url' => $img->thumbnail_path ? asset('storage/'.$img->thumbnail_path) : null,
                        'display_order' => $img->display_order,
                    ])->values()->all(),
                    'created_at' => $post->created_at?->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * DELETE /api/worker/posts/{postId}
     * Soft-delete (deactivate) a job post owned by the authenticated worker.
     */
    public function destroy(Request $request, int $postId): JsonResponse
    {
        $post = JobPost::find($postId);

        if (! $post) {
            return response()->json(['success' => false, 'message' => 'Job post not found.'], 404);
        }

        if ($post->workerProfile->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $this->service->deactivate($post);

        return response()->json([
            'success' => true,
            'message' => 'Job post deactivated. It is no longer visible to clients.',
        ]);
    }
}
