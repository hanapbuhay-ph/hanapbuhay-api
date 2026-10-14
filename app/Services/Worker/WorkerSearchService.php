<?php

namespace App\Services\Worker;

use App\Exceptions\BusinessRuleException;
use App\Helpers\DistanceHelper;
use App\Models\Booking;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\WorkerProfile;
use Illuminate\Http\Request;

class WorkerSearchService
{
    public function getWorkersByCategory(User $authUser, int $categoryId, Request $request): array
    {
        $category = ServiceCategory::find($categoryId);

        if (! $category || ! $category->is_active) {
            throw new BusinessRuleException('Category not found.', 404);
        }

        $query = WorkerProfile::query()
            ->where(function ($q) {
                $q->whereNull('trust_tier')
                    ->orWhereNotIn('trust_tier', ['flagged', 'revoked']);
            })
            ->whereHas('user', fn ($q) => $q->where('is_active', true))
            ->with([
                'user.barangay',
                'serviceCategories',
                'jobPosts' => fn ($q) => $q->where('service_category_id', $categoryId)->where('is_active', true),
                'jobPosts.images',
                'jobPosts' => fn ($query) => $query
                    ->where('is_active', true)
                    ->where('is_available', true)
                    ->with(['serviceCategory', 'images']),
            ])
            ->whereHas('serviceCategories', fn ($q) => $q->where('service_categories.id', $categoryId));

        if ($request->filled('barangay_id')) {
            $query->whereHas('user', fn ($q) => $q->where('barangay_id', $request->integer('barangay_id')));
        }

        if ($request->boolean('verified_only')) {
            $query->whereNotNull('trust_tier');
        }

        if ($request->boolean('available_only')) {
            $query->where('availability_status', 'available');
        }

        $workers = $query->get();

        $authBarangay = $authUser->relationLoaded('barangay')
            ? $authUser->barangay
            : $authUser->load('barangay')->barangay;

        $formatted = $workers->map(function ($profile) use ($authBarangay) {
            $summary = $this->formatWorkerSummary($profile, $authBarangay);
            $jobPost = $profile->jobPosts->first();

            $summary['job_post'] = $jobPost ? [
                'id' => $jobPost->id,
                'category' => [
                    'id' => $jobPost->serviceCategory?->id,
                    'name' => $jobPost->serviceCategory?->name,
                ],
                'title' => $jobPost->title,
                'description' => $jobPost->description,
                'rate_amount' => (float) $jobPost->rate_amount,
                'rate_type' => $jobPost->rate_type,
                'rate_display' => $jobPost->rate_display,
                'is_available' => $jobPost->is_available,
                'images' => $jobPost->images->map(fn ($img) => [
                    'id' => $img->id,
                    'image_url' => app(\App\Services\Media\MediaStorageService::class)->publicUrl($img->image_path),
                    'thumbnail_url' => $img->thumbnail_path
                        ? app(\App\Services\Media\MediaStorageService::class)->publicUrl($img->thumbnail_path)
                        : null,
                    'display_order' => $img->display_order,
                ])->values()->all(),
            ] : null;

            return $summary;
        })->values()->all();

        return [
            'category' => ['id' => $category->id, 'name' => $category->name],
            'workers' => $formatted,
            'total' => count($formatted),
        ];
    }

