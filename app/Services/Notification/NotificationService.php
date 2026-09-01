<?php

namespace App\Services\Notification;

use App\Models\DeviceToken;
use App\Models\HanapbuhayNotification;
use App\Models\User;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class NotificationService
{
    public function __construct(private readonly Messaging $messaging) {}

    /**
     * Create an in-app notification record for the given user.
     */
    public function notify(User $user, string $title, string $body, string $type, array $data = []): HanapbuhayNotification
    {
        return HanapbuhayNotification::create([
            'user_id' => $user->id,
            'title'   => $title,
            'body'    => $body,
            'type'    => $type,
            'data'    => $data ?: null,
            'is_read' => false,
        ]);
    }

    /**
     * Send a Firebase push notification to all of a user's registered devices.
     * Also creates an in-app notification record.
     */
    public function sendPush(User $user, string $title, string $body = '', array $data = []): void
    {
        $tokens = DeviceToken::where('user_id', $user->id)->get();

        if ($tokens->isEmpty()) {
            return;
        }

        $notification = Notification::create($title, $body);

        foreach ($tokens as $token) {
            try {
                $message = CloudMessage::new()
                    ->withToken($token->fcm_token)
                    ->withNotification($notification)
                    ->withData($data);

                $this->messaging->send($message);
            } catch (MessagingException) {
                $token->delete();
            }
        }
    }
}
