<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateCategoryRequest;
use App\Http\Requests\Admin\PostAnnouncementRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Services\Admin\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function __construct(private readonly AdminService $adminService) {}

    /**
     * GET /api/admin/settings  (spec §K17)
     * Returns service categories, report reasons, notification templates, active announcement.
     */
    public function index(): JsonResponse
    {
        $settings = $this->adminService->getSettings();

        return response()->json([
            'success' => true,
            'message' => 'Settings retrieved.',
            'data'    => $settings,
        ]);
    }

    /**
     * POST /api/admin/settings  (spec §K17)
     * Handles:
     *   action=post_announcement → publishes a system-wide announcement
     *   action=add_category      → creates a new service category (delegates to createCategory)
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'action' => ['required', 'string', 'in:post_announcement,add_category'],
        ]);

        if ($request->input('action') === 'post_announcement') {
            $request->validate([
                'title'      => ['required', 'string', 'max:200'],
                'body'       => ['required', 'string', 'max:1000'],
                'expires_at' => ['nullable', 'date', 'after:today'],
            ]);

            $announcement = $this->adminService->postAnnouncement(
                $request->user(),
                $request->input('title'),
                $request->input('body'),
                $request->input('expires_at'),
            );

            return response()->json([
                'success' => true,
                'message' => 'Setting updated.',
                'data'    => [
                    'announcement' => [
                        'id'         => $announcement->id,
                        'title'      => $announcement->title,
                        'body'       => $announcement->body,
                        'expires_at' => $announcement->expires_at?->toDateString(),
                    ],
                ],
            ]);
        }

        // action=add_category
        $request->validate([
            'name'      => ['required', 'string', 'max:100', 'unique:service_categories,name'],
            'icon'      => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category = $this->adminService->createCategory($request->user(), $request->only('name', 'icon', 'is_active'));

        return response()->json([
            'success' => true,
            'message' => 'Setting updated.',
            'data'    => ['category' => $category],
        ]);
    }

    // ── Sub-resource routes (existing) ────────────────────────────────────────

    /**
     * GET /api/admin/settings/categories
     */
    public function listCategories(): JsonResponse
    {
        $categories = $this->adminService->listCategories();

        return response()->json([
            'success' => true,
            'message' => 'Categories retrieved.',
            'data'    => $categories,
        ]);
    }

    /**
     * POST /api/admin/settings/categories
     */
    public function createCategory(CreateCategoryRequest $request): JsonResponse
    {
        $category = $this->adminService->createCategory($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Category created.',
            'data'    => $category,
        ], 201);
    }

    /**
     * PATCH /api/admin/settings/categories/{categoryId}
     */
    public function updateCategory(UpdateCategoryRequest $request, int $categoryId): JsonResponse
    {
        $category = $this->adminService->updateCategory($request->user(), $categoryId, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Category updated.',
            'data'    => $category,
        ]);
    }
}
