<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\SystemNotification;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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
        ?Model $subject,
        string $description,
        array $metadata = [],
    ): void {
        ActivityLog::create([
            'user_id' => $request->user()?->id ?? ($subject instanceof User ? $subject->id : null),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
        ]);
    }

    protected function isNotificationEnabled(string $settingKey): bool
    {
        if (! Schema::hasTable('system_settings')) {
            return true;
        }

        $value = SystemSetting::query()->where('key', $settingKey)->value('value');

        if ($value === null) {
            return true;
        }

        return ! in_array((string) $value, ['0', 'false', 'off'], true);
    }

    protected function sendSystemNotification(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $link = null,
        ?string $settingKey = null,
    ): void {
        if (! Schema::hasTable('system_notifications')) {
            return;
        }

        if ($settingKey !== null && ! $this->isNotificationEnabled($settingKey)) {
            return;
        }

        SystemNotification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
        ]);
    }

    protected function notifyOperationalManagers(
        string $type,
        string $title,
        string $message,
        ?string $link = null,
        ?string $settingKey = null,
    ): void {
        if ($settingKey !== null && ! $this->isNotificationEnabled($settingKey)) {
            return;
        }

        User::query()
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_MANAGER])
            ->where('is_active', true)
            ->pluck('id')
            ->each(fn (int $userId) => $this->sendSystemNotification($userId, $type, $title, $message, $link));
    }
}
