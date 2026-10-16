<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class BookingScheduleConflictException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'This worker already has a booking scheduled at that time.',
        ], 409);
    }
}
