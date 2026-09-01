<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'This action is for admins only.',
            ], 403);
        }

        return $next($request);
    }
}
