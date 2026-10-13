<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\ToggleUserStatusRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Models\User;
use App\Services\Admin\AdminService;
use App\Services\SecurityEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly AdminService $adminService,
        private readonly SecurityEventLogger $securityEventLogger,
    ) {}

    /**
     * GET /api/admin/users
     * List all users with optional role, is_active, and search filters.
     */
    public function index(Request $request): JsonResponse
    {
        $paginated = $this->adminService->listUsers(
            $request->query('role'),
            $request->query('is_active') ?? $request->query('status'), // support both param names
            $request->query('search'),
            $request->filled('barangay') ? $request->integer('barangay') : null,
            $request->query('verification'),
        );

        $users = collect($paginated->items())->map(function ($user): array {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile_number' => $user->mobile_number,
                'role' => $user->role,
                'barangay_id' => $user->barangay_id,
                'barangay' => $user->barangay?->name,
                'worker_profile_id' => $user->workerProfile?->id,
                'profile_photo_url' => self::profilePhotoUrl($user->profile_photo_path),
                'is_active' => $user->is_active,
                'created_at' => $user->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved.',
            'data' => [
                'users' => $users,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                ],
            ],
        ], 200);
    }

    public function store(StoreAdminUserRequest $request): JsonResponse
    {
        $user = $this->adminService->createUser($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'User created.',
            'data' => self::userData($user),
        ], 201);
    }

    private static function profilePhotoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
            ? $path
            : asset('storage/'.$path);
    }

    /**
     * GET /api/admin/users/{id}
     * View a single user's full detail, including worker_profile if applicable.
     */
    public function show(int|string $id): JsonResponse
    {
        $user = $this->adminService->getUser($id);

        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'mobile_number' => $user->mobile_number,
            'profile_photo_path' => $user->profile_photo_path,
            'barangay_id' => $user->barangay_id,
            'barangay' => $user->barangay?->name,
            'worker_profile_id' => $user->workerProfile?->id,
            'profile_photo_url' => self::profilePhotoUrl($user->profile_photo_path),
            'is_active' => $user->is_active,
            'is_google_account' => $user->is_google_account,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
            'last_seen_at' => $user->last_seen_at?->toIso8601String(),
            'deletion_requested_at' => $user->deletion_requested_at?->toIso8601String(),
            'two_factor_enabled' => $user->two_factor_enabled,
            'notification_preferences' => [
                'booking_updates' => $user->notify_booking_updates,
                'messages' => $user->notify_messages,
                'promotions' => $user->notify_promotions,
                'push' => $user->notify_push,
                'email' => $user->notify_email,
            ],
        ];

        if ($user->role === 'worker' && $user->workerProfile) {
            $data['worker_profile'] = [
                'bio' => $user->workerProfile->bio,
                'verification_status' => $user->workerProfile->verification_status,
                'trust_tier' => $user->workerProfile->trust_tier,
                'availability_status' => $user->workerProfile->availability_status,
                'average_rating' => $user->workerProfile->average_rating,
                'total_reviews' => $user->workerProfile->total_reviews,
                'completed_jobs' => $user->workerProfile->completed_jobs,
                'verification_remarks' => $user->workerProfile->verification_remarks,
                'verified_at' => $user->workerProfile->verified_at?->toIso8601String(),
                'verified_by' => $user->workerProfile->verifiedBy
                    ? [
                        'id' => $user->workerProfile->verifiedBy->id,
                        'name' => $user->workerProfile->verifiedBy->name,
                    ]
                    : null,
                'created_at' => $user->workerProfile->created_at?->toIso8601String(),
                'updated_at' => $user->workerProfile->updated_at?->toIso8601String(),
                'service_categories' => $user->workerProfile->serviceCategories
                    ->map(fn ($category) => ['id' => $category->id, 'name' => $category->name])
                    ->values(),
                'job_posts' => $user->workerProfile->jobPosts
                    ->map(fn ($post) => [
                        'id' => $post->id,
                        'title' => $post->title,
                        'description' => $post->description,
                        'service_category' => $post->serviceCategory?->name,
                        'rate_amount' => (float) $post->rate_amount,
                        'rate_type' => $post->rate_type,
                        'rate_display' => $post->rate_display,
                        'is_available' => $post->is_available,
                        'is_active' => $post->is_active,
                        'deleted_at' => $post->deleted_at?->toIso8601String(),
                        'created_at' => $post->created_at?->toIso8601String(),
                        'images' => $post->images->map(fn ($image) => [
                            'image_url' => self::profilePhotoUrl($image->image_path),
                            'thumbnail_url' => self::profilePhotoUrl($image->thumbnail_path),
                        ])->values(),
                    ])
                    ->values(),
            ];
        }

        if (in_array($user->role, ['client', 'worker'], true)) {
            $otherPartyKey = $user->role === 'client' ? 'worker' : 'client';
            $data['activity'] = [
                'bookings' => [
                    'total' => array_sum($user->booking_counts ?? []),
                    'by_status' => $user->booking_counts ?? [],
                    'pagination' => self::activityPagination(array_sum($user->booking_counts ?? [])),
                    'recent' => $user->recentBookings
                        ->map(fn ($booking) => [
                            'id' => $booking->id,
                            'booking_code' => $booking->booking_code,
                            'status' => $booking->status,
                            'scheduled_at' => $booking->scheduled_at?->toIso8601String(),
                            'service_category' => $booking->serviceCategory?->name,
                            'other_party' => [
                                'id' => $booking->{$otherPartyKey}?->id,
                                'name' => $booking->{$otherPartyKey}?->name,
                            ],
                        ])
                        ->values(),
                ],
                'reviews_received_count' => $user->reviews_received_count,
                'reviews_given_count' => $user->reviews_given_count,
                'reviews_received_pagination' => self::activityPagination($user->reviews_received_count),
                'reviews_given_pagination' => self::activityPagination($user->reviews_given_count),
                'recent_reviews_received' => $user->recentReceivedRatings
                    ->map(fn ($review) => [
                        'id' => $review->id,
                        'booking_code' => $review->booking?->booking_code,
                        'from' => $review->ratedByUser?->name,
                        'score' => $review->score,
                        'comment' => $review->comment,
                        'created_at' => $review->created_at?->toIso8601String(),
                    ])
                    ->values(),
                'recent_reviews_given' => $user->recentSentRatings
                    ->map(fn ($review) => [
                        'id' => $review->id,
                        'booking_code' => $review->booking?->booking_code,
                        'to' => $review->ratedUser?->name,
                        'score' => $review->score,
                        'comment' => $review->comment,
                        'created_at' => $review->created_at?->toIso8601String(),
                    ])
                    ->values(),
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'User retrieved.',
            'data' => $data,
        ], 200);
    }

    public function activity(Request $request, int|string $id, string $type): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $paginated = $this->adminService->getUserActivityPage((int) $id, $type, $perPage);

        $items = collect($paginated->items())->map(function ($item) use ($type): array {
            $otherParty = $item->relationLoaded('worker') ? $item->worker : $item->client;

            return match ($type) {
                'bookings' => [
                    'id' => $item->id,
                    'booking_code' => $item->booking_code,
                    'status' => $item->status,
                    'scheduled_at' => $item->scheduled_at?->toIso8601String(),
                    'service_category' => $item->serviceCategory?->name,
                    'other_party' => $otherParty
                        ? [
                            'id' => $otherParty->id,
                            'name' => $otherParty->name,
                        ]
                        : ['id' => null, 'name' => null],
                ],
                'reviews-received' => [
                    'id' => $item->id,
                    'booking_code' => $item->booking?->booking_code,
                    'from' => $item->ratedByUser?->name,
                    'score' => $item->score,
                    'comment' => $item->comment,
                    'created_at' => $item->created_at?->toIso8601String(),
                ],
                'reviews-given' => [
                    'id' => $item->id,
                    'booking_code' => $item->booking?->booking_code,
                    'to' => $item->ratedUser?->name,
                    'score' => $item->score,
                    'comment' => $item->comment,
                    'created_at' => $item->created_at?->toIso8601String(),
                ],
            };
        });

        return response()->json([
            'success' => true,
            'message' => 'User activity retrieved.',
            'data' => [
                'items' => $items,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                ],
            ],
        ]);
    }

    private static function activityPagination(int $total): array
    {
        return [
            'current_page' => 1,
            'per_page' => 10,
            'total' => $total,
            'last_page' => max(1, (int) ceil($total / 10)),
        ];
    }

    public function update(UpdateAdminUserRequest $request, int|string $id): JsonResponse
    {
        try {
            $user = $this->adminService->updateUser($id, $request->validated(), $request->user());
        } catch (BusinessRuleException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->statusCode());
        }

        return response()->json([
            'success' => true,
            'message' => 'User updated.',
            'data' => self::userData($user),
        ]);
    }

    public function destroy(Request $request, int|string $id): JsonResponse
    {
        try {
            $this->adminService->deleteUser($id, $request->user());
        } catch (BusinessRuleException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->statusCode());
        }

        return response()->json([
            'success' => true,
            'message' => 'User deleted.',
        ]);
    }

    private static function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'mobile_number' => $user->mobile_number,
            'barangay_id' => $user->barangay_id,
            'barangay' => $user->barangay?->name,
            'is_active' => $user->is_active,
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }

    /**
     * PATCH /api/admin/users/{id}/toggle-active  (legacy)
     * Toggle a user's is_active flag. Admin cannot deactivate themselves.
     */
    public function toggleActive(Request $request, int|string $id): JsonResponse
    {
        try {
            $user = $this->adminService->toggleActive((int) $id, $request->user());
        } catch (BusinessRuleException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->statusCode());
        }

        $message = $user->is_active ? 'User reactivated.' : 'User deactivated.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'user_id' => $user->id,
                'is_active' => $user->is_active,
            ],
        ], 200);
    }

    /**
     * POST /api/admin/users/{id}/toggle-status  (spec URL)
     * Suspend or reactivate a user with an explicit action + optional reason.
     * Logs the action in admin_audit_logs.
     */
    public function toggleStatus(ToggleUserStatusRequest $request, int|string $id): JsonResponse
    {
        $admin = $request->user();
        $action = $request->validated('action');
        $reason = $request->validated('reason');

        if ((int) $id === $admin->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot modify your own account status.',
            ], 422);
        }

        $user = User::findOrFail($id);
        if ($user->isSuperAdmin() && ! $admin->isSuperAdmin()) {
            $this->securityEventLogger->record(
                'protected_account_status_change_denied',
                'failure',
                $admin,
                $user->id,
                [],
                $request->ip(),
                $request->userAgent(),
            );
            return response()->json([
                'success' => false,
                'message' => 'The super admin account can only be managed by the super admin.',
            ], 403);
        }

        $previousActive = $user->is_active;
        $newActive = ($action === 'reactivate');
        $user->update(['is_active' => $newActive]);

        $this->securityEventLogger->record(
            $newActive ? 'user_reactivated' : 'user_suspended',
            'success',
            $admin,
            $user->id,
            array_filter(['reason' => $reason]),
            $request->ip(),
            $request->userAgent(),
        );

        $this->adminService->audit($admin, "user_{$action}", 'User', $user->id, array_filter([
            'changes' => [
                'is_active' => [
                    'from' => $previousActive,
                    'to' => $newActive,
                ],
            ],
            'reason' => $reason,
        ]));

        $message = $action === 'suspend' ? 'User account suspended.' : 'User account reactivated.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'user_id' => $user->id,
                'is_active' => $user->is_active,
            ],
        ]);
    }
}
