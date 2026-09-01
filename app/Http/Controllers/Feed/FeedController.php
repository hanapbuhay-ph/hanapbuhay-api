<?php

namespace App\Http\Controllers\Feed;

use App\Http\Controllers\Controller;
use App\Services\Feed\FeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function __construct(private readonly FeedService $feedService) {}

    /**
     * GET /api/feed
     * Client home feed — paginated list of active job posts sorted by
     * distance → trust tier → average rating.
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->feedService->getFeed($request->user(), $request);

        return response()->json([
            'success' => true,
            'data'    => [
                'posts'      => $paginator->items(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                    'last_page'    => $paginator->lastPage(),
                ],
            ],
        ]);
    }
}
