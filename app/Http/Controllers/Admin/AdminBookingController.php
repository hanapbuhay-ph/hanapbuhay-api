<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ForceCancelBookingRequest;
use App\Services\Admin\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBookingController extends Controller
{
    public function __construct(
        private readonly AdminService $adminService,
    ) {}

    /**
     * GET /api/admin/bookings
     * List all bookings across all users, optionally filtered by status.
     */
    public function index(Request $request): JsonResponse
    {
        $paginated = $this->adminService->listBookings(
            $request->query('status'),
            $request->filled('category_id') ? $request->integer('category_id') : null,
            $request->query('date_from'),
            $request->query('date_to'),
            $request->query('search'),
        );

        $bookings = collect($paginated->items())->map(function ($booking): array {
            return [
                'id'               => $booking->id,
                'booking_code'     => $booking->booking_code,
                'status'           => $booking->status,
                'scheduled_at'     => $booking->scheduled_at?->toIso8601String(),
                'created_at'       => $booking->created_at?->toIso8601String(),
                'client'           => [
                    'id'   => $booking->client?->id,
                    'name' => $booking->client?->name,
                ],
                'worker'           => [
                    'id'   => $booking->worker?->id,
                    'name' => $booking->worker?->name,
                ],
                'service_category' => [
                    'name' => $booking->serviceCategory?->name,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Bookings retrieved.',
            'data'    => [
                'bookings'   => $bookings,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ],
        ], 200);
    }

    /**
     * GET /api/admin/bookings/{id}
     * View a single booking's full detail.
     */
    public function show(int $id): JsonResponse
    {
        $booking = $this->adminService->getBooking($id);

        return response()->json([
            'success' => true,
            'message' => 'Booking retrieved.',
            'data'    => [
                'id'                  => $booking->id,
                'booking_code'        => $booking->booking_code,
                'status'              => $booking->status,
                'notes'               => $booking->notes,
                'scheduled_at'        => $booking->scheduled_at?->toIso8601String(),
                'started_at'          => $booking->started_at?->toIso8601String(),
                'completed_at'        => $booking->completed_at?->toIso8601String(),
                'cancelled_by'        => $booking->cancelled_by,
                'cancellation_reason' => $booking->cancellation_reason,
                'created_at'          => $booking->created_at?->toIso8601String(),
                'updated_at'          => $booking->updated_at?->toIso8601String(),
                'client'              => [
                    'id'   => $booking->client?->id,
                    'name' => $booking->client?->name,
                ],
                'worker'              => [
                    'id'   => $booking->worker?->id,
                    'name' => $booking->worker?->name,
                ],
                'service_category'    => [
                    'name' => $booking->serviceCategory?->name,
                ],
            ],
        ], 200);
    }

    /**
     * POST /api/admin/bookings/{id}/cancel
     * Force-cancel any non-terminal booking.
     */
    public function forceCancel(ForceCancelBookingRequest $request, int $id): JsonResponse
    {
        $booking = $this->adminService->forceCancelBooking(
            $request->user(),
            $id,
            $request->validated('reason'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Booking force-cancelled.',
            'data'    => [
                'id'                  => $booking->id,
                'booking_code'        => $booking->booking_code,
                'status'              => $booking->status,
                'cancellation_reason' => $booking->cancellation_reason,
                'cancelled_by'        => $booking->cancelled_by,
            ],
        ]);
    }
}
