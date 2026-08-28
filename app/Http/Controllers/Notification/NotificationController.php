<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\RegisterDeviceRequest;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function registerDevice(RegisterDeviceRequest $request): JsonResponse
    {
        DeviceToken::updateOrCreate(
            [
                'user_id'   => $request->user()->id,
                'fcm_token' => $request->input('fcm_token'),
            ],
            [
                'device_type' => $request->input('device_type'),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Device registered successfully.',
            'data'    => [],
        ]);
    }
}
