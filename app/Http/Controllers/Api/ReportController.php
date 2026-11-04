<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Asset;
use App\Models\Expense;
use App\Models\InventorySession;
use App\Models\MaterialRequest;
use App\Models\SavedReport;
use App\Models\StockMovement;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);
        $snapshot = $this->compileReportData($request, $from, $to);

        return response()->json([
            'success' => true,
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'data' => $snapshot,
            'saved_reports' => Schema::hasTable('saved_reports')
                ? SavedReport::with('generator:id,name')->latest()->limit(30)->get()
                : [],
        ]);
    }

    /**
     * Génère et archive un rapport officiel (Hebdomadaire, Mensuel, Inventaire, Personnalisé)
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['Hebdomadaire', 'Mensuel', 'Inventaire', 'Personnalisé'])],
            'title' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'service' => ['nullable', 'string'],
            'category' => ['nullable', 'string'],
            'user_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
            'difficulties' => ['nullable', 'string', 'max:4000'],
            'action_items' => ['nullable', 'string', 'max:4000'],
        ]);

        if ($validated['type'] === 'Hebdomadaire' && empty($validated['from']) && empty($validated['to'])) {
            $from = now()->startOfWeek();
            $to = now()->endOfWeek();
        } elseif ($validated['type'] === 'Mensuel' && empty($validated['from']) && empty($validated['to'])) {
            $from = now()->startOfMonth();
            $to = now()->endOfMonth();
        } else {
            [$from, $to] = $this->period($request);
        }

        $snapshot = $this->compileReportData($request, $from, $to);

        $report = DB::transaction(function () use ($request, $validated, $from, $to, $snapshot): SavedReport {
            $nextNumber = (int) SavedReport::query()->lockForUpdate()->max('id') + 1;
            $reference = 'RAP-'.now()->format('Y').'-'.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
            $defaultTitle = "Rapport {$validated['type']} du {$from->format('d/m/Y')} au {$to->format('d/m/Y')}";

            return SavedReport::create([
                'reference' => $reference,
                'type' => $validated['type'],
                'title' => ($validated['title'] ?? null) ?: $defaultTitle,
                'period_from' => $from->toDateString(),
                'period_to' => $to->toDateString(),
                'difficulties' => $validated['difficulties'] ?? null,
                'action_items' => $validated['action_items'] ?? null,
                'filters' => $request->only(['service', 'category', 'user_id', 'status']),
                'snapshot' => $snapshot,
                'generated_by' => $request->user()?->id,
            ]);
        }, attempts: 5);

        $this->recordActivity($request, 'report.generated', $report, "Rapport {$report->reference} ({$report->type}) généré et archivé.");

        return response()->json([
            'success' => true,
            'message' => "Rapport {$report->reference} généré et enregistré dans l'historique.",
            'data' => $report->load('generator:id,name'),
        ], 201);
    }

    public function showSaved(SavedReport $savedReport): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $savedReport->load('generator:id,name'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->period($request);
        $query = $this->filteredRequestsQuery($request, $from, $to)->with(['user', 'assignee']);
        $rows = $query->latest()->get();
        $snapshot = $this->compileReportData($request, $from, $to);

        return response()->streamDownload(function () use ($rows, $snapshot, $from, $to): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, ['RAPPORT CONSOLIDÉ DE GESTION INTERNE — GESTION+'], ';');
            fputcsv($output, ['Période', $from->format('d/m/Y').' au '.$to->format('d/m/Y'), 'Généré le', now()->format('d/m/Y H:i')], ';');
            fputcsv($output, [], ';');

            fputcsv($output, ['SYNTHÈSE FINANCIÈRE & OPÉRATIONNELLE'], ';');
            fputcsv($output, ['Indicateur', 'Valeur'], ';');
            fputcsv($output, ['Total demandes période', $snapshot['totals']['requests']], ';');
            fputcsv($output, ['Délai moyen de traitement (heures)', $snapshot['average_processing_hours']], ';');
            fputcsv($output, ['Dépenses autorisées (FCFA)', $snapshot['expenses']['authorized']], ';');
            fputcsv($output, ['Dépenses engagées (FCFA)', $snapshot['expenses']['committed']], ';');
            fputcsv($output, ['Dépenses décaissées (FCFA)', $snapshot['expenses']['disbursed']], ';');
            fputcsv($output, ['Dépenses justifiées (FCFA)', $snapshot['expenses']['justified']], ';');
            fputcsv($output, ['Montant restant à justifier (FCFA)', $snapshot['expenses']['unjustified']], ';');
            fputcsv($output, ['Valorisation globale du stock (FCFA)', $snapshot['stock']['value']], ';');
            fputcsv($output, ['Articles sous seuil / rupture', $snapshot['stock']['low']], ';');
            fputcsv($output, ['Mouvements de stock sur la période', $snapshot['stock']['movements']], ';');
            fputcsv($output, ['Biens du patrimoine (Total / Affectés / Endommagés)', "{$snapshot['assets']['total']} / {$snapshot['assets']['assigned']} / {$snapshot['assets']['damaged']}"], ';');
            fputcsv($output, ['Véhicules (Total / En maintenance / Alertes)', "{$snapshot['vehicles']['total']} / {$snapshot['vehicles']['maintenance']} / {$snapshot['vehicles']['alerts']}"], ';');
            fputcsv($output, [], ';');

            fputcsv($output, ['DÉTAIL DES DEMANDES DE LA PÉRIODE'], ';');
            fputcsv($output, ['Référence', 'Date', 'Demandeur', 'Service', 'Catégorie', 'Motif', 'Urgence', 'Agent affecté', 'Statut', 'En retard'], ';');
            foreach ($rows as $row) {
                fputcsv($output, [
                    $row->reference,
                    $row->created_at?->format('d/m/Y'),
                    $row->user?->name,
                    $row->service,
                    $row->category,
                    $row->motif,
                    $row->urgency,
                    $row->assignee?->name ?? '-',
                    $row->status,
                    $row->is_overdue ? 'Oui' : 'Non',
                ], ';');
            }
            fclose($output);
        }, 'rapport-gestion-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function compileReportData(Request $request, Carbon $from, Carbon $to): array
    {
        $requests = $this->filteredRequestsQuery($request, $from, $to);
        $expenses = Expense::whereBetween('created_at', [$from, $to]);
        $movements = StockMovement::whereBetween('created_at', [$from, $to]);

        if ($request->filled('service')) {
            $service = $request->string('service')->toString();
            $expenses->whereHas('materialRequest', fn ($q) => $q->where('service', $service));
        }

        $completedRequests = (clone $requests)
            ->where(function ($sub): void {
                $sub->whereNotNull('closed_at')->orWhereNotNull('approved_at');
            })
            ->get(['created_at', 'approved_at', 'closed_at']);

        $averageProcessingHours = $completedRequests->isEmpty()
            ? 0.0
            : round((float) $completedRequests->average(function (MaterialRequest $item): float {
                $end = $item->closed_at ?? $item->approved_at ?? now();

                return (float) $item->created_at->diffInHours($end);
            }), 1);

        // Comparaison avec la période précédente équivalente
        $daysSpan = max(1, (int) $from->diffInDays($to) + 1);
        $prevTo = $from->copy()->subSecond();
        $prevFrom = $prevTo->copy()->subDays($daysSpan - 1)->startOfDay();

        $currReqCount = (clone $requests)->count();
        $prevReqCount = $this->filteredRequestsQuery($request, $prevFrom, $prevTo)->count();

        $currDisbursed = (float) (clone $expenses)->sum('disbursed_amount');
        $prevDisbursed = (float) Expense::whereBetween('created_at', [$prevFrom, $prevTo])->sum('disbursed_amount');

        $currMovements = (clone $movements)->count();
        $prevMovements = StockMovement::whereBetween('created_at', [$prevFrom, $prevTo])->count();

        $expenseCollection = (clone $expenses)->get();
        $vehicles = Vehicle::all();

        return [
            'totals' => [
                'requests' => $currReqCount,
                'pending_validation' => (clone $requests)->whereIn('status', MaterialRequest::PENDING_WORKFLOW_STATUSES)->count(),
                'validated' => (clone $requests)->whereIn('status', array_merge(MaterialRequest::AUTHORIZED_STATUSES, [MaterialRequest::STATUS_CLOSED]))->count(),
                'rejected' => (clone $requests)->where('status', MaterialRequest::STATUS_REJECTED)->count(),
            ],
            'comparison' => [
                'previous_period' => ['from' => $prevFrom->toDateString(), 'to' => $prevTo->toDateString()],
                'requests' => ['current' => $currReqCount, 'previous' => $prevReqCount, 'delta' => $currReqCount - $prevReqCount],
                'disbursed_expenses' => ['current' => $currDisbursed, 'previous' => $prevDisbursed, 'delta' => round($currDisbursed - $prevDisbursed, 2)],
                'stock_movements' => ['current' => $currMovements, 'previous' => $prevMovements, 'delta' => $currMovements - $prevMovements],
            ],
            'requests_by_status' => (clone $requests)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'requests_by_category' => (clone $requests)->selectRaw('category, COUNT(*) as total')->groupBy('category')->pluck('total', 'category'),
            'average_processing_hours' => $averageProcessingHours,
            'expenses' => [
                'estimated' => (float) $expenseCollection->sum('estimated_amount'),
                'authorized' => (float) $expenseCollection->sum('authorized_amount'),
                'committed' => (float) $expenseCollection->sum('committed_amount'),
                'disbursed' => $currDisbursed,
                'justified' => (float) $expenseCollection->sum('justified_amount'),
                'unjustified' => round((float) $expenseCollection->sum(fn (Expense $e): float => $e->amount_to_justify), 2),
                'remaining' => round((float) $expenseCollection->sum(fn (Expense $e): float => $e->remaining_amount), 2),
            ],
            'stock' => [
                'value' => round((float) Article::selectRaw('SUM(quantity * COALESCE(unit_cost, 0)) as value')->value('value'), 2),
                'low' => Article::whereColumn('quantity', '<=', 'threshold')->count(),
                'out_of_stock' => Article::where('quantity', '<=', 0)->count(),
                'movements' => $currMovements,
                'movements_by_type' => (clone $movements)->selectRaw('type, COUNT(*) as total, SUM(quantity) as qty')->groupBy('type')->get(),
            ],
            'inventories' => InventorySession::whereBetween('created_at', [$from, $to])
                ->with(['items.article'])
                ->withCount(['items as variances_count' => fn ($query) => $query->where('variance', '!=', 0)])
                ->get(),
            'assets' => [
                'total' => Asset::count(),
                'assigned' => Asset::where('availability', 'Affecté')->count(),
                'available' => Asset::where('availability', 'Disponible')->count(),
                'damaged' => Asset::whereIn('condition', ['Endommagé', 'Hors service'])->count(),
            ],
            'vehicles' => [
                'total' => $vehicles->count(),
                'available' => $vehicles->where('status', 'Disponible')->count(),
                'in_service' => $vehicles->where('status', 'En service')->count(),
                'maintenance' => $vehicles->whereIn('status', ['Maintenance', 'Immobilisé'])->count(),
                'alerts' => $vehicles->filter(fn (Vehicle $v): bool => $v->has_alert)->count(),
            ],
            'late_requests' => (clone $requests)
                ->whereNotIn('status', array_merge(MaterialRequest::TERMINAL_STATUSES, [MaterialRequest::STATUS_DRAFT, MaterialRequest::STATUS_EXECUTED]))
                ->whereDate('date_needed', '<', today())
                ->with('user:id,name,service')
                ->latest()
                ->get(),
        ];
    }

    private function filteredRequestsQuery(Request $request, Carbon $from, Carbon $to): Builder
    {
        $query = MaterialRequest::whereBetween('created_at', [$from, $to]);

        if ($request->filled('service')) {
            $query->where('service', $request->string('service')->toString());
        }
        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return $query;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(Request $request): array
    {
        $from = $request->date('from')?->startOfDay() ?? now()->startOfMonth();
        $to = $request->date('to')?->endOfDay() ?? now()->endOfDay();

        return [$from, $to];
    }
}
