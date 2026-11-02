<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
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

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function recordActivity(
        Request $request,
        string $action,
        Model $subject,
        string $description,
        array $metadata = [],
    ): void {
        ActivityLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
        ]);
    }
}
