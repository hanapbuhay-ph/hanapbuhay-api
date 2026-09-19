<?php

namespace App\Services\Report;

use App\Models\Booking;
use App\Models\Report;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
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
        $expectedId     = $reporter->id === $booking->client_id
            ? $booking->worker_id
            : $booking->client_id;

        if ($reportedUserId !== $expectedId) {
            throw new RuntimeException('You can only report the other party on this booking.');
        }

        $evidencePaths = null;

        if (!empty($photos)) {
            try {
                $stored = [];
                foreach ($photos as $photo) {
                    /** @var UploadedFile $photo */
                    $stored[] = $photo->store("reports/{$booking->id}", 'public');
                }
                $evidencePaths = $stored;
            } catch (\Throwable $e) {
                throw new RuntimeException('Failed to store evidence files.');
            }
        }

        $report = Report::create([
            'booking_id'     => $booking->id,
            'reported_by'    => $reporter->id,
            'reported_user'  => $reportedUserId,
            'reason'         => $data['reason'],
            'description'    => $data['description'],
            'evidence_paths' => $evidencePaths,
            'status'         => 'under_review',
        ]);

        $reportedUser = User::find($reportedUserId);
        if ($reportedUser !== null) {
            $title = 'New report received';
            $body = "A report has been submitted about your account for booking #{$booking->booking_code}.";
            $data = [
                'type' => 'report_received',
                'report_id' => (string) $report->id,
                'booking_id' => (string) $booking->id,
            ];

            $this->notifications->notify($reportedUser, $title, $body, 'report_received', $data);
            $this->notifications->sendPush($reportedUser, $title, $body, $data);
        }

        return $report;
    }

    public function listForUser(User $user, string $view = 'all'): LengthAwarePaginator
    {
        return Report::with([
                'booking:id,booking_code',
                'reporter:id,name,profile_photo_path',
                'reportedUser:id,name,profile_photo_path',
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
            ])
            ->findOrFail($id);
    }
}
