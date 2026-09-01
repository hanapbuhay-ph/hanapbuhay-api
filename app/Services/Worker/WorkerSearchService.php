<?php

namespace App\Services\Worker;

use App\Exceptions\BusinessRuleException;
use App\Helpers\DistanceHelper;
use App\Models\User;
use App\Models\WorkerProfile;
use Illuminate\Http\Request;

class WorkerSearchService
{
    public function getWorkersByCategory(User $authUser, int $categoryId, Request $request): array
    {
        $category = \App\Models\ServiceCategory::find($categoryId);

        if (! $category || ! $category->is_active) {
            throw new BusinessRuleException('Category not found.', 404);
        }

        $query = WorkerProfile::query()
            ->where('verification_status', 'approved')
            ->where(function ($q) {
                $q->whereNull('trust_tier')
                  ->orWhereNotIn('trust_tier', ['flagged', 'revoked']);
            })
            ->with(['user.barangay', 'serviceCategories', 'jobPosts' => fn ($q) => $q->where('service_category_id', $categoryId)->where('is_active', true)])
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

        $formatted = $workers->map(function ($profile) use ($authBarangay, $categoryId) {
            $summary  = $this->formatWorkerSummary($profile, $authBarangay);
            $jobPost  = $profile->jobPosts->first();

            $summary['job_post'] = $jobPost ? [
                'id'           => $jobPost->id,
                'rate_amount'  => (float) $jobPost->rate_amount,
                'rate_type'    => $jobPost->rate_type,
                'rate_display' => $jobPost->rate_display,
            ] : null;

            return $summary;
        })->values()->all();

        return [
            'category' => ['id' => $category->id, 'name' => $category->name],
            'workers'  => $formatted,
            'total'    => count($formatted),
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
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        $workers = $query->get();

        $authBarangay = $authUser->relationLoaded('barangay')
            ? $authUser->barangay
            : $authUser->load('barangay')->barangay;

        return [
            'workers' => $workers->map(fn ($profile) => $this->formatWorkerSummary($profile, $authBarangay))->values()->all(),
            'total'   => $workers->count(),
        ];
    }

    public function getWorker(User $authUser, int $workerProfileId): array
    {
        $profile = WorkerProfile::with([
            'user.barangay',
            'serviceCategories',
            'verificationDocuments',
            'ratings' => fn ($q) => $q->with('ratedByUser')->latest()->limit(10),
        ])->find($workerProfileId);

        if ($profile === null) {
            throw new BusinessRuleException('Worker not found.', 404);
        }

        if ($authUser->role !== 'admin' && $profile->verification_status !== 'approved') {
            throw new BusinessRuleException('Worker not found.', 404);
        }

        $authBarangay = $authUser->relationLoaded('barangay')
            ? $authUser->barangay
            : $authUser->load('barangay')->barangay;

        return $this->formatWorkerDetail($profile, $authBarangay);
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

    private function formatWorkerSummary(WorkerProfile $profile, ?object $authBarangay): array
    {
        $workerBarangay = $profile->user?->barangay;
        $distance       = $this->resolveDistance($authBarangay, $workerBarangay);

        return [
            'worker_profile_id'   => $profile->id,
            'user_id'             => $profile->user_id,
            'name'                => $profile->user?->name,
            'profile_photo_url'   => $profile->user?->profile_photo_path
                ? asset('storage/' . $profile->user->profile_photo_path)
                : null,
            'barangay'            => $workerBarangay?->name,
            'barangay_id'         => $profile->user?->barangay_id,
            'distance_km'         => $distance['distance_km'],
            'distance_label'      => $distance['distance_label'],
            'categories'          => $profile->serviceCategories->pluck('name')->all(),
            'average_rating'      => (float) $profile->average_rating,
            'total_reviews'       => $profile->total_reviews,
            'completed_jobs'      => $profile->completed_jobs,
            'trust_tier'          => $profile->trust_tier,
            'verification_status' => $profile->verification_status,
            'availability_status' => $profile->availability_status,
        ];
    }

    private function formatWorkerDetail(WorkerProfile $profile, ?object $authBarangay): array
    {
        $workerBarangay = $profile->user?->barangay;
        $distance       = $this->resolveDistance($authBarangay, $workerBarangay);

        return [
            'worker_profile_id'   => $profile->id,
            'user_id'             => $profile->user_id,
            'name'                => $profile->user?->name,
            'profile_photo_url'   => $profile->user?->profile_photo_path
                ? asset('storage/' . $profile->user->profile_photo_path)
                : null,
            'bio'                 => $profile->bio,
            'barangay'            => $workerBarangay?->name,
            'barangay_id'         => $profile->user?->barangay_id,
            'distance_km'         => $distance['distance_km'],
            'distance_label'      => $distance['distance_label'],
            'categories'          => $profile->serviceCategories->map(fn ($c) => [
                'id'   => $c->id,
                'name' => $c->name,
            ])->values()->all(),
            'portfolio_photos'    => [], // TODO: needs worker_portfolio_photos table
            'average_rating'      => (float) $profile->average_rating,
            'total_reviews'       => $profile->total_reviews,
            'completed_jobs'      => $profile->completed_jobs,
            'trust_tier'          => $profile->trust_tier,
            'verification_status' => $profile->verification_status,
            'availability_status' => $profile->availability_status,
            'reviews'             => $profile->ratings->map(fn ($r) => [
                'rated_by_name' => $r->ratedByUser?->name,
                'score'         => $r->score,
                'comment'       => $r->comment,
                'created_at'    => $r->created_at?->toISOString(),
            ])->values()->all(),
        ];
    }
}
