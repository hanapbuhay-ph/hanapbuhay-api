<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role === null) {
            return response()->json([
                'success' => false,
                'code' => 'profile_incomplete',
                'message' => 'Complete your profile before using the app.',
            ], 403);
        }

        return $next($request);
    }
}
