<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AdminService $adminService,
    ) {}

    /**
     * GET /api/admin/dashboard
     * Return aggregate counts for the admin dashboard.
     */
    public function index(): JsonResponse
    {
        $stats = $this->adminService->dashboardStats();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard stats retrieved.',
            'data'    => $stats,
        ], 200);
    }
}
