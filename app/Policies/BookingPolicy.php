<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function view(User $user, Booking $booking): bool
    {
        return $user->role === 'admin'
            || $user->id === $booking->client_id
            || $user->id === $booking->worker_id;
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $user->id === $booking->client_id
            || $user->id === $booking->worker_id;
    }

    public function rate(User $user, Booking $booking): bool
    {
        return $user->id === $booking->client_id
            || $user->id === $booking->worker_id;
    }

    public function messages(User $user, Booking $booking): bool
    {
        return $user->id === $booking->client_id
            || $user->id === $booking->worker_id;
    }

    public function tracking(User $user, Booking $booking): bool
    {
        return $user->id === $booking->client_id
            || $user->id === $booking->worker_id;
    }
}