    public function getWorkers(User $authUser, Request $request): array
    {
        $query = WorkerProfile::query()
            ->where('verification_status', 'approved')
            ->with(['user.barangay', 'serviceCategories']);

        if ($request->filled('barangay_id')) {
            $query->whereHas('user', fn ($q) => $q->where('barangay_id', $request->integer('barangay_id')));
        }

        if ($request->filled('category_id')) {
            $query->whereHas('serviceCategories', fn ($q) => $q->where('service_categories.id', $request->integer('category_id')));
        }

        if ($request->filled('min_rating')) {
            $query->where('average_rating', '>=', $request->float('min_rating'));
        }

        if ($request->boolean('verified_only')) {
            $query->whereNotNull('trust_tier');
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('serviceCategories', fn ($categoryQuery) => $categoryQuery->where('service_categories.name', 'like', "%{$search}%"));
            });
        }

        $workers = $query->get();

        $authBarangay = $authUser->relationLoaded('barangay')
            ? $authUser->barangay
            : $authUser->load('barangay')->barangay;

        return [
            'workers' => $workers->map(fn ($profile) => $this->formatWorkerSummary($profile, $authBarangay))->values()->all(),
            'total' => $workers->count(),
        ];
    }

    public function getWorker(User $authUser, int|string $workerProfileId): array
    {
        $profileQuery = WorkerProfile::with([
            'user.barangay',
            'serviceCategories',
            'portfolioPhotos',
            'verificationDocuments',
            'jobPosts' => fn ($query) => $query
                ->where('is_active', true)
                ->where('is_available', true)
                ->with(['serviceCategory', 'images']),
            'ratings' => fn ($q) => $q->with('ratedByUser')->latest(),
        ]);

        $profile = $profileQuery->whereKey($workerProfileId)->first()
            ?? WorkerProfile::with([
                'user.barangay',
                'serviceCategories',
                'portfolioPhotos',
                'verificationDocuments',
                'jobPosts' => fn ($query) => $query
                    ->where('is_active', true)
                    ->where('is_available', true)
                    ->with(['serviceCategory', 'images']),
                'ratings' => fn ($q) => $q->with('ratedByUser')->latest(),
            ])->where('user_id', $workerProfileId)->first();

        if ($profile === null) {
            throw new BusinessRuleException('Worker not found.', 404);
        }

        $authBarangay = $authUser->relationLoaded('barangay')
            ? $authUser->barangay
            : $authUser->load('barangay')->barangay;

        return $this->formatWorkerDetail($profile, $authBarangay);
    }

    public function getAuthenticatedWorker(User $authUser): array
    {
        $workerProfileId = $authUser->workerProfile()->value('id');
        if ($workerProfileId === null) {
            throw new BusinessRuleException('Worker profile not found.', 404);
        }

        return $this->getWorker($authUser, $workerProfileId);
    }

    private function resolveDistance(?object $authBarangay, ?object $workerBarangay): array
    {
        if ($authBarangay === null || $workerBarangay === null) {
            return ['distance_km' => null, 'distance_label' => 'Distance unavailable'];
        }

        $km = DistanceHelper::haversine(
            (float) $authBarangay->latitude,
            (float) $authBarangay->longitude,
            (float) $workerBarangay->latitude,
            (float) $workerBarangay->longitude,
        );

        return ['distance_km' => $km, 'distance_label' => "~{$km} km"];
    }

    private function getCompletedJobsCount(WorkerProfile $profile): int
    {
        $userId = $profile->user_id;

        if ($userId === null) {
            return (int) ($profile->completed_jobs ?? 0);
        }

        $count = Booking::query()
            ->where('worker_id', $userId)
            ->where('status', 'completed')
            ->count();

        if ($count > 0) {
            return $count;
        }

        return (int) ($profile->completed_jobs ?? 0);
    }

    private function formatWorkerSummary(WorkerProfile $profile, ?object $authBarangay): array
    {
        $workerBarangay = $profile->user?->barangay;
        $photoPath = $profile->user?->profile_photo_path;
        $distance = $this->resolveDistance($authBarangay, $workerBarangay);
        $completedJobs = $this->getCompletedJobsCount($profile);

        return [
            'worker_profile_id' => $profile->id,
            'user_id' => $profile->user_id,
            'name' => $profile->user?->name,
            'profile_photo_url' => $photoPath
                ? (str_starts_with($photoPath, 'http://') || str_starts_with($photoPath, 'https://')
                    ? $photoPath
                    : app(\App\Services\Media\MediaStorageService::class)->publicUrl($photoPath))
                : null,
            'barangay' => $workerBarangay?->name,
            'barangay_id' => $profile->user?->barangay_id,
            'distance_km' => $distance['distance_km'],
            'distance_label' => $distance['distance_label'],
            'categories' => $profile->serviceCategories->pluck('name')->all(),
            'average_rating' => (float) $profile->average_rating,
            'total_reviews' => $profile->total_reviews,
            'completed_jobs' => $completedJobs,
            'trust_tier' => $profile->trust_tier,
            'verification_status' => $profile->verification_status,
            'availability_status' => $profile->availability_status,
        ];
    }

    private function formatWorkerDetail(WorkerProfile $profile, ?object $authBarangay): array
    {
        $workerBarangay = $profile->user?->barangay;
        $photoPath = $profile->user?->profile_photo_path;
        $distance = $this->resolveDistance($authBarangay, $workerBarangay);
        $completedJobs = $this->getCompletedJobsCount($profile);

        return [
            'worker_profile_id' => $profile->id,
            'user_id' => $profile->user_id,
            'name' => $profile->user?->name,
            'profile_photo_url' => $photoPath
                ? (str_starts_with($photoPath, 'http://') || str_starts_with($photoPath, 'https://')
                    ? $photoPath
                    : app(\App\Services\Media\MediaStorageService::class)->publicUrl($photoPath))
                : null,
            'bio' => $profile->bio,
            'barangay' => $workerBarangay?->name,
            'barangay_id' => $profile->user?->barangay_id,
            'distance_km' => $distance['distance_km'],
            'distance_label' => $distance['distance_label'],
            'categories' => $profile->serviceCategories->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
            ])->values()->all(),
            'portfolio_photos' => $profile->portfolioPhotos->map(fn ($photo) => [
                'id' => $photo->id,
                'url' => app(\App\Services\Media\MediaStorageService::class)->publicUrl($photo->file_path),
                'display_order' => $photo->display_order,
            ])->values()->all(),
            'average_rating' => (float) $profile->average_rating,
            'total_reviews' => $profile->total_reviews,
            'completed_jobs' => $completedJobs,
            'trust_tier' => $profile->trust_tier,
            'verification_status' => $profile->verification_status,
            'availability_status' => $profile->availability_status,
            'is_online' => $profile->user?->last_seen_at?->gte(now()->subMinutes(5)) ?? false,
            'job_posts' => $profile->jobPosts->map(fn ($p) => [
                'id' => $p->id,
                'category' => [
                    'id' => $p->serviceCategory?->id,
                    'name' => $p->serviceCategory?->name,
                ],
                'title' => $p->title,
                'description' => $p->description,
                'rate_amount' => (float) $p->rate_amount,
                'rate_type' => $p->rate_type,
                'rate_display' => $p->rate_display,
                'is_available' => $p->is_available,
                'is_active' => $p->is_active,
                'images' => $p->images->map(fn ($image) => [
                    'id' => $image->id,
                    'image_url' => app(\App\Services\Media\MediaStorageService::class)->publicUrl($image->image_path),
                    'thumbnail_url' => $image->thumbnail_path
                        ? app(\App\Services\Media\MediaStorageService::class)->publicUrl($image->thumbnail_path)
                        : null,
                    'display_order' => $image->display_order,
                ])->values()->all(),
            ])->values()->all(),
            'reviews' => $profile->ratings->map(fn ($r) => [
                'id' => $r->id,
                'booking_id' => $r->booking_id,
                'rated_by_user_id' => $r->rated_by,
                'rated_by_name' => $r->ratedByUser?->name,
                'reviewer_avatar_url' => $r->ratedByUser?->profile_photo_path
                    ? (str_starts_with($r->ratedByUser->profile_photo_path, 'http://')
                        || str_starts_with($r->ratedByUser->profile_photo_path, 'https://')
                        ? $r->ratedByUser->profile_photo_path
                        : app(\App\Services\Media\MediaStorageService::class)->publicUrl($r->ratedByUser->profile_photo_path))
                    : null,
                'score' => $r->score,
                'comment' => $r->comment,
                'created_at' => $r->created_at?->toISOString(),
            ])->values()->all(),
        ];
    }
}
