<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Notification\NotificationService;
use Illuminate\Console\Command;

class NotifyTodayBookings extends Command
{
    protected $signature = 'bookings:notify-today';

    protected $description = 'Send push notifications to clients and workers for bookings scheduled today.';

    public function handle(NotificationService $notifications): void
    {
        $today = now()->toDateString();

        $bookings = Booking::with(['client', 'worker', 'serviceCategory'])
            ->where('status', 'accepted')
            ->whereDate('scheduled_at', $today)
            ->get();

        foreach ($bookings as $booking) {
            $service = $booking->serviceCategory?->name ?? 'your service';
            $time = $booking->scheduled_at->format('g:i A');

            if ($booking->client) {
                $notifications->notify(
                    $booking->client,
                    'Booking Today',
                    "Your {$service} booking is scheduled today at {$time}.",
                    'booking_today',
                    ['booking_id' => $booking->id],
                );
            }

            if ($booking->worker) {
                $notifications->notify(
                    $booking->worker,
                    'Job Today',
                    "You have a {$service} job scheduled today at {$time}.",
                    'booking_today',
                    ['booking_id' => $booking->id],
                );
            }
        }

        $this->info("Notified {$bookings->count()} booking(s) for {$today}.");
    }
}
