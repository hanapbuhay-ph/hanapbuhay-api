<?php

namespace App\Services\Notification;

use App\Mail\NotificationEmail;
use App\Models\DeviceToken;
use App\Models\HanapbuhayNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class NotificationService
{
    /**
     * Create in-app notifications and deliver them to opted-in devices.
     * Chat messages are push-only; booking updates may also queue an email.
     */
    public function notify(User $user, string $title, string $body, string $type, array $data = []): ?HanapbuhayNotification
    {
        if (! $this->categoryEnabled($user, $type)) {
            return null;
        }

        if ($this->isMessageNotification($type)) {
            $this->sendPush($user, $title, $body, array_merge($data, ['type' => $type]));

            return null;
        }

        $notification = HanapbuhayNotification::create([
            'user_id' => $user->id,
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'data' => $data ?: null,
            'is_read' => false,
        ]);

        if ($this->isBookingUpdate($type) && ($user->notify_email ?? true) && $user->email) {
            try {
                Mail::to($user->email)->queue(
                    (new NotificationEmail($title, $body))->afterCommit(),
                );
            } catch (\Throwable $exception) {
                Log::error('Failed to queue booking notification email', [
                    'user_id' => $user->id,
                    'type' => $type,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->sendPush($user, $title, $body, array_merge($data, ['type' => $type]));

        return $notification;
    }

    /**
     * Send a Firebase push notification to all opted-in registered devices.
     */
    public function sendPush(User $user, string $title, string $body = '', array $data = []): void
    {
        $type = (string) ($data['type'] ?? '');
        if (! ($user->notify_push ?? true) || ! $this->categoryEnabled($user, $type)) {
            return;
        }

        $tokens = DeviceToken::where('user_id', $user->id)->get();

        if ($tokens->isEmpty()) {
            return;
        }

        $notification = Notification::create($title, $body);

        try {
            $messaging = app(Messaging::class);
        } catch (\Throwable $exception) {
            Log::warning('Firebase messaging is unavailable', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        foreach ($tokens as $token) {
            try {
                $messageData = [];
                foreach ($data as $key => $value) {
                    if (is_scalar($value)) {
                        $messageData[(string) $key] = (string) $value;
                    }
                }

                $message = CloudMessage::new()
                    ->withToken($token->fcm_token)
                    ->withNotification($notification)
                    ->withData($messageData);

                $messaging->send($message);
            } catch (MessagingException $exception) {
                Log::warning('Firebase push delivery failed', [
                    'user_id' => $user->id,
                    'error' => $exception->getMessage(),
                ]);
                $token->delete();
            } catch (\Throwable $exception) {
                Log::error('Unexpected Firebase push delivery error', [
                    'user_id' => $user->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function categoryEnabled(User $user, string $type): bool
    {
        if ($this->isBookingUpdate($type)) {
            return (bool) ($user->notify_booking_updates ?? true);
        }

        if ($this->isMessageNotification($type)) {
            return (bool) ($user->notify_messages ?? true);
        }

        if (str_contains($type, 'promotion') || str_contains($type, 'announcement')) {
            return (bool) ($user->notify_promotions ?? false);
        }

        return true;
    }

    private function isBookingUpdate(string $type): bool
    {
        return str_starts_with($type, 'booking_')
            || str_starts_with($type, 'tracking_')
            || in_array($type, ['new_rating', 'new_review'], true);
    }

    private function isMessageNotification(string $type): bool
    {
        return in_array($type, ['new_message', 'new_chat_message', 'message'], true);
    }
}
