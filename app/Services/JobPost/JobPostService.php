<?php

namespace App\Services\JobPost;

use App\Models\JobPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class JobPostService
{
    /**
     * Create a new job post for a worker.
     * Business rule: one post per category per worker — the previous post in
     * that category is soft-deleted before the new one is inserted.
     */
    public function create(User $worker, array $data): JobPost
    {
        $profile = $worker->workerProfile;

        // Soft-delete any existing active post for this category
        JobPost::where('worker_profile_id', $profile->id)
            ->where('service_category_id', $data['service_category_id'])
            ->delete();

        return JobPost::create([
            'worker_profile_id' => $profile->id,
            'service_category_id' => $data['service_category_id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'rate_amount' => $data['rate_amount'],
            'rate_type' => $data['rate_type'],
            'is_available' => $data['is_available'] ?? true,
            'is_active' => true,
        ]);
    }

    /**
     * Return all job posts for a worker (active by default).
     */
    public function list(User $worker, bool $includeInactive = false): Collection
    {
        $profile = $worker->workerProfile;

        $query = JobPost::with(['serviceCategory', 'images'])
            ->where('worker_profile_id', $profile->id);

        if (! $includeInactive) {
            $query->where('is_active', true);
        }

        return $query->latest()->get();
    }

    /**
     * Update an existing job post — only the owning worker may do this.
     */
    public function update(JobPost $post, array $data): JobPost
    {
        $post->update($data);

        return $post->fresh(['serviceCategory']);
    }

    /**
     * Deactivate (soft-delete) a job post.
     */
    public function deactivate(JobPost $post): void
    {
        $post->delete(); // soft delete via SoftDeletes trait
    }

    /**
     * Format a single post for API responses.
     */
    public function format(JobPost $post): array
    {
        $images = $post->relationLoaded('images') ? $post->images : $post->images;

        return [
            'id' => $post->id,
            'category' => [
                'id' => $post->serviceCategory->id,
                'name' => $post->serviceCategory->name,
            ],
            'title' => $post->title,
            'description' => $post->description,
            'rate_amount' => (float) $post->rate_amount,
            'rate_type' => $post->rate_type,
            'rate_display' => $post->rate_display,
            'is_available' => $post->is_available,
            'is_active' => $post->is_active,
            'images' => $images->map(fn ($img) => [
                'id' => $img->id,
                'image_url' => asset('storage/'.$img->image_path),
                'thumbnail_url' => $img->thumbnail_path ? asset('storage/'.$img->thumbnail_path) : null,
                'display_order' => $img->display_order,
            ])->values()->all(),
            'created_at' => $post->created_at?->toIso8601String(),
        ];
    }

    /**
     * Return a single active post visible to clients.
     */
    public function findForClient(int $postId): ?JobPost
    {
        return JobPost::with(['serviceCategory', 'workerProfile.user.barangay', 'images'])
            ->where('id', $postId)
            ->where('is_active', true)
            ->where('is_available', true)
            ->first();
    }
}
