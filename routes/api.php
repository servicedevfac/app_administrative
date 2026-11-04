<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\MaterialRequestController;
use App\Http\Controllers\Api\OperationsController;
use App\Http\Controllers\Api\ReferenceDataController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\UserController;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| API Routes - GESTION+ Enterprise
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    $dbConnected = false;
    $dbMessage = '';
    $hasUsers = false;
    try {
        DB::connection()->getPdo();
        $dbConnected = true;
        $hasUsers = Schema::hasTable('users') && User::count() > 0;
        $dbMessage = 'Service de données connecté avec succès.';
    } catch (Exception) {
        $dbMessage = 'Service de données indisponible.';
    }

    return response()->json([
        'status' => $dbConnected ? 'online' : 'degraded',
        'app' => 'GESTION+ Enterprise Backend',
        'database_connected' => $dbConnected,
        'has_users' => $hasUsers,
        'database_driver' => config('database.default'),
        'message' => $dbMessage,
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Authentification publique
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::get('/public-references', [OperationsController::class, 'publicCatalogReferences']);

Route::middleware('auth:api')->group(function (): void {
    // Profil & Session
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::get('/auth/login-history', [AuthController::class, 'myLoginHistory']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Référentiels actifs pour les formulaires de tous les utilisateurs connectés
    Route::get('/catalog-references', [OperationsController::class, 'publicCatalogReferences']);

    // Tableau de bord & Notifications
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/notifications', [OperationsController::class, 'notifications']);
    Route::put('/notifications/read-all', [OperationsController::class, 'markAllNotificationsRead']);
    Route::put('/notifications/{notification}/read', [OperationsController::class, 'readNotification']);

    // Catalogue des articles en stock
    Route::get('/articles', [ArticleController::class, 'index']);
    Route::get('/articles/{id}', [ArticleController::class, 'show'])->whereNumber('id');

    // Demandes (Employé + Responsable + Administrateur)
    Route::get('/requests', [MaterialRequestController::class, 'index']);
    Route::post('/requests', [MaterialRequestController::class, 'store']);
    Route::get('/requests-export', [MaterialRequestController::class, 'export'])->middleware('manager');
    Route::get('/requests/{id}', [MaterialRequestController::class, 'show']);
    Route::put('/requests/{id}', [MaterialRequestController::class, 'update']);
    Route::post('/requests/{id}/attachments', [MaterialRequestController::class, 'addAttachment']);
    Route::put('/requests/{id}/submit', [MaterialRequestController::class, 'submit']);
    Route::put('/requests/{id}/cancel', [MaterialRequestController::class, 'cancel']);
    Route::put('/requests/{id}/receive', [MaterialRequestController::class, 'confirmReceipt']);

    // Téléchargement des documents rattachés (bons / pièces jointes)
    Route::get('/documents/{document}/download', [DocumentController::class, 'download']);

    // Opérations de Gestion (Responsable, Délégataire actif & Administrateur)
    Route::middleware('manager')->group(function (): void {
        // Circuit de validation et d'exécution des demandes
        Route::put('/requests/{id}/verify', [MaterialRequestController::class, 'verify']);
        Route::put('/requests/{id}/approve', [MaterialRequestController::class, 'approve']);
        Route::put('/requests/{id}/reject', [MaterialRequestController::class, 'reject']);
        Route::put('/requests/{id}/request-correction', [MaterialRequestController::class, 'requestCorrection']);
        Route::put('/requests/{id}/assign', [MaterialRequestController::class, 'assign']);
        Route::put('/requests/{id}/execution-status', [MaterialRequestController::class, 'updateExecutionStatus']);
        Route::put('/requests/{id}/deliver', [MaterialRequestController::class, 'markDelivered']);
        Route::put('/requests/{id}/return', [MaterialRequestController::class, 'markReturned']);
        Route::put('/requests/{id}/close', [MaterialRequestController::class, 'close']);

        // Stocks, Articles, Réapprovisionnement & Mouvements
        Route::get('/articles-export', [ArticleController::class, 'export']);
        Route::post('/articles', [ArticleController::class, 'store']);
        Route::put('/articles/{id}', [ArticleController::class, 'update']);
        Route::get('/articles-replenishment', [ArticleController::class, 'replenishment']);
        Route::get('/articles-replenishment-export', [ArticleController::class, 'exportReplenishment']);
        Route::get('/movements', [StockMovementController::class, 'index']);
        Route::post('/movements', [StockMovementController::class, 'store']);
        Route::get('/movements-export', [StockMovementController::class, 'export']);

        // Inventaires physiques
        Route::get('/inventories', [OperationsController::class, 'inventories']);
        Route::post('/inventories', [OperationsController::class, 'storeInventory']);
        Route::put('/inventories/{inventory}/validate', [OperationsController::class, 'validateInventory']);

        // Centre d'opérations : Patrimoine, Parc auto, Maintenance & Dépenses
        Route::get('/operations/overview', [OperationsController::class, 'overview']);
        Route::get('/operations/assignees', [OperationsController::class, 'assignees']);

        Route::get('/assets', [OperationsController::class, 'assets']);
        Route::get('/assets-export', [OperationsController::class, 'exportAssets']);
        Route::post('/assets', [OperationsController::class, 'storeAsset']);
        Route::put('/assets/{asset}', [OperationsController::class, 'updateAsset']);
        Route::post('/assets/{asset}/assign', [OperationsController::class, 'assignAsset']);
        Route::post('/assets/{asset}/return', [OperationsController::class, 'returnAsset']);
        Route::post('/assets/{asset}/incidents', [OperationsController::class, 'storeAssetIncident']);
        Route::put('/asset-incidents/{incident}/resolve', [OperationsController::class, 'resolveAssetIncident']);

        Route::get('/vehicles', [OperationsController::class, 'vehicles']);
        Route::get('/vehicles-export', [OperationsController::class, 'exportVehicles']);
        Route::post('/vehicles', [OperationsController::class, 'storeVehicle']);
        Route::put('/vehicles/{vehicle}', [OperationsController::class, 'updateVehicle']);
        Route::post('/vehicles/{vehicle}/documents', [OperationsController::class, 'storeVehicleDocument']);
        Route::get('/vehicle-documents/{document}/download', [OperationsController::class, 'downloadVehicleDocument']);

        Route::post('/maintenance-records', [OperationsController::class, 'storeMaintenance']);
        Route::put('/maintenance-records/{maintenanceRecord}', [OperationsController::class, 'updateMaintenance']);

        Route::get('/expenses', [OperationsController::class, 'expenses']);
        Route::get('/expenses-export', [OperationsController::class, 'exportExpenses']);
        Route::post('/expenses', [OperationsController::class, 'storeExpense']);
        Route::put('/expenses/{expense}', [OperationsController::class, 'updateExpense']);
        Route::put('/expenses/{expense}/validate', [OperationsController::class, 'validateExpense']);
        Route::post('/expenses/{expense}/attachments', [OperationsController::class, 'addExpenseAttachment']);
        Route::get('/expense-attachments/{attachment}/download', [OperationsController::class, 'downloadExpenseAttachment']);

        // Reporting & Archivage des rapports
        Route::get('/reports', [ReportController::class, 'index']);
        Route::post('/reports/generate', [ReportController::class, 'generate']);
        Route::get('/reports/saved/{savedReport}', [ReportController::class, 'showSaved']);
        Route::get('/reports-export', [ReportController::class, 'export']);

        // Documents administratifs
        Route::get('/documents', [DocumentController::class, 'index']);
        Route::post('/documents', [DocumentController::class, 'store']);
        Route::delete('/documents/{document}', [DocumentController::class, 'destroy']);
    });

    // Administration Générale (Administrateur uniquement)
    Route::middleware('admin')->group(function (): void {
        Route::delete('/articles/{id}', [ArticleController::class, 'destroy']);

        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::put('/users/{id}/active', [UserController::class, 'toggleActive']);
        Route::put('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::get('/login-history', [UserController::class, 'loginHistory']);

        Route::get('/reference-data', [ReferenceDataController::class, 'index']);
        Route::post('/categories', [ReferenceDataController::class, 'storeCategory']);
        Route::put('/categories/{category}', [ReferenceDataController::class, 'updateCategory']);
        Route::post('/units', [ReferenceDataController::class, 'storeUnit']);
        Route::put('/units/{unit}', [ReferenceDataController::class, 'updateUnit']);

        Route::get('/activity-logs', [ActivityLogController::class, 'index']);

        Route::get('/operations/reference-data', [OperationsController::class, 'referenceData']);
        Route::post('/operations/reference-data/{type}', [OperationsController::class, 'storeReference']);
        Route::put('/operations/reference-data/{type}/{id}', [OperationsController::class, 'updateReference']);
        Route::put('/operations/settings', [OperationsController::class, 'saveSettings']);

        Route::get('/backups', [OperationsController::class, 'backups']);
        Route::post('/backups', [OperationsController::class, 'createBackup']);
        Route::get('/backups/{backup}/download', [OperationsController::class, 'downloadBackup']);
        Route::post('/backups/restore', [OperationsController::class, 'restoreBackup']);
    });
});
