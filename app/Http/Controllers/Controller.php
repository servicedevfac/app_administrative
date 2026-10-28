<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function resolveActorId(Request $request, string $inputName = 'user_id'): ?int
    {
        $authenticatedUserId = $request->user()?->id;

        if ($authenticatedUserId !== null) {
            return $authenticatedUserId;
        }

        $requestedUserId = $request->input($inputName);

        if ($requestedUserId !== null) {
            $resolvedUserId = User::query()->whereKey($requestedUserId)->value('id');

            if ($resolvedUserId !== null) {
                return (int) $resolvedUserId;
            }
        }

        $fallbackUserId = User::query()->where('role', User::ROLE_ADMIN)->value('id')
            ?? User::query()->value('id');

        return $fallbackUserId === null ? null : (int) $fallbackUserId;
    }
}
