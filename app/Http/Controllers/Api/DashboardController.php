<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\MaterialRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Retourne les indicateurs consolidés (KPIs) selon le profil actif
     */
    public function index(Request $request)
    {
        $role = $request->query('role', 'admin');
        $userId = $request->query('user_id') ?? $request->user()?->id;

        if ($role === 'admin') {
            $totalArticles = Article::count();
            $lowStockCount = Article::whereColumn('quantity', '<=', 'threshold')->count();
            $pendingRequests = MaterialRequest::where('status', 'En attente')->count();
            $approvedRequests = MaterialRequest::where('status', 'Approuvée')->count();

            // Répartition par catégorie
            $categories = Article::select('category', DB::raw('count(*) as count'), DB::raw('sum(quantity) as total_qty'))
                ->groupBy('category')
                ->get();

            // Demandes urgentes à valider
            $urgentRequests = MaterialRequest::with(['user', 'items.article'])
                ->where('status', 'En attente')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            // Alertes stock critique
            $criticalStock = Article::whereColumn('quantity', '<=', 'threshold')
                ->orderBy('quantity', 'asc')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'role' => 'admin',
                'kpis' => [
                    'total_articles' => $totalArticles,
                    'low_stock_count' => $lowStockCount,
                    'pending_requests' => $pendingRequests,
                    'approved_requests' => $approvedRequests,
                ],
                'categories_distribution' => $categories,
                'urgent_requests' => $urgentRequests,
                'critical_stock' => $criticalStock,
            ]);
        }

        // Collaborateur
        $myRequestsQuery = MaterialRequest::query();
        if ($userId) {
            $myRequestsQuery->where('user_id', $userId);
        }

        $inProgress = (clone $myRequestsQuery)->whereIn('status', ['En attente'])->count();
        $approved = (clone $myRequestsQuery)->whereIn('status', ['Approuvée', 'Restituée'])->count();
        $latest = (clone $myRequestsQuery)->orderBy('id', 'desc')->first();

        $recentRequests = (clone $myRequestsQuery)->with(['items.article'])
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        $articlesReceived = 0;
        if ($userId) {
            $articlesReceived = (int) DB::table('request_items')
                ->join('material_requests', 'material_requests.id', '=', 'request_items.material_request_id')
                ->where('material_requests.user_id', $userId)
                ->where('material_requests.status', 'Approuvée')
                ->sum('request_items.quantity_approved');
        }

        return response()->json([
            'success' => true,
            'role' => 'collaborator',
            'kpis' => [
                'requests_in_progress' => $inProgress,
                'requests_approved' => $approved,
                'articles_received_month' => $articlesReceived,
                'latest_request' => $latest ? $latest->reference : '-',
                'latest_status' => $latest ? $latest->status : 'Aucune demande',
            ],
            'recent_requests' => $recentRequests,
        ]);
    }
}
