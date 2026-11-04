<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArticleController extends Controller
{
    /**
     * Liste des articles en stock avec filtres
     */
    public function index(Request $request): JsonResponse
    {
        $query = Article::query();

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('location')) {
            $query->where('location', $request->location);
        }

        if ($request->filled('status')) {
            if ($request->status === 'rupture' || $request->status === 'En rupture') {
                $query->where('quantity', '<=', 0);
            } elseif ($request->status === 'faible' || $request->status === 'Stock faible') {
                $query->where('quantity', '>', 0)->whereColumn('quantity', '<=', 'threshold');
            } elseif ($request->status === 'alerte') {
                $query->whereColumn('quantity', '<=', 'threshold');
            } elseif ($request->status === 'dispo' || $request->status === 'En stock') {
                $query->whereColumn('quantity', '>', 'threshold');
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('supplier', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $articles = $query->orderBy('id', 'desc')->get();
        $totalStockValue = (float) $articles->sum(fn (Article $a): float => $a->quantity * (float) ($a->unit_cost ?? 0));

        return response()->json([
            'success' => true,
            'count' => $articles->count(),
            'total_stock_value' => round($totalStockValue, 2),
            'data' => $articles,
        ]);
    }

    /**
     * État de réapprovisionnement : liste des articles en rupture ou sous le seuil minimum à commander
     */
    public function replenishment(): JsonResponse
    {
        $articles = Article::query()
            ->whereColumn('quantity', '<=', 'threshold')
            ->orderBy('quantity')
            ->orderBy('name')
            ->get()
            ->map(function (Article $article): array {
                $targetMax = $article->maximum_threshold && $article->maximum_threshold > $article->threshold
                    ? $article->maximum_threshold
                    : max($article->threshold * 2, $article->threshold + 5);
                $suggestedQty = max(1, $targetMax - $article->quantity);
                $unitCost = (float) ($article->unit_cost ?? 0);

                return [
                    'id' => $article->id,
                    'code' => $article->code,
                    'name' => $article->name,
                    'category' => $article->category,
                    'unit' => $article->unit,
                    'location' => $article->location,
                    'supplier' => $article->supplier,
                    'quantity' => $article->quantity,
                    'threshold' => $article->threshold,
                    'maximum_threshold' => $targetMax,
                    'suggested_order_quantity' => $suggestedQty,
                    'unit_cost' => $unitCost,
                    'estimated_order_cost' => round($suggestedQty * $unitCost, 2),
                    'urgency' => $article->quantity <= 0 ? 'Rupture critique' : 'Sous seuil minimum',
                ];
            });

        return response()->json([
            'success' => true,
            'count' => $articles->count(),
            'total_estimated_cost' => round((float) $articles->sum('estimated_order_cost'), 2),
            'data' => $articles->values(),
        ]);
    }

    /**
     * Export CSV de l'état de réapprovisionnement
     */
    public function exportReplenishment(): StreamedResponse
    {
        $articles = Article::query()
            ->whereColumn('quantity', '<=', 'threshold')
            ->orderBy('quantity')
            ->orderBy('name')
            ->get();

        return response()->streamDownload(function () use ($articles): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Code', 'Désignation', 'Catégorie', 'Unité', 'Stock actuel', 'Seuil min', 'Seuil cible', 'Qté à commander', 'Fournisseur', 'Coût unitaire', 'Montant estimé'], ';');

            foreach ($articles as $article) {
                $targetMax = $article->maximum_threshold && $article->maximum_threshold > $article->threshold
                    ? $article->maximum_threshold
                    : max($article->threshold * 2, $article->threshold + 5);
                $suggestedQty = max(1, $targetMax - $article->quantity);
                $unitCost = (float) ($article->unit_cost ?? 0);

                fputcsv($output, [
                    $article->code,
                    $article->name,
                    $article->category,
                    $article->unit,
                    $article->quantity,
                    $article->threshold,
                    $targetMax,
                    $suggestedQty,
                    $article->supplier ?? '-',
                    $unitCost,
                    round($suggestedQty * $unitCost, 2),
                ], ';');
            }

            fclose($output);
        }, 'etat-reapprovisionnement-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Enregistrer un nouvel article
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => ['required', 'string', Rule::exists('article_categories', 'name')->where('is_active', true)],
            'unit' => ['required', 'string', Rule::exists('measurement_units', 'name')->where('is_active', true)],
            'quantity' => 'required|integer|min:0',
            'threshold' => 'required|integer|min:1',
            'maximum_threshold' => 'nullable|integer|gte:threshold',
            'unit_cost' => 'nullable|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
        ]);

        $stockState = Article::stockState($validated['quantity'], $validated['threshold']);
        $userId = $this->resolveActorId($request, 'created_by');

        $article = DB::transaction(function () use ($validated, $stockState, $userId): Article {
            $highestCodeNumber = Article::query()
                ->lockForUpdate()
                ->pluck('code')
                ->reduce(function (int $highestNumber, string $code): int {
                    if (preg_match('/^ART-(\d+)$/', $code, $matches) !== 1) {
                        return $highestNumber;
                    }

                    return max($highestNumber, (int) $matches[1]);
                }, 0);

            $article = Article::create(array_merge($validated, [
                'code' => sprintf('ART-%03d', $highestCodeNumber + 1),
                'status' => $stockState['status'],
                'is_available' => $stockState['is_available'],
                'created_by' => $userId,
            ]));

            if ($validated['quantity'] > 0 && $userId) {
                StockMovement::create([
                    'article_id' => $article->id,
                    'user_id' => $userId,
                    'type' => 'Entrée',
                    'source' => StockMovement::SOURCE_INITIAL_STOCK,
                    'quantity' => $validated['quantity'],
                    'to_location' => $article->location,
                    'reason' => "Stock initial / Création de l'article",
                ]);
            }

            return $article;
        }, attempts: 5);

        $this->recordActivity($request, 'article.created', $article, "Article {$article->code} ({$article->name}) créé.");

        return response()->json([
            'success' => true,
            'message' => "Article \"{$article->name}\" ({$article->code}) ajouté au stock avec succès.",
            'data' => $article,
        ], 201);
    }

    /**
     * Détails d'un article
     */
    public function show(int $id): JsonResponse
    {
        $article = Article::with('stockMovements.user')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $article,
        ]);
    }

    /**
     * Mettre à jour un article
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $article = Article::findOrFail($id);
        $before = $article->only(['name', 'category', 'unit', 'quantity', 'threshold', 'maximum_threshold', 'unit_cost', 'supplier', 'location']);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'category' => ['sometimes', 'string', Rule::exists('article_categories', 'name')->where('is_active', true)],
            'unit' => ['sometimes', 'string', Rule::exists('measurement_units', 'name')->where('is_active', true)],
            'quantity' => 'sometimes|integer|min:0',
            'threshold' => 'sometimes|integer|min:1',
            'maximum_threshold' => 'nullable|integer|min:1',
            'unit_cost' => 'nullable|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
        ]);

        if (isset($validated['quantity']) || isset($validated['threshold'])) {
            $qty = $validated['quantity'] ?? $article->quantity;
            $thresh = $validated['threshold'] ?? $article->threshold;
            $stockState = Article::stockState($qty, $thresh);
            $article->status = $stockState['status'];
            $article->is_available = $stockState['is_available'];
        }

        $article->update($validated);
        $this->recordActivity($request, 'article.updated', $article, "Article {$article->code} mis à jour.", [
            'before' => $before,
            'after' => $article->only(['name', 'category', 'unit', 'quantity', 'threshold', 'maximum_threshold', 'unit_cost', 'supplier', 'location']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Article mis à jour avec succès.',
            'data' => $article,
        ]);
    }

    /**
     * Supprimer un article (protégé si historisé)
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $article = Article::findOrFail($id);
        if ($article->requestItems()->exists() || $article->stockMovements()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cet article possède un historique de mouvements ou de demandes et ne peut pas être supprimé.',
            ], 422);
        }

        $this->recordActivity($request, 'article.deleted', $article, "Article {$article->code} supprimé.");
        $article->delete();

        return response()->json([
            'success' => true,
            'message' => 'Article supprimé du magasin.',
        ]);
    }

    public function export(): StreamedResponse
    {
        $articles = Article::query()->orderBy('category')->orderBy('name')->get();

        return response()->streamDownload(function () use ($articles): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Code', 'Article', 'Catégorie', 'Unité', 'Quantité', 'Seuil Min', 'Seuil Max', 'Coût unitaire', 'Valeur totale', 'Fournisseur', 'Statut', 'Emplacement'], ';');

            foreach ($articles as $article) {
                $unitCost = (float) ($article->unit_cost ?? 0);
                fputcsv($output, [
                    $article->code,
                    $article->name,
                    $article->category,
                    $article->unit,
                    $article->quantity,
                    $article->threshold,
                    $article->maximum_threshold ?? '-',
                    $unitCost,
                    round($article->quantity * $unitCost, 2),
                    $article->supplier ?? '-',
                    $article->status,
                    $article->location ?? '-',
                ], ';');
            }

            fclose($output);
        }, 'etat-stock-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
