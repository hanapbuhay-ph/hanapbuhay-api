<?php

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('admin-panel', function (User $user) {
    return $user->isAdmin();
});

Broadcast::channel('booking.{bookingId}', function (User $user, int $bookingId) {
    $booking = Booking::find($bookingId);

    return $booking &&
        ($user->id === $booking->client_id ||
         $user->id === $booking->worker_id);
});
