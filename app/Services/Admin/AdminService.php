<?php

namespace App\Services\Admin;

use App\Models\AdminAuditLog;
use App\Models\Booking;
use App\Models\JobPost;
use App\Models\RatingReview;
use App\Models\Report;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\WorkerProfile;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AdminService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    // ──────────────────────────────────────────────────────────────────────────
    // AUDIT LOG HELPER
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Write an admin audit log entry.
     */
    public function audit(User $admin, string $action, string $targetType, int $targetId, array $details = [], ?string $ip = null): void
    {
        AdminAuditLog::create([
            'admin_id'    => $admin->id,
            'action'      => $action,
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'details'     => $details ?: null,
            'ip_address'  => $ip,
        ]);
    }    // ──────────────────────────────────────────────────────────────────────────
    // SECTION 1 — VERIFICATION MANAGEMENT
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * List all worker profiles with their verification status.
     * Optionally filtered by verification_status.
     */
    public function listVerifications(?string $status): LengthAwarePaginator
    {
        $query = WorkerProfile::query()
            ->with([
                'user:id,name,email,barangay_id',
                'user.barangay:id,name',
                'verificationDocuments:id,worker_profile_id,document_type,status,file_path',
            ])
            ->orderByDesc('updated_at');

        if ($status !== null) {
            $query->where('verification_status', $status);
        }

        return $query->paginate(15);
    }

    /**
     * Approve, reject, or request resubmission of a worker's verification.
     * Notifies the worker after commit.
     */
    public function reviewVerification(int $workerProfileId, string $action, ?string $adminNotes): WorkerProfile
    {
        $workerProfile = WorkerProfile::findOrFail($workerProfileId);

        $newStatus = match ($action) {
            'approve'              => 'approved',
            'reject'               => 'rejected',
            'request_resubmission' => 'resubmission_required',
            default                => 'rejected',
        };

        DB::transaction(function () use ($workerProfile, $newStatus, $adminNotes): void {
            $workerProfile->update([
                'verification_status'  => $newStatus,
                'verification_remarks' => $adminNotes,
            ]);

            $workerProfile->verificationDocuments()->update(['status' => $newStatus]);
        });

        // Reload the user relationship for the push notification
        $workerProfile->load('user');

        $pushMap = [
            'approved'              => ['Verification Approved',    'Your account has been verified. You can now receive bookings.'],
            'rejected'              => ['Verification Rejected',     'Your verification was rejected. Please resubmit your documents.'],
            'resubmission_required' => ['Resubmission Required',    'Admin has requested changes to your verification documents.'],
        ];

        [$title, $body] = $pushMap[$newStatus];

        $this->notificationService->sendPush(
            $workerProfile->user,
            $title,
            $body,
            ['type' => 'verification_' . $newStatus],
        );

        return $workerProfile;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SECTION 2 — USER MANAGEMENT
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * List all users with optional filters for role, is_active, barangay, and search.
     */
    public function listUsers(?string $role, ?string $isActive, ?string $search, ?int $barangayId = null): LengthAwarePaginator
    {
        $query = User::query()
            ->with('barangay:id,name')
            ->orderByDesc('created_at');

        if ($role !== null) {
            $query->where('role', $role);
        }

        if ($isActive !== null) {
            // Support both ?status=active/suspended and ?is_active=true/false
            if ($isActive === 'active') {
                $query->where('is_active', true);
            } elseif ($isActive === 'suspended') {
                $query->where('is_active', false);
            } else {
                $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
            }
        }

        if ($barangayId !== null) {
            $query->where('barangay_id', $barangayId);
        }

        if ($search !== null) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->paginate(15);
    }

    /**
     * Return a single user. Includes worker_profile when the user is a worker.
     */
    public function getUser(int $id): User
    {
        $user = User::findOrFail($id);

        if ($user->role === 'worker') {
            $user->load([
                'workerProfile:id,user_id,verification_status,average_rating,total_reviews',
            ]);
        }

        return $user;
    }

    /**
     * Toggle is_active for a user. Admin may not deactivate themselves.
     *
     * @throws \App\Exceptions\BusinessRuleException
     */
    public function toggleActive(int $userId, int $adminId): User
    {
        if ($userId === $adminId) {
            throw new \App\Exceptions\BusinessRuleException(
                'You cannot deactivate your own account.'
            );
        }

        $user = User::findOrFail($userId);
        $user->update(['is_active' => ! $user->is_active]);

        return $user;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SECTION 3 — BOOKING OVERSIGHT
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * List all bookings with optional filters for status, category, date range, and search.
     */
    public function listBookings(?string $status, ?int $categoryId = null, ?string $dateFrom = null, ?string $dateTo = null, ?string $search = null): LengthAwarePaginator
    {
        $query = Booking::query()
            ->with([
                'client:id,name',
                'worker:id,name',
                'serviceCategory:id,name',
            ])
            ->orderByDesc('created_at');

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($categoryId !== null) {
            $query->where('service_category_id', $categoryId);
        }

        if ($dateFrom !== null) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($search !== null) {
            $query->where(function ($q) use ($search): void {
                $q->where('booking_code', 'like', "%{$search}%")
                  ->orWhereHas('client', fn ($cq) => $cq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('worker', fn ($wq) => $wq->where('name', 'like', "%{$search}%"));
            });
        }

        return $query->paginate(15);
    }

    /**
     * Return a single booking with full eager loads.
     */
    public function getBooking(int $id): Booking
    {
        return Booking::with([
            'client:id,name',
            'worker:id,name',
            'serviceCategory:id,name',
        ])->findOrFail($id);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SECTION 4 — REPORT MANAGEMENT
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * List all reports, optionally filtered by status.
     */
    public function listReports(?string $status): LengthAwarePaginator
    {
        $query = Report::query()
            ->with([
                'booking:id,booking_code',
                'reporter:id,name',
                'reportedUser:id,name',
            ])
            ->orderByDesc('created_at');

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->paginate(15);
    }

    /**
     * Return a single report with full detail.
     */
    public function getReport(int $id): Report
    {
        return Report::with([
            'booking:id,booking_code',
            'reporter:id,name',
            'reportedUser:id,name',
        ])->findOrFail($id);
    }

    /**
     * Resolve or dismiss a report, storing optional admin notes.
     * Optionally applies an enforcement action to the reported user.
     */
    public function resolveReport(int $id, string $status, ?string $adminNotes, ?string $action = null): Report
    {
        $report = Report::with(['reportedUser.workerProfile'])->findOrFail($id);

        DB::transaction(function () use ($report, $status, $adminNotes, $action): void {
            $report->update([
                'status'        => $status,
                'admin_remarks' => $adminNotes,
            ]);

            if ($status === 'resolved' && $action !== null) {
                $reportedUser = $report->reportedUser;

                match ($action) {
                    'suspend_user'      => $reportedUser?->update(['is_active' => false]),
                    'revoke_trust_tier' => $reportedUser?->workerProfile?->update(['trust_tier' => 'revoked']),
                    'warn_user'         => null, // notification only — no DB change needed
                    default             => null,
                };
            }
        });

        return $report->fresh();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SECTION 5 — DASHBOARD STATS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return aggregate counts for the admin dashboard.
     */
    public function dashboardStats(): array
    {
        return [
            'total_users'              => User::count(),
            'total_workers'            => User::where('role', 'worker')->count(),
            'total_clients'            => User::where('role', 'client')->count(),
            'pending_verifications'    => WorkerProfile::where('verification_status', 'pending')->count(),
            'total_bookings'           => Booking::count(),
            'active_bookings'          => Booking::where('status', 'active')->count(),
            'completed_bookings'       => Booking::where('status', 'completed')->count(),
            'total_reports'            => Report::count(),
            'open_reports'             => Report::where('status', 'under_review')->count(),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SECTION 6 — WORKER TRUST TIER
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Update a worker's trust tier and record the change in the audit log.
     */
    public function updateTrustTier(User $admin, int $workerProfileId, string $tier, string $remarks): WorkerProfile
    {
        $profile = WorkerProfile::findOrFail($workerProfileId);

        $previous = $profile->trust_tier;

        $profile->update([
            'trust_tier'           => $tier,
            'verification_remarks' => $remarks,
        ]);

        $this->audit($admin, 'update_trust_tier', 'WorkerProfile', $profile->id, [
            'previous_tier' => $previous,
            'new_tier'      => $tier,
            'remarks'       => $remarks,
        ]);

        $this->notificationService->sendPush(
            $profile->user,
            'Trust Tier Updated',
            "Your trust tier has been updated to {$tier}.",
            ['type' => 'trust_tier_updated', 'tier' => $tier],
        );

        return $profile->load('user');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SECTION 7 — FORCE CANCEL BOOKING
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Force-cancel a booking by an admin. Allowed on any non-terminal status.
     *
     * @throws \App\Exceptions\BusinessRuleException
     */
    public function forceCancelBooking(User $admin, int $bookingId, string $reason): Booking
    {
        $booking = Booking::with(['client', 'worker'])->findOrFail($bookingId);

        $terminal = ['completed', 'cancelled'];

        if (in_array($booking->status, $terminal, true)) {
            throw new \App\Exceptions\BusinessRuleException(
                "Booking is already {$booking->status} and cannot be cancelled."
            );
        }

        $booking->update([
            'status'            => 'cancelled',
            'cancelled_by'      => 'admin',
            'cancellation_reason' => $reason,
            'force_cancelled_by' => $admin->id,
        ]);

        $this->audit($admin, 'force_cancel_booking', 'Booking', $booking->id, [
            'reason'          => $reason,
            'previous_status' => $booking->getOriginal('status'),
        ]);

        // Notify both parties
        $this->notificationService->sendPush(
            $booking->client,
            'Booking Cancelled by Admin',
            "Your booking #{$booking->booking_code} was cancelled by an admin.",
        );

        $this->notificationService->sendPush(
            $booking->worker,
            'Booking Cancelled by Admin',
            "Booking #{$booking->booking_code} was cancelled by an admin.",
        );

        return $booking->fresh(['client', 'worker', 'serviceCategory']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SECTION 8 — RATINGS OVERSIGHT
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * List all ratings with optional filters: worker_id, client_id, score, direction, search.
     */
    public function listRatings(?int $workerId, ?int $clientId, ?int $score = null, ?string $direction = null, ?string $search = null): LengthAwarePaginator
    {
        $query = RatingReview::query()
            ->with([
                'booking:id,booking_code',
                'ratedByUser:id,name',
                'ratedUser:id,name',
            ])
            ->orderByDesc('created_at');

        if ($workerId !== null) {
            $query->where('rated_user', $workerId);
        }

        if ($clientId !== null) {
            $query->where('rated_by', $clientId);
        }

        if ($score !== null) {
            $query->where('score', $score);
        }

        if ($direction !== null) {
            if ($direction === 'client_to_worker') {
                // rated_by is a client (role=client), rated_user is a worker
                $query->whereHas('ratedByUser', fn ($q) => $q->where('role', 'client'));
            } elseif ($direction === 'worker_to_client') {
                $query->whereHas('ratedByUser', fn ($q) => $q->where('role', 'worker'));
            }
        }

        if ($search !== null) {
            $query->where(function ($q) use ($search): void {
                $q->whereHas('ratedByUser', fn ($iq) => $iq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('ratedUser',   fn ($iq) => $iq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('booking',     fn ($iq) => $iq->where('booking_code', 'like', "%{$search}%"));
            });
        }

        return $query->paginate(15);
    }

    /**
     * Delete a specific rating and recalculate the worker's average.
     */
    public function deleteRating(User $admin, int $ratingId): void
    {
        $rating = RatingReview::findOrFail($ratingId);

        $workerUserId = $rating->rated_user;

        DB::transaction(function () use ($rating, $admin): void {
            $this->audit($admin, 'delete_rating', 'RatingReview', $rating->id, [
                'score'        => $rating->score,
                'rated_user'   => $rating->rated_user,
                'booking_id'   => $rating->booking_id,
            ]);

            $rating->delete();
        });

        // Recalculate the worker's average rating
        $profile = WorkerProfile::where('user_id', $workerUserId)->first();
        if ($profile) {
            $remaining = RatingReview::where('rated_user', $workerUserId)->get();
            $profile->update([
                'average_rating' => $remaining->avg('score') ?? 0,
                'total_reviews'  => $remaining->count(),
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SECTION 9 — AUDIT LOGS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return paginated audit logs, optionally filtered by admin_id, action, target_type, date range.
     */
    public function listAuditLogs(?int $adminId, ?string $action, ?string $targetType = null, ?string $dateFrom = null, ?string $dateTo = null): LengthAwarePaginator
    {
        $query = AdminAuditLog::query()
            ->with('admin:id,name,email')
            ->orderByDesc('created_at');

        if ($adminId !== null) {
            $query->where('admin_id', $adminId);
        }

        if ($action !== null) {
            $query->where('action', 'like', "%{$action}%");
        }

        if ($targetType !== null) {
            $query->where('target_type', $targetType);
        }

        if ($dateFrom !== null) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return $query->paginate(25);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SECTION 10 — PLATFORM SETTINGS (SERVICE CATEGORIES)
    // ──────────────────────────────────────────────────────────────────────────
    /**
     * List all service categories (admin view — includes inactive).
     */
    public function listCategories(): \Illuminate\Database\Eloquent\Collection
    {
        return ServiceCategory::orderBy('name')->get(['id', 'name', 'icon', 'is_active']);
    }

    /**
     * Create a new service category.
     */
    public function createCategory(User $admin, array $data): ServiceCategory
    {
        $category = ServiceCategory::create([
            'name'      => $data['name'],
            'icon'      => $data['icon'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->audit($admin, 'create_category', 'ServiceCategory', $category->id, [
            'name' => $category->name,
        ]);

        return $category;
    }

    /**
     * Update an existing service category.
     *
     * @throws \App\Exceptions\BusinessRuleException
     */
    public function updateCategory(User $admin, int $categoryId, array $data): ServiceCategory
    {
        $category = ServiceCategory::findOrFail($categoryId);

        $category->update(array_filter($data, fn ($v) => $v !== null));

        $this->audit($admin, 'update_category', 'ServiceCategory', $category->id, $data);

        return $category->fresh();
    }

    /**
     * GET /admin/settings — aggregate all platform settings into one response.
     */
    public function getSettings(): array
    {
        $activeAnnouncement = \App\Models\Announcement::active()
            ->latest()
            ->first();

        return [
            'service_categories'      => ServiceCategory::orderBy('name')->get(['id', 'name', 'icon', 'is_active']),
            'report_reasons'          => [
                'no_show', 'unsatisfactory_work', 'misconduct',
                'non_payment', 'unsafe_environment', 'abusive_behavior',
                'false_information', 'other',
            ],
            'notification_templates'  => [
                'booking_accepted'   => 'Your booking {booking_code} has been accepted.',
                'booking_declined'   => 'Your booking {booking_code} has been declined.',
                'booking_completed'  => 'Your booking {booking_code} has been completed.',
                'verification_approved' => 'Your account has been verified. You can now receive bookings.',
                'verification_rejected' => 'Your verification was rejected. Please resubmit your documents.',
                'new_review'         => 'You received a new review!',
            ],
            'active_announcement'     => $activeAnnouncement ? [
                'id'         => $activeAnnouncement->id,
                'title'      => $activeAnnouncement->title,
                'body'       => $activeAnnouncement->body,
                'expires_at' => $activeAnnouncement->expires_at?->toDateString(),
                'posted_at'  => $activeAnnouncement->created_at?->toIso8601String(),
            ] : null,
        ];
    }

    /**
     * POST /admin/settings with action=post_announcement — publish a system-wide announcement.
     * Also creates in-app notifications for all active users.
     */
    public function postAnnouncement(User $admin, string $title, string $body, ?string $expiresAt): \App\Models\Announcement
    {
        $announcement = \App\Models\Announcement::create([
            'posted_by'  => $admin->id,
            'title'      => $title,
            'body'       => $body,
            'expires_at' => $expiresAt,
            'is_active'  => true,
        ]);

        $this->audit($admin, 'post_announcement', 'Announcement', $announcement->id, [
            'title'      => $title,
            'expires_at' => $expiresAt,
        ]);

        // Notify all active users in-app
        User::where('is_active', true)
            ->whereNotNull('email_verified_at')
            ->whereNull('deleted_at')
            ->chunk(200, function ($users) use ($title, $body): void {
                foreach ($users as $user) {
                    $this->notificationService->notify(
                        $user,
                        $title,
                        $body,
                        'system_announcement',
                    );
                }
            });

        return $announcement;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SECTION 11 — JOB POST OVERSIGHT
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * List all active job posts with optional category and worker filters.
     */
    public function listJobPosts(?int $categoryId, ?int $workerProfileId): LengthAwarePaginator
    {
        $query = JobPost::withTrashed()
            ->with([
                'workerProfile.user:id,name',
                'serviceCategory:id,name',
            ])
            ->orderByDesc('created_at');

        if ($categoryId !== null) {
            $query->where('service_category_id', $categoryId);
        }

        if ($workerProfileId !== null) {
            $query->where('worker_profile_id', $workerProfileId);
        }

        return $query->paginate(15);
    }

    /**
     * Force-delete (hard delete) a job post as admin.
     */
    public function forceDeleteJobPost(User $admin, int $postId): void
    {
        $post = JobPost::withTrashed()->findOrFail($postId);

        $this->audit($admin, 'delete_job_post', 'JobPost', $post->id, [
            'title'             => $post->title,
            'worker_profile_id' => $post->worker_profile_id,
        ]);

        $post->forceDelete();
    }
}
