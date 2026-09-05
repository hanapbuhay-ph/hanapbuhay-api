<?php

namespace App\Http\Controllers\JobPost;

use App\Http\Controllers\Controller;
use App\Http\Requests\JobPost\ReorderJobPostImagesRequest;
use App\Http\Requests\JobPost\UploadJobPostImagesRequest;
use App\Models\JobPost;
use App\Models\JobPostImage;
use App\Services\JobPost\JobPostImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobPostImageController extends Controller
{
    public function __construct(private readonly JobPostImageService $service) {}

    /**
     * POST /api/worker/posts/{postId}/images
     */
    public function store(UploadJobPostImagesRequest $request, int $postId): JsonResponse
    {
        $post = JobPost::find($postId);

        if (! $post) {
            return response()->json(['success' => false, 'message' => 'Job post not found.'], 404);
        }

        if ($post->workerProfile->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $existing = $post->images()->count();
        $incoming = count($request->file('images'));

        if ($existing + $incoming > JobPostImageService::MAX_IMAGES) {
            return response()->json([
                'success' => false,
                'message' => 'A post may have at most '.JobPostImageService::MAX_IMAGES.' images.',
            ], 422);
        }

        $created = $this->service->upload($post, $request->file('images'));

        $post->load('images');

        return response()->json([
            'success' => true,
            'data' => [
                'images' => $this->service->formatImages($post->images),
            ],
        ], 201);
    }

    /**
     * DELETE /api/worker/posts/{postId}/images/{imageId}
     */
    public function destroy(Request $request, int $postId, int $imageId): JsonResponse
    {
        $post = JobPost::find($postId);

        if (! $post) {
            return response()->json(['success' => false, 'message' => 'Job post not found.'], 404);
        }

        if ($post->workerProfile->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $image = JobPostImage::where('id', $imageId)
            ->where('job_post_id', $postId)
            ->first();

        if (! $image) {
            return response()->json(['success' => false, 'message' => 'Image not found.'], 404);
        }

        $this->service->delete($image);

        return response()->json(['success' => true, 'message' => 'Image deleted.']);
    }

    /**
     * PUT /api/worker/posts/{postId}/images/order
     */
    public function reorder(ReorderJobPostImagesRequest $request, int $postId): JsonResponse
    {
        $post = JobPost::find($postId);

        if (! $post) {
            return response()->json(['success' => false, 'message' => 'Job post not found.'], 404);
        }

        if ($post->workerProfile->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        try {
            $this->service->reorder($post, $request->input('image_ids'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $post->load('images');

        return response()->json([
            'success' => true,
            'data' => [
                'images' => $this->service->formatImages($post->images),
            ],
        ]);
    }
}
