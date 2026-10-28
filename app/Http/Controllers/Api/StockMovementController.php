<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockMovementController extends Controller
{
    /**
     * Historique des mouvements de stock
     */
    public function index(Request $request)
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
    public function store(Request $request)
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

        return DB::transaction(function () use ($userId, $validated) {
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

            return response()->json([
                'success' => true,
                'message' => "Ajustement ({$validated['type']} de {$qty}) enregistré avec succès.",
                'data' => $movement,
                'article' => $article,
            ], 201);
        });
    }
}
