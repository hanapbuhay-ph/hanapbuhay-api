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
            'data'    => ['job_post' => $this->service->format($post->load('serviceCategory'))],
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
            'data'    => [
                'posts' => $posts->map(fn (JobPost $p) => $this->service->format($p)),
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
            'data'    => ['job_post' => $this->service->format($updated)],
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
