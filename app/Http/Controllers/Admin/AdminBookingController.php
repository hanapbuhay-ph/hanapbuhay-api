<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateAdminBookingRequest;
use App\Http\Requests\Admin\ForceCancelBookingRequest;
use App\Http\Requests\Booking\RescheduleBookingRequest;
use App\Models\Booking;
use App\Models\JobPost;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\Admin\AdminService;
use App\Services\Booking\BookingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBookingController extends Controller
{
    public function __construct(
        private readonly AdminService $adminService,
        private readonly BookingService $bookingService,
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
                'id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'status' => $booking->status,
                'scheduled_at' => $booking->scheduled_at?->toIso8601String(),
                'created_at' => $booking->created_at?->toIso8601String(),
                'client' => [
                    'id' => $booking->client?->id,
                    'name' => $booking->client?->name,
                    'profile_photo_url' => self::profilePhotoUrl($booking->client?->profile_photo_path),
                ],
                'worker' => [
                    'id' => $booking->worker?->id,
                    'name' => $booking->worker?->name,
                    'profile_photo_url' => self::profilePhotoUrl($booking->worker?->profile_photo_path),
                ],
                'service_category' => [
                    'name' => $booking->serviceCategory?->name,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Bookings retrieved.',
            'data' => [
                'bookings' => $bookings,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                ],
            ],
        ], 200);
    }

    private static function profilePhotoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
            ? $path
            : app(\App\Services\Media\MediaStorageService::class)->publicUrl($path);
    }

    /**
     * GET /api/admin/bookings/{id}
     * View a single booking's full detail.
     */
    public function show(int|string $id): JsonResponse
    {
        $booking = $this->adminService->getBooking($id);

        return response()->json([
            'success' => true,
            'message' => 'Booking retrieved.',
            'data' => [
                'id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'status' => $booking->status,
                'notes' => $booking->notes,
                'scheduled_at' => $booking->scheduled_at?->toIso8601String(),
                'started_at' => $booking->started_at?->toIso8601String(),
                'completed_at' => $booking->completed_at?->toIso8601String(),
                'cancelled_by' => $booking->cancelled_by,
                'cancellation_reason' => $booking->cancellation_reason,
                'created_at' => $booking->created_at?->toIso8601String(),
                'updated_at' => $booking->updated_at?->toIso8601String(),
                'client' => [
                    'id' => $booking->client?->id,
                    'name' => $booking->client?->name,
                    'email' => $booking->client?->email,
                    'mobile_number' => $booking->client?->mobile_number,
                    'barangay' => $booking->client?->barangay?->name,
                ],
                'worker' => [
                    'id' => $booking->worker?->id,
                    'name' => $booking->worker?->name,
                    'email' => $booking->worker?->email,
                    'mobile_number' => $booking->worker?->mobile_number,
                    'barangay' => $booking->worker?->barangay?->name,
                ],
                'service_category' => [
                    'name' => $booking->serviceCategory?->name,
                ],
                'job_post' => $booking->jobPost ? [
                    'id' => $booking->jobPost->id,
                    'title' => $booking->jobPost->title,
                    'description' => $booking->jobPost->description,
                    'rate_amount' => (float) $booking->jobPost->rate_amount,
                    'rate_type' => $booking->jobPost->rate_type,
                ] : null,
                'messages_count' => $booking->messages_count,
                'reports_count' => $booking->reports_count,
                'tracking_points_count' => $booking->tracking_points_count,
            ],
        ], 200);
    }

    /**
     * POST /api/admin/bookings
     * Create a pending booking on behalf of an active client.
     */
    public function store(CreateAdminBookingRequest $request): JsonResponse
    {
        $data = $request->validated();
        $client = User::query()->where('role', 'client')->where('is_active', true)->find($data['client_id']);
        $worker = User::query()->where('role', 'worker')->where('is_active', true)->with('workerProfile')->find($data['worker_id']);
        $category = ServiceCategory::query()->where('is_active', true)->find($data['service_category_id']);

        $errors = [];
        if (! $client) {
            $errors['client_id'] = ['The selected client is not active or does not exist.'];
        }
        if (! $worker || ! $worker->workerProfile) {
            $errors['worker_id'] = ['The selected worker is not active or does not have a worker profile.'];
        }
        if (! $category) {
            $errors['service_category_id'] = ['The selected service category is not active or does not exist.'];
        }
        $jobPost = $worker?->workerProfile
            ? JobPost::query()
                ->where('worker_profile_id', $worker->workerProfile->id)
                ->where('service_category_id', $data['service_category_id'])
                ->where('is_active', true)
                ->find($data['job_post_id'])
            : null;
        if (! $jobPost) {
            $errors['job_post_id'] = ['The selected job post is not active or does not belong to the selected worker and service category.'];
        }
        if ($errors !== []) {
            return response()->json([
                'success' => false,
                'message' => 'The selected booking participants or service are invalid.',
                'errors' => $errors,
            ], 422);
        }

        $data['job_post_id'] = $jobPost->id;
        $booking = $this->bookingService->createForAdmin($data, $client);
        $this->adminService->audit($request->user(), 'create_booking_for_client', 'Booking', $booking->id, [
            'booking_code' => $booking->booking_code,
            'client_id' => $client->id,
            'worker_id' => $worker->id,
            'scheduled_at' => $booking->scheduled_at?->toIso8601String(),
        ], $request->ip());

        return response()->json([
            'success' => true,
            'message' => 'Booking created for client.',
            'data' => [
                'id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'status' => $booking->status,
                'scheduled_at' => $booking->scheduled_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * POST /api/admin/bookings/{id}/reschedule
     * Reschedule pending bookings and notify both parties.
     */
    public function reschedule(RescheduleBookingRequest $request, int|string $id): JsonResponse
    {
        $booking = Booking::findOrFail($id);
        if ($booking->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending bookings can be rescheduled by an admin.',
            ], 422);
        }

        $previousScheduledAt = $booking->scheduled_at?->toIso8601String();
        $result = $this->bookingService->reschedulePendingByAdmin(
            $booking,
            Carbon::parse($request->validated('scheduled_at')),
        );
        if ($result instanceof JsonResponse) {
            return $result;
        }

        $this->adminService->audit($request->user(), 'reschedule_booking', 'Booking', $result->id, [
            'previous_scheduled_at' => $previousScheduledAt,
            'scheduled_at' => $result->scheduled_at?->toIso8601String(),
        ], $request->ip());

        return response()->json([
            'success' => true,
            'message' => 'Booking rescheduled.',
            'data' => [
                'id' => $result->id,
                'booking_code' => $result->booking_code,
                'status' => $result->status,
                'scheduled_at' => $result->scheduled_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/admin/bookings/{id}/cancel
     * Force-cancel any non-terminal booking.
     */
    public function forceCancel(ForceCancelBookingRequest $request, int|string $id): JsonResponse
    {
        $booking = $this->adminService->forceCancelBooking(
            $request->user(),
            $id,
            $request->validated('reason'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Booking force-cancelled.',
            'data' => [
                'id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'status' => $booking->status,
                'cancellation_reason' => $booking->cancellation_reason,
                'cancelled_by' => $booking->cancelled_by,
            ],
        ]);
    }
}
