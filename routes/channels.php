<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('booking.{bookingId}', function (User $user, int $bookingId) {
    $booking = \App\Models\Booking::find($bookingId);

    return $booking &&
        ($user->id === $booking->client_id ||
         $user->id === $booking->worker_id);
});
