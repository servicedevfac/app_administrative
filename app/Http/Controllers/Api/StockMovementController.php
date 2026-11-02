<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockMovementController extends Controller
{
    /**
     * Historique des mouvements de stock
     */
    public function index(Request $request): JsonResponse
    {
        $query = StockMovement::with(['article', 'user', 'materialRequest']);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('article_id')) {
            $query->where('article_id', $request->article_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('article', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            })->orWhere('reason', 'like', "%{$search}%");
        }

        $movements = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'count' => $movements->count(),
            'data' => $movements,
        ]);
    }

    /**
     * Enregistrer manuellement une entrée ou sortie de matériel (Magasinier)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'article_id' => 'required|exists:articles,id',
            'type' => 'required|in:Entrée,Sortie',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string|max:255',
        ]);

        $userId = $this->resolveActorId($request);

        if ($userId === null) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun utilisateur ne peut être associé à ce mouvement.',
            ], 422);
        }

        $user = User::find($userId);

        if (! $user?->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Seul un administrateur ou gestionnaire peut ajuster directement le stock.',
            ], 403);
        }

        return DB::transaction(function () use ($request, $userId, $validated) {
            $article = Article::findOrFail($validated['article_id']);
            $qty = $validated['quantity'];

            if ($validated['type'] === 'Sortie') {
                if ($article->quantity < $qty) {
                    return response()->json([
                        'success' => false,
                        'message' => "Stock insuffisant ({$article->quantity} disponibles).",
                    ], 422);
                }
                $newQty = $article->quantity - $qty;
            } else {
                $newQty = $article->quantity + $qty;
            }

            $article->update(Article::stockState($newQty, $article->threshold));

            $movement = StockMovement::create([
                'article_id' => $article->id,
                'user_id' => $userId,
                'type' => $validated['type'],
                'source' => StockMovement::SOURCE_MANUAL_ADJUSTMENT,
                'quantity' => $qty,
                'reason' => $validated['reason'],
            ]);

            $movement->load(['article', 'user']);
            $this->recordActivity($request, 'stock.adjusted', $movement, "Ajustement de {$qty} unité(s) sur {$article->code}.", [
                'type' => $validated['type'],
                'reason' => $validated['reason'],
            ]);

            return response()->json([
                'success' => true,
                'message' => "Ajustement ({$validated['type']} de {$qty}) enregistré avec succès.",
                'data' => $movement,
                'article' => $article,
            ], 201);
        });
    }

    public function export(): StreamedResponse
    {
        $movements = StockMovement::query()->with(['article', 'user', 'materialRequest'])->latest()->get();

        return response()->streamDownload(function () use ($movements): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Date', 'Article', 'Type', 'Quantité', 'Origine', 'Demande', 'Utilisateur', 'Motif'], ';');

            foreach ($movements as $movement) {
                fputcsv($output, [
                    $movement->created_at?->format('d/m/Y H:i'),
                    $movement->article?->name,
                    $movement->type,
                    $movement->quantity,
                    $movement->source,
                    $movement->materialRequest?->reference,
                    $movement->user?->name,
                    $movement->reason,
                ], ';');
            }

            fclose($output);
        }, 'mouvements-stock-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
