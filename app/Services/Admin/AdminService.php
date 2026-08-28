<?php

namespace App\Services\Admin;

use App\Models\Booking;
use App\Models\Report;
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
     * Approve or reject a worker's verification. Notifies the worker after commit.
     */
    public function reviewVerification(int $workerProfileId, string $action, ?string $adminNotes): WorkerProfile
    {
        $workerProfile = WorkerProfile::findOrFail($workerProfileId);

        $newStatus = $action === 'approve' ? 'approved' : 'rejected';

        DB::transaction(function () use ($workerProfile, $newStatus): void {
            $workerProfile->update(['verification_status' => $newStatus]);

            $workerProfile->verificationDocuments()->update(['status' => $newStatus]);
        });

        // Reload the user relationship for the push notification
        $workerProfile->load('user');

        if ($action === 'approve') {
            $this->notificationService->sendPush(
                $workerProfile->user,
                'Verification Approved',
                'Your account has been verified. You can now receive bookings.',
                ['type' => 'verification_approved'],
            );
        } else {
            $this->notificationService->sendPush(
                $workerProfile->user,
                'Verification Rejected',
                'Your verification was rejected. Please resubmit your documents.',
                ['type' => 'verification_rejected'],
            );
        }

        return $workerProfile;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SECTION 2 — USER MANAGEMENT
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * List all users with optional filters for role, is_active, and search.
     */
    public function listUsers(?string $role, ?string $isActive, ?string $search): LengthAwarePaginator
    {
        $query = User::query()
            ->with('barangay:id,name')
            ->orderByDesc('created_at');

        if ($role !== null) {
            $query->where('role', $role);
        }

        if ($isActive !== null) {
            $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
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
     * List all bookings, optionally filtered by status.
     */
    public function listBookings(?string $status): LengthAwarePaginator
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
     */
    public function resolveReport(int $id, string $status, ?string $adminNotes): Report
    {
        $report = Report::findOrFail($id);

        $report->update([
            'status'       => $status,
            'admin_remarks' => $adminNotes,
        ]);

        return $report;
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
}
