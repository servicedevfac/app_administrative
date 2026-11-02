<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\MaterialRequestController;
use App\Http\Controllers\Api\ReferenceDataController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - GESTION+ Enterprise
|--------------------------------------------------------------------------
*/

// Statut de l'API & Test de connexion Base de données
Route::get('/health', function () {
    $dbConnected = false;
    $dbMessage = '';
    try {
        DB::connection()->getPdo();
        $dbConnected = true;
        $dbMessage = 'Service de données connecté avec succès.';
    } catch (Exception) {
        $dbMessage = 'Service de données indisponible.';
    }

    return response()->json([
        'status' => $dbConnected ? 'online' : 'degraded',
        'app' => 'GESTION+ Enterprise Backend (Laravel 13)',
        'database_connected' => $dbConnected,
        'database_driver' => config('database.default'),
        'message' => $dbMessage,
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Authentification & Profils
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

Route::middleware('auth:api')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('/articles', [ArticleController::class, 'index']);
    Route::get('/articles/{id}', [ArticleController::class, 'show'])->whereNumber('id');

    Route::get('/requests', [MaterialRequestController::class, 'index']);
    Route::post('/requests', [MaterialRequestController::class, 'store']);
    Route::get('/requests-export', [MaterialRequestController::class, 'export'])->middleware('admin');
    Route::get('/requests/{id}', [MaterialRequestController::class, 'show']);
    Route::put('/requests/{id}/submit', [MaterialRequestController::class, 'submit']);
    Route::put('/requests/{id}/cancel', [MaterialRequestController::class, 'cancel']);

    Route::middleware('admin')->group(function (): void {
        Route::get('/articles-export', [ArticleController::class, 'export']);
        Route::post('/articles', [ArticleController::class, 'store']);
        Route::put('/articles/{id}', [ArticleController::class, 'update']);
        Route::delete('/articles/{id}', [ArticleController::class, 'destroy']);

        Route::put('/requests/{id}/approve', [MaterialRequestController::class, 'approve']);
        Route::put('/requests/{id}/reject', [MaterialRequestController::class, 'reject']);
        Route::put('/requests/{id}/request-correction', [MaterialRequestController::class, 'requestCorrection']);
        Route::put('/requests/{id}/deliver', [MaterialRequestController::class, 'markDelivered']);
        Route::put('/requests/{id}/return', [MaterialRequestController::class, 'markReturned']);
        Route::put('/requests/{id}/close', [MaterialRequestController::class, 'close']);
        Route::get('/movements', [StockMovementController::class, 'index']);
        Route::post('/movements', [StockMovementController::class, 'store']);
        Route::get('/movements-export', [StockMovementController::class, 'export']);

        Route::get('/documents', [DocumentController::class, 'index']);
        Route::post('/documents', [DocumentController::class, 'store']);
        Route::get('/documents/{document}/download', [DocumentController::class, 'download']);

        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);

        Route::get('/reference-data', [ReferenceDataController::class, 'index']);
        Route::post('/categories', [ReferenceDataController::class, 'storeCategory']);
        Route::put('/categories/{category}', [ReferenceDataController::class, 'updateCategory']);
        Route::post('/units', [ReferenceDataController::class, 'storeUnit']);
        Route::put('/units/{unit}', [ReferenceDataController::class, 'updateUnit']);
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    });
});
