<?php

namespace App\Services\Admin;

use App\Events\AdminEntityChanged;
use App\Exceptions\BusinessRuleException;
use App\Models\AdminAuditLog;
use App\Models\Announcement;
use App\Models\Booking;
use App\Models\JobPost;
use App\Models\RatingReview;
use App\Models\Report;
use App\Models\ReportReason;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\UserAccountAction;
use App\Models\VerificationHistory;
use App\Models\WorkerProfile;
use App\Services\Media\MediaStorageService;
use App\Services\Notification\NotificationService;
use App\Services\SecurityEventLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;

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
    public function audit(User $admin, string $action, string $targetType, ?int $targetId, array $details = [], ?string $ip = null): void
    {
        AdminAuditLog::create([
            'admin_id' => $admin->id,
            'actor_name' => $admin->name,
            'actor_email' => $admin->email,
            'category' => 'admin_activity',
            'outcome' => 'success',
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'details' => $details ?: null,
            'ip_address' => $ip ?? request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 1024) ?: null,
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
                'user:id,name,email,barangay_id,profile_photo_path',
                'user.barangay:id,name',
                'verificationDocuments:id,worker_profile_id,document_type,status,file_path',
                'verificationHistory:id,worker_profile_id,changed_by,status,remarks,created_at',
                'verificationHistory.changedBy:id,name',
            ])
            ->orderByDesc('updated_at');

        if ($status !== null) {
            $query->where('verification_status', $status);
        }

        return $query->paginate(15);
    }

    public function findVerification(int|string $workerProfileId): WorkerProfile
    {
        return WorkerProfile::query()
            ->with([
                'user:id,name,email,barangay_id',
                'user.barangay:id,name',
                'verificationDocuments:id,worker_profile_id,document_type,status,file_path',
                'verificationHistory:id,worker_profile_id,changed_by,status,remarks,created_at',
                'verificationHistory.changedBy:id,name',
            ])
            ->findOrFail($workerProfileId);
    }

    /**
     * Approve, reject, or request resubmission of a worker's verification.
     * Notifies the worker after commit.
     */
    public function reviewVerification(User $admin, int|string $workerProfileId, string $action, ?string $adminNotes): WorkerProfile
    {
        $workerProfile = WorkerProfile::findOrFail($workerProfileId);

        $newStatus = match ($action) {
            'approve' => 'approved',
            'reject' => 'rejected',
            'request_resubmission' => 'resubmission_required',
            default => 'rejected',
        };

        $previousStatus = $workerProfile->verification_status;
        $previousTrustTier = $workerProfile->trust_tier;

        DB::transaction(function () use ($admin, $workerProfile, $newStatus, $adminNotes): void {
            $updateData = [
                'verification_status' => $newStatus,
                'verification_remarks' => $adminNotes,
            ];

            // Spec: auto-set trust_tier = 'verified' when approved
            if ($newStatus === 'approved') {
                $updateData['trust_tier'] = 'verified';
                $updateData['verified_by'] = null; // will be set after load
                $updateData['verified_at'] = now();
            }

            $workerProfile->update($updateData);

            $workerProfile->verificationDocuments()->update(['status' => $newStatus]);

            VerificationHistory::create([
                'worker_profile_id' => $workerProfile->id,
                'changed_by' => $admin->id,
                'status' => $newStatus,
                'remarks' => $adminNotes,
            ]);
        });

        $this->audit($admin, 'review_verification', 'WorkerProfile', $workerProfile->id, [
            'worker_id' => $workerProfile->user_id,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'previous_trust_tier' => $previousTrustTier,
            'new_trust_tier' => $newStatus === 'approved' ? 'verified' : $previousTrustTier,
            'admin_notes' => $adminNotes,
        ]);

        // Reload the user relationship for the notification.
        $workerProfile->load('user');

        $pushMap = [
            'approved' => ['Verification Approved',    'Your account has been verified. You can now receive bookings.'],
            'rejected' => ['Verification Rejected',     'Your verification was rejected. Please resubmit your documents.'],
            'resubmission_required' => ['Resubmission Required',    'Admin has requested changes to your verification documents.'],
        ];

        [$title, $body] = $pushMap[$newStatus];

        $this->notificationService->notify(
            $workerProfile->user,
            $title,
            $body,
            'verification_'.$newStatus,
        );

        return $workerProfile;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SECTION 2 — USER MANAGEMENT
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * List all users with optional filters for role, is_active, barangay, and search.
     */
    public function listUsers(
        ?string $role,
        ?string $isActive,
        ?string $search,
        ?int $barangayId = null,
        ?string $verification = null,
    ): LengthAwarePaginator {
        $query = User::query()
            ->with([
                'barangay:id,name',
                'workerProfile:id,user_id',
                'pendingAdminActions',
            ])
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

        if ($verification === 'verified') {
            $query->whereHas('workerProfile', fn ($profileQuery) => $profileQuery->where('verification_status', 'approved')
            );
        } elseif ($verification === 'unverified') {
            $query->where(function ($verificationQuery): void {
                $verificationQuery
                    ->whereDoesntHave('workerProfile')
                    ->orWhereHas('workerProfile', fn ($profileQuery) => $profileQuery->where('verification_status', '!=', 'approved')
                    );
            });
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
        $user = User::with('barangay:id,name')->findOrFail($id);

        if ($user->role === 'worker') {
            $user->load([
                'workerProfile.serviceCategories:id,name',
                'workerProfile.verifiedBy:id,name',
                'workerProfile.jobPosts' => fn ($query) => $query
                    ->withTrashed()
                    ->with([
                        'serviceCategory:id,name',
                        'images:id,job_post_id,image_path,thumbnail_path,display_order',
                    ])
                    ->orderByDesc('created_at'),
            ]);
        }

        if (in_array($user->role, ['client', 'worker'], true)) {
            $bookingRelation = $user->role === 'client'
                ? $user->clientBookings()
                : $user->workerBookings();
            $otherPartyRelation = $user->role === 'client' ? 'worker' : 'client';

            $user->setAttribute(
                'booking_counts',
                (clone $bookingRelation)
                    ->selectRaw('status, COUNT(*) as aggregate')
                    ->groupBy('status')
                    ->pluck('aggregate', 'status')
                    ->all()
            );
            $user->setRelation(
                'recentBookings',
                (clone $bookingRelation)
                    ->with([$otherPartyRelation.':id,name', 'serviceCategory:id,name'])
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get()
            );
            $user->setAttribute('reviews_received_count', $user->receivedRatings()->count());
            $user->setAttribute('reviews_given_count', $user->sentRatings()->count());
            $user->setRelation(
                'recentReceivedRatings',
                $user->receivedRatings()
                    ->with(['ratedByUser:id,name', 'booking:id,booking_code'])
                    ->latest()
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get()
            );
            $user->setRelation(
                'recentSentRatings',
                $user->sentRatings()
                    ->with(['ratedUser:id,name', 'booking:id,booking_code'])
                    ->latest()
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get()
            );
        }

        return $user;
    }

    public function getUserActivityPage(int $id, string $type, int $perPage): LengthAwarePaginator
    {
        $user = User::query()->findOrFail($id);

        if (! in_array($user->role, ['client', 'worker'], true)) {
            abort(404, 'User activity not found.');
        }

        $query = match ($type) {
            'bookings' => ($user->role === 'client' ? $user->clientBookings() : $user->workerBookings())
                ->with([
                    ($user->role === 'client' ? 'worker' : 'client').':id,name',
                    'serviceCategory:id,name',
                ]),
            'reviews-received' => $user->receivedRatings()
                ->with(['ratedByUser:id,name', 'booking:id,booking_code']),
            'reviews-given' => $user->sentRatings()
                ->with(['ratedUser:id,name', 'booking:id,booking_code']),
        };

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function createUser(array $attributes, User $admin): User
    {
        $user = DB::transaction(function () use ($attributes): User {
            $user = User::create([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => $attributes['password'],
                'mobile_number' => $attributes['mobile_number'] ?? null,
                'role' => $attributes['role'],
                'barangay_id' => $attributes['barangay_id'] ?? null,
                'is_active' => $attributes['is_active'] ?? true,
                'email_verified_at' => now(),
            ]);

            if ($user->role === 'worker') {
                WorkerProfile::create([
                    'user_id' => $user->id,
                    'availability_status' => 'available',
                ]);
            }

            return $user;
        });

        $this->audit($admin, 'create_user', 'User', $user->id, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'mobile_number' => $user->mobile_number,
            'barangay_id' => $user->barangay_id,
            'is_active' => $user->is_active,
            'password_set' => true,
        ]);
        event(new AdminEntityChanged('user', 'created', (int) $user->id, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ]));

        return $user->load('barangay:id,name');
    }

    public function updateUser(int|string $userId, array $attributes, User $admin): User
    {
        if ((int) $userId === $admin->id && array_key_exists('is_active', $attributes) && ! $attributes['is_active']) {
            throw new BusinessRuleException('You cannot deactivate your own account.');
        }

        $user = User::findOrFail($userId);
        $this->assertSuperAdminManageableBy($user, $admin);
        $previousRole = $user->role;
        if (
            in_array($previousRole, ['client', 'worker'], true)
            && ($attributes['role'] ?? $previousRole) === 'admin'
        ) {
            throw new BusinessRuleException(
                'Client or worker accounts cannot be changed to an admin role.',
                403
            );
        }

        $previousValues = [];
        foreach (array_keys($attributes) as $field) {
            if (! in_array($field, ['password', 'password_confirmation'], true)) {
                $previousValues[$field] = $user->getRawOriginal($field);
            }
        }
        $role = $attributes['role'] ?? $user->role;
        $updateData = collect($attributes)->except('password')->all();

        if (! empty($attributes['password'])) {
            $updateData['password'] = $attributes['password'];
        }

        DB::transaction(function () use ($user, $updateData, $role): void {
            $user->update($updateData);

            if ($role === 'worker' && ! $user->workerProfile()->exists()) {
                WorkerProfile::create([
                    'user_id' => $user->id,
                    'availability_status' => 'available',
                ]);
            }
        });

        $changes = $this->changesFor($previousValues, $user->getAttributes(), array_keys($previousValues));
        $this->audit($admin, 'update_user', 'User', $user->id, [
            'changes' => $changes,
            'password_changed' => ! empty($attributes['password']),
        ]);
        if ($previousRole !== $user->role) {
            $this->securityEvent('user_role_changed', 'success', $admin, $user, [
                'previous_role' => $previousRole,
                'new_role' => $user->role,
            ]);
        }
        if (! empty($attributes['password'])) {
            $this->securityEvent('user_password_changed_by_admin', 'success', $admin, $user);
        }
        event(new AdminEntityChanged('user', 'updated', (int) $user->id, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ]));

        return $user->fresh()->load('barangay:id,name');
    }

    public function processScheduledAccountAction(UserAccountAction $scheduledAction): void
    {
        $storagePaths = [];
        $actionType = $scheduledAction->action;
        $processed = DB::transaction(function () use ($scheduledAction, &$storagePaths): bool {
            $action = UserAccountAction::query()
                ->lockForUpdate()
                ->findOrFail($scheduledAction->id);

            if ($action->status !== 'pending' || $action->scheduled_for->isFuture()) {
                return false;
            }

            $user = User::withTrashed()->find($action->target_user_id);
            if ($user === null) {
                $action->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'failure_reason' => 'The target account no longer exists.',
                ]);

                return false;
            }

            $previousActive = $user->is_active;
            if ($action->action === 'delete') {
                $storagePaths = $this->storagePathsForUser($user);
                $user->tokens()->delete();
                $user->forceDelete();
            } else {
                $user->update(['is_active' => false]);
            }

            $admin = User::withTrashed()->find($action->requested_by);
            $details = [
                'reason' => $action->reason,
                'scheduled_for' => $action->scheduled_for->toIso8601String(),
                'account_action_id' => $action->id,
            ];
            if ($action->action === 'suspend') {
                $details['changes'] = [
                    'is_active' => [
                        'from' => $previousActive,
                        'to' => false,
                    ],
                ];
            }

            AdminAuditLog::create([
                'admin_id' => $admin?->id,
                'actor_name' => $admin?->name ?? $action->requester_name,
                'actor_email' => $admin?->email ?? $action->requester_email,
                'category' => 'admin_activity',
                'outcome' => 'success',
                'action' => $action->action === 'delete' ? 'delete_user' : 'user_suspend',
                'target_type' => 'User',
                'target_id' => $action->target_user_id,
                'details' => $details,
                'ip_address' => null,
                'user_agent' => null,
            ]);

            app(SecurityEventLogger::class)->record(
                $action->action === 'delete' ? 'user_deleted_by_admin' : 'user_suspended',
                'success',
                $admin,
                $action->target_user_id,
                $details,
            );

            $action->update([
                'status' => 'completed',
                'completed_at' => now(),
                'failure_reason' => null,
            ]);

            return true;
        });

        if (! $processed) {
            return;
        }

        if ($actionType === 'delete') {
            if ($storagePaths !== [] && ! app(MediaStorageService::class)->deleteMany($storagePaths)) {
                Log::warning('Unable to remove one or more files belonging to a scheduled user deletion.', [
                    'user_id' => $scheduledAction->target_user_id,
                    'paths' => $storagePaths,
                ]);
            }

            event(new AdminEntityChanged('user', 'deleted', (int) $scheduledAction->target_user_id, [
                'email' => $scheduledAction->target_email,
            ]));

            return;
        }

        event(new AdminEntityChanged('user', 'updated', (int) $scheduledAction->target_user_id, [
            'is_active' => false,
        ]));
    }

    private function storagePathsForUser(User $user): array
    {
        $paths = [$user->profile_photo_path];
        $profile = $user->workerProfile;

        if ($profile !== null) {
            $paths = array_merge(
                $paths,
                $profile->verificationDocuments()->pluck('file_path')->all(),
                $profile->portfolioPhotos()->pluck('file_path')->all(),
            );

            $posts = JobPost::withTrashed()
                ->where('worker_profile_id', $profile->id)
                ->with('images')
                ->get();

            foreach ($posts as $post) {
                foreach ($post->images as $image) {
                    $paths[] = $image->image_path;
                    $paths[] = $image->thumbnail_path;
                }
            }
        }

        $paths = array_merge(
            $paths,
            DB::table('messages')
                ->where(fn ($query) => $query
                    ->where('sender_id', $user->id)
                    ->orWhere('receiver_id', $user->id))
                ->pluck('attachment_path')
                ->all(),
        );

        $reports = DB::table('reports')
            ->where(fn ($query) => $query
                ->where('reported_by', $user->id)
                ->orWhere('reported_user', $user->id))
            ->pluck('evidence_paths');

        foreach ($reports as $evidencePaths) {
            $evidencePaths = is_array($evidencePaths)
                ? $evidencePaths
                : json_decode((string) $evidencePaths, true);

            if (is_array($evidencePaths)) {
                $paths = array_merge($paths, $evidencePaths);
            }
        }

        return array_values(array_unique(array_filter(
            $paths,
            fn ($path) => is_string($path)
                && $path !== ''
                && ! Str::startsWith($path, ['http://', 'https://']),
        )));
    }

    /**
     * Toggle is_active for a user. Admin may not deactivate themselves.
     *
     * @throws BusinessRuleException
     */
    public function toggleActive(int $userId, User $admin): User
    {
        if ($userId === $admin->id) {
            throw new BusinessRuleException(
                'You cannot deactivate your own account.'
            );
        }

        $user = User::findOrFail($userId);
        $this->assertSuperAdminManageableBy($user, $admin);
        $previousActive = $user->is_active;
        $user->update(['is_active' => ! $user->is_active]);
        $this->audit($admin, $user->is_active ? 'user_reactivate' : 'user_suspend', 'User', $user->id, [
            'changes' => [
                'is_active' => [
                    'from' => $previousActive,
                    'to' => $user->is_active,
                ],
            ],
        ]);
        $this->securityEvent(
            $user->is_active ? 'user_reactivated' : 'user_suspended',
            'success',
            $admin,
            $user,
        );

        return $user;
    }

    private function securityEvent(
        string $action,
        string $outcome,
        ?User $actor,
        ?User $target,
        array $details = [],
    ): void {
        app(SecurityEventLogger::class)->record(
            $action,
            $outcome,
            $actor,
            $target?->id,
            $details,
            request()->ip(),
            request()->userAgent(),
        );
    }

    private function changesFor(array $before, array $after, array $fields): array
    {
        $changes = [];

        foreach ($fields as $field) {
            if (! array_key_exists($field, $after)) {
                continue;
            }

            $oldValue = $before[$field] ?? null;
            $newValue = $after[$field];
            if (json_encode($oldValue) === json_encode($newValue)) {
                continue;
            }

            $changes[$field] = [
                'from' => $oldValue,
                'to' => $newValue,
            ];
        }

        return $changes;
    }

    private function assertSuperAdminManageableBy(User $target, User $admin): void
    {
        if ($target->isSuperAdmin() && ! $admin->isSuperAdmin()) {
            $this->securityEvent(
                'protected_account_change_denied',
                'failure',
                $admin,
                $target,
            );
            throw new BusinessRuleException(
                'The super admin account can only be managed by the super admin.',
                403
            );
        }
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
                'client:id,name,profile_photo_path',
                'worker:id,name,profile_photo_path',
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
            'client:id,name,email,mobile_number,barangay_id',
            'client.barangay:id,name',
            'worker:id,name,email,mobile_number,barangay_id',
            'worker.barangay:id,name',
            'serviceCategory:id,name',
            'jobPost:id,title,description,rate_amount,rate_type',
        ])->withCount(['messages', 'reports', 'trackingPoints'])->findOrFail($id);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SECTION 4 — REPORT MANAGEMENT
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * List all reports, optionally filtered by status and reason.
     */
    public function listReports(?string $status, ?string $reason = null): LengthAwarePaginator
    {
        $query = Report::query()
            ->with([
                'booking:id,booking_code',
                'reporter:id,name',
                'reportedUser:id,name',
                'submittedByAdmin:id,name',
            ])
            ->orderByDesc('created_at');

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($reason !== null) {
            $query->where('reason', $reason);
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
            'submittedByAdmin:id,name',
            'resolvedBy:id,name',
        ])->findOrFail($id);
    }

    /**
     * Resolve or dismiss a report, storing optional admin notes.
     * Supports both spec field name (resolution_action) and legacy (action).
     * Side-effects are enforced inside a transaction.
     */
    public function resolveReport(int $id, string $status, ?string $adminNotes, ?string $action = null, ?string $resolutionAction = null, ?int $resolvedById = null): Report
    {
        $report = Report::with(['reportedUser.workerProfile'])->findOrFail($id);
        $previousStatus = $report->status;

        // Normalise: map spec resolution_action → internal action
        $effectiveAction = $action;
        if ($resolutionAction !== null && $effectiveAction === null) {
            $effectiveAction = match ($resolutionAction) {
                'account_suspended' => 'suspend_user',
                'verification_revoked' => 'revoke_trust_tier',
                'warning_issued' => 'warn_user',
                'no_action' => null,
                default => null,
            };
        }

        $storedResolutionAction = $resolutionAction ?? match ($effectiveAction) {
            'suspend_user' => 'account_suspended',
            'revoke_trust_tier' => 'verification_revoked',
            'warn_user' => 'warning_issued',
            default => null,
        };

        DB::transaction(function () use ($report, $status, $adminNotes, $effectiveAction, $storedResolutionAction, $resolvedById): void {
            $report->update([
                'status' => $status,
                'admin_remarks' => $adminNotes,
                'resolution_action' => $status === 'resolved' ? $storedResolutionAction : null,
                'resolved_by' => $status === 'resolved' ? $resolvedById : null,
                'resolved_at' => $status === 'resolved' ? now() : null,
            ]);

            if ($status === 'resolved' && $effectiveAction !== null) {
                $reportedUser = $report->reportedUser;

                match ($effectiveAction) {
                    'suspend_user' => $reportedUser?->update(['is_active' => false]),
                    'revoke_trust_tier' => $reportedUser?->workerProfile?->update(['trust_tier' => 'revoked']),
                    'warn_user' => null,
                    default => null,
                };

                if ($effectiveAction === 'suspend_user' && $reportedUser !== null) {
                    $this->securityEvent(
                        'user_suspended',
                        'success',
                        $resolvedById === null ? null : User::find($resolvedById),
                        $reportedUser,
                        ['source' => 'report_resolution', 'report_id' => $report->id],
                    );
                }
            }
        });

        if ($resolvedById !== null) {
            $this->audit(
                User::findOrFail($resolvedById),
                'resolve_report',
                'Report',
                $report->id,
                [
                    'reason' => $report->reason,
                    'previous_status' => $previousStatus,
                    'new_status' => $status,
                    'resolution_action' => $status === 'resolved' ? $storedResolutionAction : null,
                    'reported_user_id' => $report->reported_user_id,
                    'admin_notes' => $adminNotes,
                ],
            );
        }

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
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $startDate = now()->subDays(6)->startOfDay();
        $endDate = now()->endOfDay();

        $recentActivity = AdminAuditLog::with('admin:id,name')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($log) => [
                'type' => $log->action,
                'description' => ucfirst(str_replace('_', ' ', $log->action))
                    .' by '.($log->admin?->name ?? 'Admin'),
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->all();

        $trendCounts = function ($query) use ($startDate, $endDate): array {
            return $query
                ->whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('DATE(created_at) as activity_date, COUNT(*) as total')
                ->groupBy('activity_date')
                ->pluck('total', 'activity_date')
                ->map(fn ($total) => (int) $total)
                ->all();
        };

        $bookingTrend = $trendCounts(Booking::query());
        $verificationTrend = $trendCounts(VerificationHistory::query());
        $jobTrend = $trendCounts(
            JobPost::query()->where('is_active', true)->whereNull('deleted_at')
        );

        $activityTrend = collect(range(0, 6))->map(function (int $offset) use (
            $bookingTrend,
            $verificationTrend,
            $jobTrend,
        ): array {
            $day = now()->subDays(6 - $offset);
            $date = $day->toDateString();

            return [
                'date' => $date,
                'label' => $day->translatedFormat('D'),
                'bookings' => $bookingTrend[$date] ?? 0,
                'verifications' => $verificationTrend[$date] ?? 0,
                'active_jobs' => $jobTrend[$date] ?? 0,
            ];
        })->values()->all();

        $topServiceCategories = ServiceCategory::query()
            ->leftJoin('bookings', 'service_categories.id', '=', 'bookings.service_category_id')
            ->where('service_categories.is_active', true)
            ->select(
                'service_categories.name',
                DB::raw('COUNT(bookings.id) as booking_count'),
            )
            ->groupBy('service_categories.id', 'service_categories.name')
            ->orderByDesc('booking_count')
            ->orderBy('service_categories.name')
            ->limit(8)
            ->get()
            ->map(fn ($category) => [
                'name' => $category->name,
                'count' => (int) $category->booking_count,
            ])
            ->values()
            ->all();

        $userCounts = User::query()
            ->selectRaw('COUNT(*) as total_users')
            ->selectRaw("SUM(role = 'worker') as total_workers")
            ->selectRaw("SUM(role = 'client') as total_clients")
            ->first();
        $bookingCounts = Booking::query()
            ->selectRaw('COUNT(*) as total_bookings')
            ->selectRaw("SUM(status = 'active') as active_bookings")
            ->selectRaw("SUM(status = 'completed') as completed_bookings")
            ->selectRaw("SUM(status = 'completed' AND DATE(updated_at) = ?) as completed_bookings_today", [$today])
            ->selectRaw("SUM(status = 'completed' AND DATE(updated_at) = ?) as completed_bookings_yesterday", [$yesterday])
            ->first();
        $reportCounts = Report::query()
            ->selectRaw('COUNT(*) as total_reports')
            ->selectRaw("SUM(status = 'under_review') as open_reports")
            ->first();
        $activeJobPosts = JobPost::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->count();

        return [
            'total_users' => (int) $userCounts->total_users,
            'total_workers' => (int) $userCounts->total_workers,
            'total_clients' => (int) $userCounts->total_clients,
            'pending_verifications' => WorkerProfile::where('verification_status', 'pending')->count(),
            'total_bookings' => (int) $bookingCounts->total_bookings,
            'active_bookings' => (int) $bookingCounts->active_bookings,
            'completed_bookings' => (int) $bookingCounts->completed_bookings,
            'completed_bookings_today' => (int) $bookingCounts->completed_bookings_today,
            'completed_bookings_yesterday' => (int) $bookingCounts->completed_bookings_yesterday,
            'total_reports' => (int) $reportCounts->total_reports,
            'open_reports' => (int) $reportCounts->open_reports,
            'open_disputes' => (int) $reportCounts->open_reports,
            'total_active_job_posts' => $activeJobPosts,
            'activity_trend' => $activityTrend,
            'top_service_categories' => $topServiceCategories,
            'recent_activity' => $recentActivity,
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
            'trust_tier' => $tier,
            'verification_remarks' => $remarks,
        ]);

        $notificationMessage = $tier === 'revoked'
            ? 'Your trust tier has been revoked. Your job posts will no longer appear in the client home feed.'
            : "Your trust tier has been updated to {$tier}.";

        $this->audit($admin, 'update_trust_tier', 'WorkerProfile', $profile->id, [
            'worker_id' => $profile->user_id,
            'previous_tier' => $previous,
            'new_tier' => $tier,
            'remarks' => $remarks,
        ]);

        $this->notificationService->notify(
            $profile->user,
            'Trust Tier Updated',
            $notificationMessage,
            'trust_tier_updated',
            [
                'type' => 'trust_tier_updated',
                'tier' => $tier,
                'posts_hidden_from_client_feed' => $tier === 'revoked',
            ],
        );

        return $profile->load('user');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SECTION 7 — FORCE CANCEL BOOKING
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Force-cancel a booking by an admin. Allowed on any non-terminal status.
     *
     * @throws BusinessRuleException
     */
    public function forceCancelBooking(User $admin, int $bookingId, string $reason): Booking
    {
        $booking = Booking::with(['client', 'worker'])->findOrFail($bookingId);

        $terminal = ['completed', 'cancelled'];

        if (in_array($booking->status, $terminal, true)) {
            throw new BusinessRuleException(
                "Booking is already {$booking->status} and cannot be cancelled."
            );
        }

        $booking->update([
            'status' => 'cancelled',
            'cancelled_by' => 'admin',
            'cancellation_reason' => $reason,
            'force_cancelled_by' => $admin->id,
        ]);

        $this->audit($admin, 'force_cancel_booking', 'Booking', $booking->id, [
            'booking_code' => $booking->booking_code,
            'client_id' => $booking->client_id,
            'worker_id' => $booking->worker_id,
            'reason' => $reason,
            'previous_status' => $booking->getOriginal('status'),
            'new_status' => 'cancelled',
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
     * List all ratings with optional filters: rated_user_id, worker_id, client_id, score, direction, search.
     */
    public function listRatings(?int $workerId, ?int $clientId, ?int $score = null, ?string $direction = null, ?string $search = null, ?int $ratedUserId = null): LengthAwarePaginator
    {
        $query = RatingReview::query()
            ->with([
                'booking:id,booking_code',
                'ratedByUser:id,name,profile_photo_path',
                'ratedUser:id,name,profile_photo_path',
            ])
            ->orderByDesc('created_at');

        if ($workerId !== null) {
            $query->where('rated_user', $workerId);
        }

        if ($clientId !== null) {
            $query->where('rated_by', $clientId);
        }

        if ($ratedUserId !== null) {
            $query->where('rated_user', $ratedUserId);
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
                    ->orWhereHas('ratedUser', fn ($iq) => $iq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('booking', fn ($iq) => $iq->where('booking_code', 'like', "%{$search}%"));
            });
        }

        return $query->paginate(15);
    }

    public function listRatingRecipients(
        ?string $role,
        ?string $search,
        string $sortBy = 'average_rating',
        string $sortDirection = 'desc'
    ): LengthAwarePaginator {
        $query = User::query()
            ->with('barangay:id,name')
            ->whereNotIn('role', ['admin', 'super_admin'])
            ->withCount('receivedRatings')
            ->withAvg('receivedRatings', 'score');

        if ($role !== null) {
            $query->where('role', $role);
        }

        if ($search !== null) {
            $query->where(function ($userQuery) use ($search): void {
                $userQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $query->orderBy(
            $sortBy === 'average_rating' ? 'received_ratings_avg_score' : 'name',
            $sortDirection
        )->orderBy('name');

        return $query->paginate(15);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SECTION 9 — AUDIT LOGS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return paginated audit logs, optionally filtered by admin_id, action, target_type, date range.
     */
    public function listAuditLogs(
        ?int $adminId,
        ?string $action,
        ?string $targetType = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $adminName = null,
        ?string $category = null,
        ?string $outcome = null,
        ?string $ipAddress = null,
    ): LengthAwarePaginator {
        return $this->auditLogsQuery(
            $adminId,
            $action,
            $targetType,
            $dateFrom,
            $dateTo,
            $adminName,
            $category,
            $outcome,
            $ipAddress,
        )->paginate(25);
    }

    public function auditLogsForExport(
        ?int $adminId,
        ?string $action,
        ?string $targetType = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $adminName = null,
        ?string $category = null,
        ?string $outcome = null,
        ?string $ipAddress = null,
    ): LazyCollection {
        return $this->auditLogsQuery(
            $adminId,
            $action,
            $targetType,
            $dateFrom,
            $dateTo,
            $adminName,
            $category,
            $outcome,
            $ipAddress,
        )->reorder()->lazyById(500, 'admin_audit_logs.id');
    }

    private function auditLogsQuery(
        ?int $adminId,
        ?string $action,
        ?string $targetType,
        ?string $dateFrom,
        ?string $dateTo,
        ?string $adminName,
        ?string $category,
        ?string $outcome,
        ?string $ipAddress,
    ): Builder {
        $query = AdminAuditLog::query()
            ->with('admin:id,name,email')
            ->orderByDesc('created_at');

        if ($adminId !== null) {
            $query->where('admin_id', $adminId);
        }

        if ($adminName !== null) {
            $query->where(function ($subQuery) use ($adminName): void {
                $subQuery->where('actor_name', 'like', "%{$adminName}%")
                    ->orWhere('actor_email', 'like', "%{$adminName}%")
                    ->orWhereHas('admin', fn ($adminQuery) => $adminQuery
                        ->where('name', 'like', "%{$adminName}%")
                        ->orWhere('email', 'like', "%{$adminName}%"));
            });
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

        if ($category !== null) {
            $query->where('category', $category);
        }

        if ($outcome !== null) {
            $query->where('outcome', $outcome);
        }

        if ($ipAddress !== null) {
            $query->where('ip_address', 'like', "%{$ipAddress}%");
        }

        return $query;
    }

    public function deleteAuditLog(int|string $id, User $admin): void
    {
        $this->assertCanDeleteAuditLogs($admin);
        AdminAuditLog::findOrFail($id)->delete();
    }

    public function deleteAllAuditLogs(User $admin): int
    {
        $this->assertCanDeleteAuditLogs($admin);

        return AdminAuditLog::query()->delete();
    }

    private function assertCanDeleteAuditLogs(User $admin): void
    {
        if (! $admin->isSuperAdmin()) {
            throw new BusinessRuleException(
                'Only the super admin can delete audit and security logs.',
                403,
            );
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SECTION 10 — PLATFORM SETTINGS (SERVICE CATEGORIES)
    // ──────────────────────────────────────────────────────────────────────────
    /**
     * List all service categories (admin view — includes inactive).
     */
    public function listCategories(): Collection
    {
        return ServiceCategory::orderBy('name')->get(['id', 'name', 'icon', 'is_active']);
    }

    /**
     * Create a new service category.
     */
    public function createCategory(User $admin, array $data): ServiceCategory
    {
        $category = ServiceCategory::create([
            'name' => $data['name'],
            'icon' => $data['icon'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->audit($admin, 'create_category', 'ServiceCategory', $category->id, [
            'name' => $category->name,
            'icon' => $category->icon,
            'is_active' => $category->is_active,
        ]);

        return $category;
    }

    /**
     * Update an existing service category.
     *
     * @throws BusinessRuleException
     */
    public function updateCategory(User $admin, int $categoryId, array $data): ServiceCategory
    {
        $category = ServiceCategory::findOrFail($categoryId);

        $before = $category->getAttributes();
        $category->update(array_filter($data, fn ($v) => $v !== null));

        $this->audit($admin, 'update_category', 'ServiceCategory', $category->id, [
            'changes' => $this->changesFor($before, $category->getAttributes(), array_keys($data)),
        ]);

        return $category->fresh();
    }

    /**
     * Delete a service category from the database.
     */
    public function deleteCategory(User $admin, int $categoryId): bool
    {
        $category = ServiceCategory::findOrFail($categoryId);

        $this->audit($admin, 'delete_category', 'ServiceCategory', $category->id, [
            'name' => $category->name,
            'icon' => $category->icon,
        ]);

        return $category->delete();
    }

    /**
     * List report reasons configured by admins.
     */
    public function listReportReasons(?string $audience = null): Collection
    {
        return ReportReason::when($audience, fn ($query) => $query->where('audience', $audience))->orderBy('label')->get();
    }

    /**
     * Create a report reason.
     */
    public function createReportReason(User $admin, array $data): ReportReason
    {
        $payload = [
            'code' => $data['code'] ?? strtolower(str_replace([' ', '-'], '_', trim($data['label']))),
            'label' => trim($data['label']),
            'description' => $data['description'] ?? null,
            'audience' => $data['audience'] ?? 'client',
            'is_active' => $data['is_active'] ?? true,
        ];

        $payload['code'] = preg_replace('/[^a-z0-9_]/', '', strtolower($payload['code'])) ?: 'other';

        $reason = ReportReason::create($payload);

        $this->audit($admin, 'create_report_reason', 'ReportReason', $reason->id, $payload);

        return $reason->fresh();
    }

    /**
     * Update an existing report reason.
     */
    public function updateReportReason(User $admin, int $reasonId, array $data): ReportReason
    {
        $reason = ReportReason::findOrFail($reasonId);

        if (isset($data['label'])) {
            $data['label'] = trim($data['label']);

            if (! isset($data['code']) || trim((string) $data['code']) === '') {
                $data['code'] = preg_replace('/[^a-z0-9_]/', '', strtolower(str_replace([' ', '-'], '_', $data['label'])));
            }
        }

        if (isset($data['code'])) {
            $data['code'] = preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string) $data['code'])));
        }

        $before = $reason->getAttributes();
        $reason->update(array_filter($data, fn ($v) => $v !== null));

        $this->audit($admin, 'update_report_reason', 'ReportReason', $reason->id, [
            'changes' => $this->changesFor($before, $reason->getAttributes(), array_keys($data)),
        ]);

        return $reason->fresh();
    }

    /**
     * Delete a report reason.
     */
    public function deleteReportReason(User $admin, int $reasonId): bool
    {
        $reason = ReportReason::findOrFail($reasonId);

        $this->audit($admin, 'delete_report_reason', 'ReportReason', $reason->id, [
            'code' => $reason->code,
            'label' => $reason->label,
        ]);

        return $reason->delete();
    }

    /**
     * GET /admin/settings — aggregate all platform settings into one response.
     */
    public function getSettings(): array
    {
        $activeAnnouncement = Announcement::query()
            ->where('status', 'published')
            ->where('is_active', true)
            ->active()
            ->latest()
            ->first();

        return [
            'service_categories' => ServiceCategory::orderBy('name')->get(['id', 'name', 'icon', 'is_active']),
            'report_reasons' => ReportReason::query()
                ->orderBy('label')
                ->pluck('code')
                ->all(),
            'notification_templates' => [
                'booking_accepted' => 'Your booking {booking_code} has been accepted.',
                'booking_declined' => 'Your booking {booking_code} has been declined.',
                'booking_completed' => 'Your booking {booking_code} has been completed.',
                'verification_approved' => 'Your account has been verified. You can now receive bookings.',
                'verification_rejected' => 'Your verification was rejected. Please resubmit your documents.',
                'new_review' => 'You received a new review!',
            ],
            'active_announcement' => $activeAnnouncement ? [
                'id' => $activeAnnouncement->id,
                'title' => $activeAnnouncement->title,
                'body' => $activeAnnouncement->body,
                'expires_at' => $activeAnnouncement->expires_at?->toDateString(),
                'posted_at' => $activeAnnouncement->created_at?->toIso8601String(),
            ] : null,
        ];
    }

    public function listAnnouncements(): Collection
    {
        return Announcement::query()
            ->orderByDesc('created_at')
            ->get([
                'id',
                'posted_by',
                'title',
                'body',
                'status',
                'target_audience',
                'expires_at',
                'is_active',
                'created_at',
                'updated_at',
            ]);
    }

    public function createAnnouncement(User $admin, array $attributes): Announcement
    {
        $announcement = Announcement::create([
            'posted_by' => $admin->id,
            'title' => $attributes['title'],
            'body' => $attributes['body'],
            'status' => $attributes['status'] ?? 'draft',
            'target_audience' => $attributes['target_audience'] ?? 'all',
            'expires_at' => $attributes['expires_at'] ?? null,
            'is_active' => ($attributes['status'] ?? 'draft') !== 'archived',
        ]);

        $this->audit($admin, 'create_announcement', 'Announcement', $announcement->id, [
            'title' => $announcement->title,
            'body' => $announcement->body,
            'status' => $announcement->status,
            'target_audience' => $announcement->target_audience,
            'expires_at' => $announcement->expires_at?->toIso8601String(),
        ]);

        if ($announcement->status === 'published') {
            User::where('is_active', true)
                ->whereNotNull('email_verified_at')
                ->whereNull('deleted_at')
                ->chunk(200, function ($users) use ($announcement): void {
                    foreach ($users as $user) {
                        $this->notificationService->notify(
                            $user,
                            $announcement->title,
                            $announcement->body,
                            'system_announcement',
                        );
                    }
                });
        }

        return $announcement;
    }

    public function updateAnnouncement(User $admin, int|string $announcementId, array $attributes): Announcement
    {
        $announcement = Announcement::findOrFail($announcementId);
        $before = $announcement->getAttributes();
        $announcement->fill($attributes);

        if (isset($attributes['status'])) {
            $announcement->is_active = $attributes['status'] !== 'archived';
        }

        $announcement->save();

        $changedFields = array_keys($attributes);
        if (array_key_exists('status', $attributes)) {
            $changedFields[] = 'is_active';
        }
        $this->audit($admin, 'update_announcement', 'Announcement', $announcement->id, [
            'changes' => $this->changesFor($before, $announcement->getAttributes(), array_unique($changedFields)),
        ]);

        return $announcement->fresh();
    }

    public function deleteAnnouncement(User $admin, int|string $announcementId): void
    {
        $announcement = Announcement::findOrFail($announcementId);
        $this->audit($admin, 'delete_announcement', 'Announcement', (int) $announcementId, [
            'title' => $announcement->title,
            'status' => $announcement->status,
            'target_audience' => $announcement->target_audience,
        ]);
        $announcement->delete();
    }

    /**
     * POST /admin/settings with action=post_announcement — publish a system-wide announcement.
     * Also creates in-app notifications for all active users.
     */
    public function postAnnouncement(User $admin, string $title, string $body, ?string $expiresAt): Announcement
    {
        $announcement = Announcement::create([
            'posted_by' => $admin->id,
            'title' => $title,
            'body' => $body,
            'status' => 'published',
            'target_audience' => 'all',
            'expires_at' => $expiresAt,
            'is_active' => true,
        ]);

        $this->audit($admin, 'post_announcement', 'Announcement', $announcement->id, [
            'title' => $title,
            'body' => $body,
            'status' => $announcement->status,
            'target_audience' => $announcement->target_audience,
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
    public function listJobPosts(
        ?int $categoryId,
        ?int $workerProfileId,
        ?int $barangayId = null,
        ?string $status = null,
        ?string $verification = null,
        ?string $search = null,
    ): LengthAwarePaginator {
        $query = JobPost::withTrashed()
            ->with([
                'workerProfile.user:id,name,barangay_id',
                'workerProfile.user.barangay:id,name',
                'serviceCategory:id,name',
            ])
            ->orderByDesc('created_at');

        if ($categoryId !== null) {
            $query->where('service_category_id', $categoryId);
        }

        if ($workerProfileId !== null) {
            $query->where('worker_profile_id', $workerProfileId);
        }

        if ($barangayId !== null) {
            $query->whereHas('workerProfile.user', fn ($userQuery) => $userQuery->where('barangay_id', $barangayId)
            );
        }

        if ($status === 'active') {
            $query->where('is_active', true)->whereNull('deleted_at');
        } elseif ($status === 'inactive') {
            $query->where(function ($statusQuery): void {
                $statusQuery->where('is_active', false)->orWhereNotNull('deleted_at');
            });
        }

        if ($verification === 'verified') {
            $query->whereHas('workerProfile', fn ($profileQuery) => $profileQuery->where('verification_status', 'approved')
            );
        } elseif ($verification === 'unverified') {
            $query->whereHas('workerProfile', fn ($profileQuery) => $profileQuery->where('verification_status', '!=', 'approved')
            );
        }

        if ($search !== null) {
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery->where('title', 'like', "%{$search}%")
                    ->orWhereHas('workerProfile.user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%")
                    );
            });
        }

        return $query->paginate(15);
    }

    public function getJobPost(int|string $postId): JobPost
    {
        return JobPost::withTrashed()
            ->with([
                'serviceCategory:id,name,icon',
                'images',
                'workerProfile.user:id,name,email,mobile_number,barangay_id,profile_photo_path',
                'workerProfile.user.barangay:id,name',
            ])
            ->withCount('bookings')
            ->findOrFail($postId);
    }

    public function createJobPost(User $admin, array $data): JobPost
    {
        $profile = WorkerProfile::with('user')->findOrFail($data['worker_profile_id']);
        $replacedPostIds = $profile->jobPosts()
            ->where('service_category_id', $data['service_category_id'])
            ->get()
            ->each(fn (JobPost $existingPost) => $existingPost->delete())
            ->pluck('id')
            ->all();

        $post = $profile->jobPosts()->create([
            'service_category_id' => $data['service_category_id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'rate_amount' => $data['rate_amount'],
            'rate_type' => $data['rate_type'],
            'is_available' => $data['is_available'],
            'is_active' => $data['is_active'] ?? true,
        ]);
        $profile->serviceCategories()->syncWithoutDetaching([$data['service_category_id']]);

        $this->audit($admin, 'create_job_post', 'JobPost', $post->id, [
            'worker_profile_id' => $profile->id,
            'service_category_id' => $post->service_category_id,
            'title' => $post->title,
            'description' => $post->description,
            'rate_amount' => $post->rate_amount,
            'rate_type' => $post->rate_type,
            'is_available' => $post->is_available,
            'is_active' => $post->is_active,
            'replaced_job_post_ids' => $replacedPostIds,
        ]);
        $this->notifyWorkerOfJobPostChange($profile->user, $post, 'created');

        return $post;
    }

    public function updateJobPost(User $admin, int $postId, array $data): JobPost
    {
        $post = JobPost::withTrashed()->with('workerProfile.user')->findOrFail($postId);
        $before = $post->getRawOriginal();
        $previousCategoryId = $post->service_category_id;
        $post->update([
            'service_category_id' => $data['service_category_id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'rate_amount' => $data['rate_amount'],
            'rate_type' => $data['rate_type'],
            'is_available' => $data['is_available'],
            'is_active' => $data['is_active'] ?? $post->is_active,
        ]);

        if ((int) $previousCategoryId !== (int) $data['service_category_id']) {
            $post->workerProfile->serviceCategories()->syncWithoutDetaching([$data['service_category_id']]);
        }

        $this->audit($admin, 'update_job_post', 'JobPost', $post->id, [
            'worker_profile_id' => $post->worker_profile_id,
            'changes' => $this->changesFor($before, $post->getAttributes(), array_keys($data)),
        ]);
        $this->notifyWorkerOfJobPostChange($post->workerProfile->user, $post, 'updated');

        return $post->fresh(['serviceCategory', 'images']);
    }

    private function notifyWorkerOfJobPostChange(User $worker, JobPost $post, string $action): void
    {
        $title = $action === 'created' ? 'A job post was added' : 'Job post updated';
        $body = $action === 'created'
            ? "An administrator added a job post to your profile: {$post->title}."
            : "An administrator updated your job post: {$post->title}.";
        $data = ['job_post_id' => (string) $post->id];

        $this->notificationService->notify($worker, $title, $body, "job_post_{$action}", $data);
    }

    /**
     * Force-delete (hard delete) a job post as admin.
     */
    public function forceDeleteJobPost(User $admin, int $postId, string $reason): void
    {
        $this->forceDeleteJobPosts($admin, [$postId], $reason);
    }

    public function forceDeleteJobPosts(User $admin, array $postIds, string $reason): void
    {
        $posts = JobPost::withTrashed()
            ->with('workerProfile.user')
            ->whereIn('id', $postIds)
            ->get();

        foreach ($posts as $post) {
            $this->audit($admin, 'delete_job_post', 'JobPost', $post->id, [
                'title' => $post->title,
                'worker_profile_id' => $post->worker_profile_id,
                'reason' => $reason,
            ]);

            if ($post->workerProfile?->user) {
                $this->notificationService->notify(
                    $post->workerProfile->user,
                    'Job post removed',
                    $reason,
                    'job_post_removed',
                    ['job_post_id' => $post->id],
                );
            }

            $post->forceDelete();
        }
    }
}
