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
        $logs = ActivityLog::query()
            ->with('user:id,name,email')
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')))
            ->latest()
            ->limit(200)
            ->get();

        return response()->json([
            'success' => true,
            'count' => $logs->count(),
            'data' => $logs,
        ]);
    }
}
