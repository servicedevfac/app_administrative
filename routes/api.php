<?php

use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\MaterialRequestController;
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
        $dbMessage = 'Connexion MySQL établie avec succès.';
    } catch (Exception) {
        $dbMessage = 'Connexion à la base de données indisponible.';
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
Route::get('/auth/me', [AuthController::class, 'me']);

// Tableau de bord consolidé (Admin & Collaborateur)
Route::get('/dashboard', [DashboardController::class, 'index']);

// Gestion du Parc & Articles de Stock
Route::get('/articles', [ArticleController::class, 'index']);
Route::post('/articles', [ArticleController::class, 'store']);
Route::get('/articles/{id}', [ArticleController::class, 'show']);
Route::put('/articles/{id}', [ArticleController::class, 'update']);
Route::delete('/articles/{id}', [ArticleController::class, 'destroy']);

// Gestion des Demandes d'Emprunt & Réquisitions Internes
Route::get('/requests', [MaterialRequestController::class, 'index']);
Route::post('/requests', [MaterialRequestController::class, 'store']);
Route::get('/requests/{id}', [MaterialRequestController::class, 'show']);
Route::put('/requests/{id}/approve', [MaterialRequestController::class, 'approve']);
Route::put('/requests/{id}/reject', [MaterialRequestController::class, 'reject']);
Route::put('/requests/{id}/return', [MaterialRequestController::class, 'markReturned']);

// Mouvements de Stock (Entrées / Sorties logistiques)
Route::get('/movements', [StockMovementController::class, 'index']);
Route::post('/movements', [StockMovementController::class, 'store']);

// Documents Administratifs & Bons de Décharge
Route::get('/documents', [DocumentController::class, 'index']);
Route::post('/documents', [DocumentController::class, 'store']);

// Gestion des Utilisateurs & Profils (Admin)
Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);
Route::get('/users/{id}', [UserController::class, 'show']);
Route::put('/users/{id}', [UserController::class, 'update']);
Route::delete('/users/{id}', [UserController::class, 'destroy']);
