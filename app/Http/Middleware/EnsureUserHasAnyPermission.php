<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasAnyPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Votre compte ne permet pas d’effectuer cette action.',
            ], 403);
        }

        $isAuthorized = $permissions !== []
            && collect($permissions)->contains(
                fn (string $permission): bool => $user->hasPermission($permission),
            );

        if (! $isAuthorized) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Vous ne disposez pas de la permission nécessaire pour effectuer cette action.',
            ], 403);
        }

        return $next($request);
    }
}
