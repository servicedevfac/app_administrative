<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    /**
     * Liste des articles en stock avec filtres
     */
    public function index(Request $request)
    {
        $query = Article::query();

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('status')) {
            if ($request->status === 'faible' || $request->status === 'Stock faible') {
                $query->whereColumn('quantity', '<=', 'threshold');
            } elseif ($request->status === 'dispo' || $request->status === 'En stock') {
                $query->whereColumn('quantity', '>', 'threshold');
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $articles = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'count' => $articles->count(),
            'data' => $articles,
        ]);
    }

    /**
     * Enregistrer un nouvel article (Magasinier / Administrateur uniquement)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:articles,code',
            'name' => 'required|string|max:255',
            'category' => 'required|string',
            'unit' => 'required|string',
            'quantity' => 'required|integer|min:0',
            'threshold' => 'required|integer|min:1',
            'location' => 'nullable|string',
        ]);

        $stockState = Article::stockState($validated['quantity'], $validated['threshold']);
        $userId = $this->resolveActorId($request, 'created_by');

        $article = Article::create(array_merge($validated, [
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
                'reason' => 'Stock initial / Création de l\'article',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "Article \"{$article->name}\" ajouté au stock avec succès.",
            'data' => $article,
        ], 201);
    }

    /**
     * Détails d'un article
     */
    public function show($id)
    {
        $article = Article::with('stockMovements')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $article,
        ]);
    }

    /**
     * Mettre à jour un article
     */
    public function update(Request $request, $id)
    {
        $article = Article::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'category' => 'sometimes|string',
            'unit' => 'sometimes|string',
            'quantity' => 'sometimes|integer|min:0',
            'threshold' => 'sometimes|integer|min:1',
            'location' => 'nullable|string',
        ]);

        if (isset($validated['quantity']) || isset($validated['threshold'])) {
            $qty = $validated['quantity'] ?? $article->quantity;
            $thresh = $validated['threshold'] ?? $article->threshold;
            $stockState = Article::stockState($qty, $thresh);
            $article->status = $stockState['status'];
            $article->is_available = $stockState['is_available'];
        }

        $article->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Article mis à jour avec succès.',
            'data' => $article,
        ]);
    }

    /**
     * Supprimer un article
     */
    public function destroy($id)
    {
        $article = Article::findOrFail($id);
        $article->delete();

        return response()->json([
            'success' => true,
            'message' => 'Article supprimé du magasin.',
        ]);
    }
}
