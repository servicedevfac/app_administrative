<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::query()->with('user:id,name,email,role,service,matricule');

        if ($request->filled('action')) {
            $query->where('action', $request->string('action')->toString());
        }

        if ($request->filled('module')) {
            $prefix = match ($request->string('module')->toString()) {
                'auth' => ['auth.', 'profile.'],
                'request', 'requests' => ['request.'],
                'article', 'articles' => ['article.'],
                'movement', 'movements' => ['stock.'],
                'inventory', 'inventories' => ['inventory.'],
                'stock' => ['article.', 'stock.', 'inventory.'],
                'asset', 'assets' => ['asset.', 'maintenance.'],
                'vehicle', 'vehicles' => ['vehicle.', 'maintenance.'],
                'expense', 'expenses', 'finance' => ['expense.'],
                'user', 'users' => ['user.'],
                'settings' => ['category.', 'unit.', 'reference.', 'settings.', 'delegation.'],
                'backup', 'backups' => ['backup.'],
                default => [],
            };

            if ($prefix !== []) {
                $query->where(function ($sub) use ($prefix): void {
                    foreach ($prefix as $item) {
                        $sub->orWhere('action', 'like', "{$item}%");
                    }
                });
            }
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($sub) use ($search): void {
                $sub->where('description', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $logs = $query->latest()->limit(300)->get();

        return response()->json([
            'success' => true,
            'count' => $logs->count(),
            'data' => $logs,
        ]);
    }
}
