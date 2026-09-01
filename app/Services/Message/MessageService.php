<?php

namespace App\Services\Message;

use App\Events\NewMessage;
use App\Models\Booking;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class MessageService
{
    /**
     * GET /api/messages — chat inbox.
     * Returns one row per booking that the user has exchanged messages on,
     * ordered by most recent message first.
     */
    public function inbox(User $user): array
    {
        // Find all booking IDs the user is part of that have at least one message
        $bookingIds = Booking::where(function ($q) use ($user) {
            $q->where('client_id', $user->id)
              ->orWhere('worker_id', $user->id);
        })
        ->whereHas('messages')
        ->pluck('id');

        $conversations = [];

        foreach ($bookingIds as $bookingId) {
            $booking = Booking::with(['client:id,name,profile_photo_path', 'worker:id,name,profile_photo_path'])
                ->find($bookingId);

            $lastMessage = Message::where('booking_id', $bookingId)
                ->latest()
                ->first();

            $unreadCount = Message::where('booking_id', $bookingId)
                ->where('receiver_id', $user->id)
                ->where('is_read', false)
                ->count();

            // Determine the "other party" from this user's perspective
            $otherParty = $user->id === $booking->client_id ? $booking->worker : $booking->client;

            $conversations[] = [
                'booking_id'      => $booking->id,
                'booking_code'    => $booking->booking_code,
                'booking_status'  => $booking->status,
                'other_party'     => [
                    'id'                => $otherParty?->id,
                    'name'              => $otherParty?->name,
                    'profile_photo_url' => $otherParty?->profile_photo_path
                        ? asset('storage/' . $otherParty->profile_photo_path)
                        : null,
                ],
                'last_message'    => $lastMessage?->content,
                'last_message_at' => $lastMessage?->created_at?->toIso8601String(),
                'unread_count'    => $unreadCount,
            ];
        }

        // Sort by last_message_at descending
        usort($conversations, function ($a, $b) {
            $aTime = $a['last_message_at'] ?? '';
            $bTime = $b['last_message_at'] ?? '';
            return strcmp($bTime, $aTime); // descending: b before a
        });

        return $conversations;
    }

    /**
     * GET /api/messages/{bookingId} — message thread.
     * Also marks all unread messages sent to this user as read.
     */
    public function thread(Booking $booking, User $viewer): LengthAwarePaginator
    {
        // Mark all unread messages from the other party as read
        Message::where('booking_id', $booking->id)
            ->where('receiver_id', $viewer->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => Carbon::now(),
            ]);

        return Message::with('sender:id,name')
            ->where('booking_id', $booking->id)
            ->orderBy('created_at')
            ->paginate(30);
    }

    /**
     * Legacy method — kept for backwards compat with /bookings/{id}/messages
     */
    public function index(Booking $booking): LengthAwarePaginator
    {
        return $this->thread($booking, auth()->user());
    }

    /**
     * Send a message, optionally with a file attachment.
     *
     * @throws RuntimeException
     */
    public function store(Booking $booking, User $sender, ?string $content, ?UploadedFile $attachment = null): Message
    {
        if (in_array($booking->status, ['cancelled', 'declined'], true)) {
            throw new RuntimeException('You cannot send messages on an inactive booking.');
        }

        $receiverId = $sender->id === $booking->client_id
            ? $booking->worker_id
            : $booking->client_id;

        $attachmentPath = null;

        if ($attachment !== null) {
            try {
                $ext            = $attachment->getClientOriginalExtension();
                $filename       = "messages/{$booking->id}/" . uniqid('msg_', true) . ".{$ext}";
                $attachmentPath = Storage::disk('public')->putFileAs(
                    "messages/{$booking->id}",
                    $attachment,
                    basename($filename),
                );
            } catch (Throwable $e) {
                Log::error('Message attachment upload failed', [
                    'booking_id' => $booking->id,
                    'sender_id'  => $sender->id,
                    'error'      => $e->getMessage(),
                ]);
                throw new RuntimeException('Attachment upload failed. Please try again.');
            }
        }

        $message = Message::create([
            'booking_id'      => $booking->id,
            'sender_id'       => $sender->id,
            'receiver_id'     => $receiverId,
            'content'         => $content ?? '',
            'attachment_path' => $attachmentPath,
            'is_read'         => false,
        ]);

        $message->load('sender:id,name');

        NewMessage::dispatch(
            $booking->id,
            $sender->id,
            $sender->name,
            $content ?? '',
            $message->created_at->toISOString(),
        );

        return $message;
    }
}
