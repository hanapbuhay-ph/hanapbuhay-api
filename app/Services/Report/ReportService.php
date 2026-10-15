<?php

namespace App\Services\Report;

use App\Models\Booking;
use App\Models\Report;
use App\Models\User;
use App\Services\AdminNotificationService;
use App\Services\Media\MediaStorageService;
use App\Services\Notification\NotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class ReportService
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @throws RuntimeException
     */
    public function create(array $data, array $photos, User $reporter): Report
    {
        $booking = Booking::findOrFail($data['booking_id']);

        $reportedUserId = (int) $data['reported_user_id'];
        $expectedId = $reporter->id === $booking->client_id
            ? $booking->worker_id
            : $booking->client_id;

        if ($reportedUserId !== $expectedId) {
            throw new RuntimeException('You can only report the other party on this booking.');
        }

        return $this->storeReport($booking, $reporter, User::findOrFail($reportedUserId), $data, $photos);
    }

    public function createOnBehalfOfUser(User $admin, array $data, array $photos): Report
    {
        $booking = Booking::with(['client', 'worker'])->findOrFail($data['booking_id']);
        $isClientReporter = $data['reporter_role'] === 'client';
        $reporter = $isClientReporter ? $booking->client : $booking->worker;
        $reportedUser = $isClientReporter ? $booking->worker : $booking->client;

        if ($reporter === null || $reportedUser === null) {
            throw new RuntimeException('The booking must have both a client and a worker to be reported.');
        }

        return $this->storeReport($booking, $reporter, $reportedUser, $data, $photos, $admin);
    }

    private function storeReport(
        Booking $booking,
        User $reporter,
        User $reportedUser,
        array $data,
        array $photos,
        ?User $submittedByAdmin = null,
    ): Report {
        $evidencePaths = null;

        if (! empty($photos)) {
            try {
                $stored = [];
                foreach ($photos as $photo) {
                    /** @var UploadedFile $photo */
                    $stored[] = app(MediaStorageService::class)->storePrivateFile(
                        $photo,
                        "reports/{$booking->id}/".Str::uuid().'.'.$photo->getClientOriginalExtension(),
                    );
                }
                $evidencePaths = $stored;
            } catch (\Throwable $e) {
                Log::error('Report evidence upload failed', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
                throw new RuntimeException('Failed to store evidence files.');
            }
        }

        $report = Report::create([
            'booking_id' => $booking->id,
            'reported_by' => $reporter->id,
            'reported_user' => $reportedUser->id,
            'submitted_by_admin_id' => $submittedByAdmin?->id,
            'reason' => $data['reason'],
            'description' => $data['description'],
            'evidence_paths' => $evidencePaths,
            'status' => 'under_review',
        ]);

        if ($submittedByAdmin === null) {
            app(AdminNotificationService::class)->create(
                'new_report',
                'New report submitted',
                "{$reporter->name} submitted a report for booking #{$booking->booking_code}.",
                ['url' => "/reports/{$report->id}"],
            );
        }

        $title = 'New report received';
        $body = "A report has been submitted about your account for booking #{$booking->booking_code}.";
        $notificationData = [
            'type' => 'report_received',
            'report_id' => (string) $report->id,
            'booking_id' => (string) $booking->id,
        ];

        $this->notifications->notify($reportedUser, $title, $body, 'report_received', $notificationData);

        if ($submittedByAdmin !== null) {
            $reporterTitle = 'A report was submitted on your behalf';
            $reporterBody = "An admin submitted a report for booking #{$booking->booking_code}.";
            $reporterData = [
                'type' => 'report_submitted_on_behalf',
                'report_id' => (string) $report->id,
                'booking_id' => (string) $booking->id,
            ];
            $this->notifications->notify($reporter, $reporterTitle, $reporterBody, 'report_submitted_on_behalf', $reporterData);
        }

        return $report;
    }

    public function listForUser(User $user, string $view = 'all'): LengthAwarePaginator
    {
        return Report::with([
            'booking:id,booking_code',
            'reporter:id,name,profile_photo_path',
            'reportedUser:id,name,profile_photo_path',
            'submittedByAdmin:id,name',
            'resolvedBy:id,name',
            'reportReason:code,label',
        ])
            ->when($view === 'submitted', fn ($query) => $query->where('reported_by', $user->id))
            ->when($view === 'received', fn ($query) => $query->where('reported_user', $user->id))
            ->when($view === 'all', fn ($query) => $query->where(fn ($q) => $q->where('reported_by', $user->id)->orWhere('reported_user', $user->id)))
            ->orderByDesc('created_at')
            ->paginate(15);
    }

    public function find(int $id): Report
    {
        return Report::with([
            'booking:id,booking_code',
            'reporter:id,name,profile_photo_path',
            'reportedUser:id,name,profile_photo_path',
            'submittedByAdmin:id,name',
        ])
            ->findOrFail($id);
    }
}
