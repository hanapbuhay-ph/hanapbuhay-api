<?php

namespace App\Services\Feed;

use App\Helpers\DistanceHelper;
use App\Models\JobPost;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class FeedService
{
    private const PER_PAGE = 15;

    /**
     * Trust tier sort weight — higher = shown first.
     */
    private const TIER_WEIGHT = [
        'trusted'    => 3,
        'verified'   => 2,
        'unverified' => 1,  // null trust_tier falls here
    ];

    /**
     * Build the paginated job-post feed for a client.
     *
     * Sort order (spec §D1):
     *   1. Distance  — nearest first (Haversine, barangay centres)
     *   2. Trust tier — trusted > verified > unverified
     *   3. Average rating — highest first within same tier
     */
    public function getFeed(User $client, Request $request): LengthAwarePaginator
    {
        // Load client's barangay once for distance computation
        $clientBarangay = $client->barangay ?? $client->load('barangay')->barangay;

        $query = JobPost::query()
            ->where('is_active', true)
            ->where('is_available', true)
            ->with([
                'workerProfile.user.barangay',
                'serviceCategory',
            ])
            // Only show posts whose worker is approved and not banned
            ->whereHas('workerProfile', function ($q) {
                $q->where('verification_status', 'approved')
                  ->where(function ($inner) {
                      $inner->whereNull('trust_tier')
                            ->orWhereNotIn('trust_tier', ['flagged', 'revoked']);
                  });
            })
            // Also require worker account to be active
            ->whereHas('workerProfile.user', fn ($q) => $q->where('is_active', true));

        // ── Filters ──────────────────────────────────────────────────────────

        if ($request->filled('category_id')) {
            $query->where('service_category_id', $request->integer('category_id'));
        }

        if ($request->filled('barangay_id')) {
            $query->whereHas('workerProfile.user', fn ($q) => $q->where('barangay_id', $request->integer('barangay_id')));
        }

        if ($request->filled('rate_type')) {
            $query->where('rate_type', $request->string('rate_type'));
        }

        $verification = $request->input('verification', 'all');
        if ($verification === 'verified') {
            // verified or trusted — has a trust_tier
            $query->whereHas('workerProfile', fn ($q) => $q->whereNotNull('trust_tier'));
        } elseif ($verification === 'unverified') {
            $query->whereHas('workerProfile', fn ($q) => $q->whereNull('trust_tier'));
        }

        $availability = $request->input('availability', 'all');
        if ($availability === 'available') {
            $query->whereHas('workerProfile', fn ($q) => $q->where('availability_status', 'available'));
        }

        // ── Fetch all matching posts then sort in PHP ─────────────────────────
        // Sorting by Haversine distance can't be done purely in SQL without
        // a stored function, so we fetch and sort in PHP before paginating.

        $posts = $query->get();

        $sorted = $posts->map(function (JobPost $post) use ($clientBarangay) {
            $workerBarangay = $post->workerProfile?->user?->barangay;

            $distanceKm = ($clientBarangay && $workerBarangay)
                ? DistanceHelper::haversine(
                    (float) $clientBarangay->latitude,
                    (float) $clientBarangay->longitude,
                    (float) $workerBarangay->latitude,
                    (float) $workerBarangay->longitude,
                )
                : PHP_INT_MAX; // push unknown-distance posts to bottom

            $tierWeight = self::TIER_WEIGHT[$post->workerProfile?->trust_tier ?? 'unverified'] ?? 1;

            return [
                'post'         => $post,
                'distance_km'  => $distanceKm === PHP_INT_MAX ? null : $distanceKm,
                'tier_weight'  => $tierWeight,
                'avg_rating'   => (float) ($post->workerProfile?->average_rating ?? 0),
            ];
        })
        ->sortBy([
            ['distance_km', 'asc'],
            ['tier_weight', 'desc'],
            ['avg_rating',  'desc'],
        ])
        ->values();

        // ── Manual pagination ─────────────────────────────────────────────────
        $page    = max(1, (int) $request->query('page', 1));
        $perPage = self::PER_PAGE;
        $total   = $sorted->count();
        $slice   = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        // Format each item
        $items = $slice->map(fn ($item) => $this->formatPost($item['post'], $item['distance_km']));

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }

    // ── Formatter ─────────────────────────────────────────────────────────────

    private function formatPost(JobPost $post, ?float $distanceKm): array
    {
        $profile        = $post->workerProfile;
        $worker         = $profile?->user;
        $workerBarangay = $worker?->barangay;

        $distanceLabel = $distanceKm !== null ? "~{$distanceKm} km" : 'Distance unavailable';

        return [
            'job_post_id'       => $post->id,
            'worker_profile_id' => $profile?->id,
            'worker'            => [
                'user_id'             => $worker?->id,
                'name'                => $worker?->name,
                'profile_photo_url'   => $worker?->profile_photo_path
                    ? asset('storage/' . $worker->profile_photo_path)
                    : null,
                'barangay'            => $workerBarangay?->name,
                'barangay_id'         => $worker?->barangay_id,
                'distance_km'         => $distanceKm,
                'distance_label'      => $distanceLabel,
                'average_rating'      => (float) ($profile?->average_rating ?? 0),
                'total_reviews'       => $profile?->total_reviews ?? 0,
                'trust_tier'          => $profile?->trust_tier,
                'verification_status' => $profile?->verification_status,
            ],
            'category'          => [
                'id'   => $post->serviceCategory?->id,
                'name' => $post->serviceCategory?->name,
                'icon' => $post->serviceCategory?->icon,
            ],
            'title'             => $post->title,
            'description'       => $post->description,
            'rate_amount'       => (float) $post->rate_amount,
            'rate_type'         => $post->rate_type,
            'rate_display'      => $post->rate_display,
            'is_available'      => $post->is_available,
            'posted_at'         => $post->created_at?->toIso8601String(),
        ];
    }
}
