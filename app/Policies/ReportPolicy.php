<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function store(User $user, Booking $booking): bool
    {
        return $user->id === $booking->client_id
            || $user->id === $booking->worker_id;
    }

    public function view(User $user, Report $report): bool
    {
        return $user->id === $report->reported_by;
    }
}
