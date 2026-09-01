<?php

namespace App\Services\Report;

use App\Models\Booking;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use RuntimeException;

class ReportService
{
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

        return Report::create([
            'booking_id'     => $booking->id,
            'reported_by'    => $reporter->id,
            'reported_user'  => $reportedUserId,
            'reason'         => $data['reason'],
            'description'    => $data['description'],
            'evidence_paths' => $evidencePaths,
            'status'         => 'under_review',
        ]);
    }

    public function listForUser(User $user): LengthAwarePaginator
    {
        return Report::with([
                'booking:id,booking_code',
                'reportedUser:id,name',
            ])
            ->where('reported_by', $user->id)
            ->orderByDesc('created_at')
            ->paginate(15);
    }

    public function find(int $id): Report
    {
        return Report::with([
                'booking:id,booking_code',
                'reportedUser:id,name',
            ])
            ->findOrFail($id);
    }
}
