<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\MaterialRequest;
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

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($sub) use ($search): void {
                $sub->whereHas('article', function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                })
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhere('from_location', 'like', "%{$search}%")
                    ->orWhere('to_location', 'like', "%{$search}%");
            });
        }

        $movements = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'count' => $movements->count(),
            'data' => $movements,
        ]);
    }

    /**
     * Enregistrer un mouvement de stock (Entrée, Sortie autorisée, Transfert, Retour, Perte)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'article_id' => 'required|exists:articles,id',
            'type' => 'required|in:Entrée,Sortie,Transfert,Retour,Perte',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string|max:255',
            'material_request_id' => 'nullable|exists:material_requests,id',
            'from_location' => 'nullable|string|max:255',
            'to_location' => 'nullable|string|max:255',
            'from_service' => 'nullable|string|max:255',
            'to_service' => 'nullable|string|max:255',
            'is_emergency' => 'sometimes|boolean',
        ]);

        $userId = $this->resolveActorId($request);

        if ($userId === null) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun utilisateur ne peut être associé à ce mouvement.',
            ], 422);
        }

        $user = User::find($userId);

        if (! $user?->canManageOperations()) {
            return response()->json([
                'success' => false,
                'message' => 'Seul un administrateur ou responsable habilité peut enregistrer un mouvement de stock.',
            ], 403);
        }

        return DB::transaction(function () use ($request, $userId, $validated): JsonResponse {
            $article = Article::query()->lockForUpdate()->findOrFail($validated['article_id']);
            $qty = $validated['quantity'];

            if ($validated['type'] === 'Sortie') {
                $isEmergency = (bool) ($validated['is_emergency'] ?? false);
                if (empty($validated['material_request_id']) && ! $isEmergency) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Une sortie de stock doit obligatoirement être liée à une demande autorisée, sauf procédure d’urgence motivée.',
                    ], 422);
                }

                if (! empty($validated['material_request_id'])) {
                    $linkedRequest = MaterialRequest::find($validated['material_request_id']);
                    if (! $linkedRequest || ! in_array($linkedRequest->status, MaterialRequest::AUTHORIZED_STATUSES, true)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'La demande sélectionnée n’est pas encore validée/autorisée.',
                        ], 422);
                    }
                }
            }

            $previousLocation = $validated['from_location'] ?? $article->location;
            if (in_array($validated['type'], ['Sortie', 'Perte'], true)) {
                if ($article->quantity < $qty) {
                    return response()->json([
                        'success' => false,
                        'message' => "Stock insuffisant ({$article->quantity} disponible(s)).",
                    ], 422);
                }
                $newQty = $article->quantity - $qty;
            } elseif (in_array($validated['type'], ['Entrée', 'Retour'], true)) {
                $newQty = $article->quantity + $qty;
            } else {
                $newQty = $article->quantity;
                if (empty($validated['to_location']) && empty($validated['to_service'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Le nouvel emplacement ou le service destinataire est requis pour un transfert.',
                    ], 422);
                }
            }

            $article->update(array_merge(
                Article::stockState($newQty, $article->threshold),
                $validated['type'] === 'Transfert' && ! empty($validated['to_location']) ? ['location' => $validated['to_location']] : [],
            ));

            $movement = StockMovement::create([
                'article_id' => $article->id,
                'user_id' => $userId,
                'type' => $validated['type'],
                'source' => StockMovement::SOURCE_MANUAL_ADJUSTMENT,
                'quantity' => $qty,
                'material_request_id' => $validated['material_request_id'] ?? null,
                'from_location' => $previousLocation,
                'to_location' => $validated['to_location'] ?? ($validated['type'] === 'Entrée' ? $article->location : null),
                'from_service' => $validated['from_service'] ?? null,
                'to_service' => $validated['to_service'] ?? null,
                'is_emergency' => $validated['is_emergency'] ?? false,
                'reason' => $validated['reason'],
                'justification' => in_array($validated['type'], ['Perte', 'Sortie'], true) ? $validated['reason'] : null,
            ]);

            if ($newQty <= $article->threshold) {
                $this->notifyOperationalManagers(
                    'stock.low',
                    $newQty === 0 ? 'Rupture de stock' : 'Stock faible',
                    "L'article {$article->code} ({$article->name}) est à {$newQty} {$article->unit} (seuil : {$article->threshold}).",
                    '#stock-articles',
                    'notify_on_low_stock',
                );
            }

            $movement->load(['article', 'user', 'materialRequest']);
            $this->recordActivity($request, 'stock.adjusted', $movement, "Mouvement {$validated['type']} de {$qty} sur {$article->code}.", [
                'type' => $validated['type'],
                'reason' => $validated['reason'],
                'is_emergency' => $movement->is_emergency,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Mouvement ({$validated['type']} de {$qty}) enregistré avec succès.",
                'data' => $movement,
                'article' => $article,
            ], 201);
        });
    }

    public function export(Request $request): StreamedResponse
    {
        $query = StockMovement::query()->with(['article', 'user', 'materialRequest']);
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }
        $movements = $query->latest()->get();

        return response()->streamDownload(function () use ($movements): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Date', 'Code Article', 'Article', 'Type', 'Quantité', 'Origine', 'Demande liée', 'Emplacement source', 'Destination', 'Utilisateur', 'Motif'], ';');

            foreach ($movements as $movement) {
                fputcsv($output, [
                    $movement->created_at?->format('d/m/Y H:i'),
                    $movement->article?->code,
                    $movement->article?->name,
                    $movement->type,
                    $movement->quantity,
                    $movement->source,
                    $movement->materialRequest?->reference ?? ($movement->is_emergency ? 'Urgence' : '-'),
                    $movement->from_location ?? $movement->from_service ?? '-',
                    $movement->to_location ?? $movement->to_service ?? '-',
                    $movement->user?->name,
                    $movement->reason,
                ], ';');
            }

            fclose($output);
        }, 'mouvements-stock-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
