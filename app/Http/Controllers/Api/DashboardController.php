<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Asset;
use App\Models\Expense;
use App\Models\MaterialRequest;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    /**
     * Retourne les indicateurs consolidés (KPIs) selon le profil actif
     */
    public function index(Request $request): JsonResponse
    {
        $role = $request->user()?->canManageOperations() ? 'admin' : 'collaborator';
        $userId = $request->user()?->id;

        if ($role === 'admin') {
            $totalArticles = Article::count();
            $lowStockCount = Article::whereColumn('quantity', '<=', 'threshold')->count();
            $outOfStockCount = Article::where('quantity', '<=', 0)->count();
            $pendingRequests = MaterialRequest::whereIn('status', MaterialRequest::PENDING_WORKFLOW_STATUSES)->count();
            $approvedRequests = MaterialRequest::where('status', MaterialRequest::STATUS_VALIDATED)->count();
            $stockValue = (float) Article::selectRaw('SUM(quantity * COALESCE(unit_cost, 0)) as val')->value('val');
            $unjustifiedAmount = Schema::hasTable('expenses')
                ? (float) Expense::all()->sum(fn (Expense $e): float => $e->amount_to_justify)
                : 0.0;

            $categories = Article::select('category', DB::raw('count(*) as count'), DB::raw('sum(quantity) as total_qty'))
                ->groupBy('category')
                ->get();

            $requestsByStatus = MaterialRequest::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status');

            $monthlyLabels = [];
            $monthlyApproved = [];
            $monthlyPending = [];
            $monthlyRejected = [];
            $monthNames = [1 => 'Jan', 2 => 'Fév', 3 => 'Mars', 4 => 'Avr', 5 => 'Mai', 6 => 'Juin', 7 => 'Juil', 8 => 'Août', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Déc'];

            for ($i = 5; $i >= 0; $i--) {
                $monthDate = now()->startOfMonth()->subMonths($i);
                $start = $monthDate->copy()->startOfMonth();
                $end = $monthDate->copy()->endOfMonth();
                $monthlyLabels[] = $monthNames[(int) $monthDate->month].' '.$monthDate->format('y');

                $monthQuery = MaterialRequest::whereBetween('created_at', [$start, $end]);
                $monthlyApproved[] = (clone $monthQuery)->where('status', MaterialRequest::STATUS_VALIDATED)->count();
                $monthlyPending[] = (clone $monthQuery)->whereIn('status', MaterialRequest::PENDING_WORKFLOW_STATUSES)->count();
                $monthlyRejected[] = (clone $monthQuery)->where('status', MaterialRequest::STATUS_REJECTED)->count();
            }

            $urgentRequests = MaterialRequest::with(['user', 'items.article'])
                ->whereIn('status', MaterialRequest::PENDING_WORKFLOW_STATUSES)
                ->orderByRaw("CASE WHEN urgency = 'Urgente' THEN 0 ELSE 1 END")
                ->orderBy('created_at', 'desc')
                ->limit(6)
                ->get();

            $criticalStock = Article::whereColumn('quantity', '<=', 'threshold')
                ->orderBy('quantity', 'asc')
                ->limit(8)
                ->get();

            return response()->json([
                'success' => true,
                'role' => 'admin',
                'kpis' => [
                    'total_articles' => $totalArticles,
                    'low_stock_count' => $lowStockCount,
                    'out_of_stock_count' => $outOfStockCount,
                    'pending_requests' => $pendingRequests,
                    'approved_requests' => $approvedRequests,
                    'stock_value' => round($stockValue, 2),
                    'unjustified_expenses' => round($unjustifiedAmount, 2),
                    'assets_total' => Schema::hasTable('assets') ? Asset::count() : 0,
                    'vehicles_alerts' => Schema::hasTable('vehicles') ? Vehicle::all()->filter(fn (Vehicle $v): bool => $v->has_alert)->count() : 0,
                ],
                'requests_by_status' => $requestsByStatus,
                'monthly_evolution' => [
                    'labels' => $monthlyLabels,
                    'approved' => $monthlyApproved,
                    'pending' => $monthlyPending,
                    'rejected' => $monthlyRejected,
                ],
                'categories_distribution' => $categories,
                'urgent_requests' => $urgentRequests,
                'critical_stock' => $criticalStock,
            ]);
        }

        // Employé / Collaborateur
        $myRequestsQuery = MaterialRequest::query();
        if ($userId) {
            $myRequestsQuery->where('user_id', $userId);
        }

        $inProgress = (clone $myRequestsQuery)->whereIn('status', MaterialRequest::PENDING_WORKFLOW_STATUSES)->count();
        $approved = (clone $myRequestsQuery)->where('status', MaterialRequest::STATUS_VALIDATED)->count();
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
                ->where('material_requests.status', MaterialRequest::STATUS_VALIDATED)
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
