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
    Route::get('/articles', [ArticleController::class, 'index'])->middleware('permission:stock.view');
    Route::get('/articles/{id}', [ArticleController::class, 'show'])->middleware('permission:stock.view')->whereNumber('id');

    // Demandes (Employé + Responsable + Administrateur)
    Route::get('/requests', [MaterialRequestController::class, 'index']);
    Route::post('/requests', [MaterialRequestController::class, 'store'])->middleware('permission:requests.create');
    Route::get('/requests-export', [MaterialRequestController::class, 'export'])->middleware(['manager', 'permission:reports.view']);
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
        Route::put('/requests/{id}/verify', [MaterialRequestController::class, 'verify'])->middleware('permission:requests.verify');
        Route::put('/requests/{id}/approve', [MaterialRequestController::class, 'approve'])->middleware('permission:requests.validate');
        Route::put('/requests/{id}/reject', [MaterialRequestController::class, 'reject'])->middleware('permission:requests.validate');
        Route::put('/requests/{id}/request-correction', [MaterialRequestController::class, 'requestCorrection'])->middleware('permission:requests.validate');
        Route::put('/requests/{id}/assign', [MaterialRequestController::class, 'assign'])->middleware('permission:requests.assign');
        Route::put('/requests/{id}/execution-status', [MaterialRequestController::class, 'updateExecutionStatus'])->middleware('permission:requests.execute');
        Route::put('/requests/{id}/deliver', [MaterialRequestController::class, 'markDelivered'])->middleware('permission:requests.execute');
        Route::put('/requests/{id}/return', [MaterialRequestController::class, 'markReturned'])->middleware('permission:requests.execute');
        Route::put('/requests/{id}/close', [MaterialRequestController::class, 'close'])->middleware('permission:requests.execute');

        // Stocks, Articles, Réapprovisionnement & Mouvements
        Route::get('/articles-export', [ArticleController::class, 'export'])->middleware('permission:stock.view');
        Route::post('/articles', [ArticleController::class, 'store'])->middleware('permission:stock.manage');
        Route::put('/articles/{id}', [ArticleController::class, 'update'])->middleware('permission:stock.manage');
        Route::get('/articles-replenishment', [ArticleController::class, 'replenishment'])->middleware('permission:stock.view');
        Route::get('/articles-replenishment-export', [ArticleController::class, 'exportReplenishment'])->middleware('permission:stock.view');
        Route::get('/movements', [StockMovementController::class, 'index'])->middleware('permission:stock.view');
        Route::post('/movements', [StockMovementController::class, 'store'])->middleware('permission:stock.manage');
        Route::get('/movements-export', [StockMovementController::class, 'export'])->middleware('permission:stock.view');

        // Inventaires physiques
        Route::get('/inventories', [OperationsController::class, 'inventories'])->middleware('permission:inventory.manage,inventory.validate');
        Route::post('/inventories', [OperationsController::class, 'storeInventory'])->middleware('permission:inventory.manage');
        Route::put('/inventories/{inventory}/validate', [OperationsController::class, 'validateInventory'])->middleware('permission:inventory.validate');

        // Centre d'opérations : Patrimoine, Parc auto, Maintenance & Dépenses
        Route::get('/operations/overview', [OperationsController::class, 'overview'])->middleware('permission:assets.manage,vehicles.manage,expenses.manage,inventory.manage,reports.view');
        Route::get('/operations/assignees', [OperationsController::class, 'assignees'])->middleware('permission:requests.assign,assets.manage,vehicles.manage,expenses.manage');

        Route::get('/assets', [OperationsController::class, 'assets'])->middleware('permission:assets.manage');
        Route::get('/assets-export', [OperationsController::class, 'exportAssets'])->middleware('permission:assets.manage');
        Route::post('/assets', [OperationsController::class, 'storeAsset'])->middleware('permission:assets.manage');
        Route::put('/assets/{asset}', [OperationsController::class, 'updateAsset'])->middleware('permission:assets.manage');
        Route::post('/assets/{asset}/assign', [OperationsController::class, 'assignAsset'])->middleware('permission:assets.manage');
        Route::post('/assets/{asset}/return', [OperationsController::class, 'returnAsset'])->middleware('permission:assets.manage');
        Route::post('/assets/{asset}/incidents', [OperationsController::class, 'storeAssetIncident'])->middleware('permission:assets.manage');
        Route::put('/asset-incidents/{incident}/resolve', [OperationsController::class, 'resolveAssetIncident'])->middleware('permission:assets.manage');

        Route::get('/vehicles', [OperationsController::class, 'vehicles'])->middleware('permission:vehicles.manage');
        Route::get('/vehicles-export', [OperationsController::class, 'exportVehicles'])->middleware('permission:vehicles.manage');
        Route::post('/vehicles', [OperationsController::class, 'storeVehicle'])->middleware('permission:vehicles.manage');
        Route::put('/vehicles/{vehicle}', [OperationsController::class, 'updateVehicle'])->middleware('permission:vehicles.manage');
        Route::post('/vehicles/{vehicle}/documents', [OperationsController::class, 'storeVehicleDocument'])->middleware('permission:vehicles.manage');
        Route::get('/vehicle-documents/{document}/download', [OperationsController::class, 'downloadVehicleDocument'])->middleware('permission:vehicles.manage');

        Route::post('/maintenance-records', [OperationsController::class, 'storeMaintenance'])->middleware('permission:assets.manage,vehicles.manage');
        Route::put('/maintenance-records/{maintenanceRecord}', [OperationsController::class, 'updateMaintenance'])->middleware('permission:assets.manage,vehicles.manage');

        Route::get('/expenses', [OperationsController::class, 'expenses'])->middleware('permission:expenses.manage,expenses.validate');
        Route::get('/expenses-export', [OperationsController::class, 'exportExpenses'])->middleware('permission:expenses.manage,expenses.validate');
        Route::post('/expenses', [OperationsController::class, 'storeExpense'])->middleware('permission:expenses.manage');
        Route::put('/expenses/{expense}', [OperationsController::class, 'updateExpense'])->middleware('permission:expenses.manage');
        Route::put('/expenses/{expense}/validate', [OperationsController::class, 'validateExpense'])->middleware('permission:expenses.validate');
        Route::post('/expenses/{expense}/attachments', [OperationsController::class, 'addExpenseAttachment'])->middleware('permission:expenses.manage');
        Route::get('/expense-attachments/{attachment}/download', [OperationsController::class, 'downloadExpenseAttachment'])->middleware('permission:expenses.manage,expenses.validate');

        // Reporting & Archivage des rapports
        Route::get('/reports', [ReportController::class, 'index'])->middleware('permission:reports.view');
        Route::post('/reports/generate', [ReportController::class, 'generate'])->middleware('permission:reports.view');
        Route::get('/reports/saved/{savedReport}', [ReportController::class, 'showSaved'])->middleware('permission:reports.view');
        Route::get('/reports-export', [ReportController::class, 'export'])->middleware('permission:reports.view');

        // Documents administratifs
        Route::get('/documents', [DocumentController::class, 'index'])->middleware('permission:documents.manage');
        Route::post('/documents', [DocumentController::class, 'store'])->middleware('permission:documents.manage');
        Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->middleware('permission:documents.manage');
    });

    // Administration Générale (Administrateur uniquement)
    Route::middleware('admin')->group(function (): void {
        Route::delete('/articles/{id}', [ArticleController::class, 'destroy']);

        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::put('/users/{id}/role', [UserController::class, 'updateRole']);
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
