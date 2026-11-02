<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Cette action est réservée aux administrateurs.',
            ], 403);
        }

        return $next($request);
    }
}
