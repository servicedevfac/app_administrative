<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanManageOperations
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active || ! $user->canManageOperations()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Cette action est réservée aux responsables, délégataires actifs et administrateurs.',
            ], 403);
        }

        return $next($request);
    }
}
