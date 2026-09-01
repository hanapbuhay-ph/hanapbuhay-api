<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;

class PublicController extends Controller
{
    /**
     * GET /api/ping
     * Health-check — no auth required.
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'HanapBuhay API is running',
            'data'    => [
                'version'     => '1.0.0',
                'scope'       => 'Trinidad, Bohol',
                'environment' => app()->environment(),
            ],
        ]);
    }

    /**
     * GET /api/barangays
     * Return all active Trinidad, Bohol barangays — no auth required.
     */
    public function barangays(): JsonResponse
    {
        $barangays = Barangay::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'latitude', 'longitude']);

        return response()->json([
            'success' => true,
            'message' => 'Barangays retrieved.',
            'data'    => $barangays,
        ]);
    }

    /**
     * GET /api/service-categories
     * Return all active service categories — no auth required.
     */
    public function serviceCategories(): JsonResponse
    {
        $categories = ServiceCategory::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'icon']);

        return response()->json([
            'success' => true,
            'message' => 'Categories retrieved.',
            'data'    => $categories,
        ]);
    }
}
