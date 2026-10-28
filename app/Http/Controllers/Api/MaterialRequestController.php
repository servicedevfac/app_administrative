<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdministrativeDocument;
use App\Models\Article;
use App\Models\MaterialRequest;
use App\Models\RequestItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaterialRequestController extends Controller
{
    /**
     * Liste des demandes (Toutes pour l'admin, personnelles pour le collaborateur)
     */
    public function index(Request $request)
    {
        $query = MaterialRequest::with(['user', 'items.article', 'approver']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('service')) {
            $query->where('service', $request->service);
        }

        $requests = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'count' => $requests->count(),
            'data' => $requests,
        ]);
    }

    /**
     * Soumission d'une nouvelle demande d'emprunt ou de réquisition par le collaborateur
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'type_operation' => 'nullable|in:Emprunt temporaire,Dotation permanente',
            'motif' => 'required|string|max:255',
            'service' => 'required|string',
            'date_needed' => 'nullable|date',
            'date_return' => 'nullable|date|after_or_equal:date_needed',
            'urgency' => 'nullable|in:Normale,Urgente,Planifiée',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.article_id' => 'required|exists:articles,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($validated) {
            // Générer une référence unique format DEM-2025-XXXXX
            $lastId = MaterialRequest::max('id') ?? 0;
            $reference = 'DEM-2025-'.str_pad($lastId + 1, 5, '0', STR_PAD_LEFT);

            $materialRequest = MaterialRequest::create([
                'reference' => $reference,
                'user_id' => $validated['user_id'],
                'type_operation' => $validated['type_operation'] ?? 'Emprunt temporaire',
                'motif' => $validated['motif'],
                'service' => $validated['service'],
                'date_needed' => $validated['date_needed'] ?? now()->toDateString(),
                'date_return' => $validated['date_return'] ?? null,
                'urgency' => $validated['urgency'] ?? 'Normale',
                'status' => 'En attente',
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                RequestItem::create([
                    'material_request_id' => $materialRequest->id,
                    'article_id' => $item['article_id'],
                    'quantity_requested' => $item['quantity'],
                    'quantity_approved' => 0,
                ]);
            }

            $materialRequest->load(['items.article', 'user']);

            return response()->json([
                'success' => true,
                'message' => "Demande {$reference} enregistrée et transmise au gestionnaire de stock pour approbation.",
                'data' => $materialRequest,
            ], 201);
        });
    }

    /**
     * Détails d'une demande
     */
    public function show($id)
    {
        $request = MaterialRequest::with(['user', 'items.article', 'approver', 'documents'])
            ->where('id', $id)
            ->orWhere('reference', $id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $request,
        ]);
    }

    /**
     * Approbation par le Gestionnaire + Décompte du stock + Génération automatique du Bon de Décharge
     */
    public function approve(Request $request, $id)
    {
        $materialRequest = MaterialRequest::with(['user', 'items.article'])
            ->where('id', $id)
            ->orWhere('reference', $id)
            ->firstOrFail();

        if ($materialRequest->status !== 'En attente') {
            return response()->json([
                'success' => false,
                'message' => 'Seule une demande en attente peut être approuvée.',
            ], 422);
        }

        $approverId = $this->resolveActorId($request, 'approved_by');
        $approver = $approverId === null ? null : User::find($approverId);

        if (! $approver?->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée : Seul un gestionnaire ou administrateur a le droit d\'approuver les demandes.',
            ], 403);
        }

        $insufficientItem = $materialRequest->items->first(
            fn (RequestItem $item): bool => $item->article === null
                || $item->article->quantity < $item->quantity_requested,
        );

        if ($insufficientItem !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Stock insuffisant pour approuver tous les articles de cette demande.',
            ], 422);
        }

        DB::transaction(function () use ($materialRequest, $approverId) {
            // 1. Décompter les articles du stock et valider les quantités
            foreach ($materialRequest->items as $item) {
                $article = $item->article;
                $qty = $item->quantity_requested;

                $item->update([
                    'quantity_approved' => $qty,
                ]);

                if ($article) {
                    $newQty = $article->quantity - $qty;
                    $article->update(Article::stockState($newQty, $article->threshold));

                    // Enregistrer le mouvement de sortie physique
                    StockMovement::create([
                        'article_id' => $article->id,
                        'user_id' => $approverId,
                        'material_request_id' => $materialRequest->id,
                        'type' => 'Sortie',
                        'source' => StockMovement::SOURCE_REQUEST_APPROVAL,
                        'quantity' => $qty,
                        'reason' => "Attribution demande {$materialRequest->reference} - {$materialRequest->user->name}",
                    ]);
                }
            }

            // 2. Mettre à jour le statut de la demande
            $materialRequest->update([
                'status' => 'Approuvée',
                'approved_by' => $approverId,
                'approved_at' => now(),
            ]);

            // 3. Génération automatique du Document Récapitulatif Officiel (Bon de Décharge)
            AdministrativeDocument::create([
                'material_request_id' => $materialRequest->id,
                'title' => "Bon_de_Decharge_{$materialRequest->reference}.pdf",
                'category' => 'Formulaires',
                'type' => 'PDF',
                'service' => $materialRequest->service,
                'file_path' => "documents/bons/Bon_de_Decharge_{$materialRequest->reference}.pdf",
                'file_size' => '142 Ko',
                'reference' => $materialRequest->reference,
                'user_id' => $materialRequest->user_id,
            ]);
        });

        $materialRequest->load(['items.article', 'approver', 'documents']);

        return response()->json([
            'success' => true,
            'message' => "Demande {$materialRequest->reference} approuvée ! Le document récapitulatif officiel (Bon de Décharge) a été généré.",
            'data' => $materialRequest,
        ]);
    }

    /**
     * Rejet de la demande avec motif
     */
    public function reject(Request $request, $id)
    {
        $materialRequest = MaterialRequest::where('id', $id)->orWhere('reference', $id)->firstOrFail();

        $validated = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        if ($materialRequest->status !== 'En attente') {
            return response()->json([
                'success' => false,
                'message' => 'Seule une demande en attente peut être rejetée.',
            ], 422);
        }

        $materialRequest->update([
            'status' => 'Rejetée',
            'rejection_reason' => $validated['reason'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Demande rejetée. Le collaborateur a été notifié du motif.',
            'data' => $materialRequest,
        ]);
    }

    /**
     * Clôture / Restitution du matériel prêté (réintégration en stock)
     */
    public function markReturned(Request $request, $id)
    {
        $materialRequest = MaterialRequest::with(['items.article'])
            ->where('id', $id)
            ->orWhere('reference', $id)
            ->firstOrFail();

        if ($materialRequest->status !== 'Approuvée') {
            return response()->json([
                'success' => false,
                'message' => 'Seule une demande approuvée peut être restituée.',
            ], 422);
        }

        $actorId = $this->resolveActorId($request);

        if ($actorId === null) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun utilisateur ne peut être associé à cette restitution.',
            ], 422);
        }

        DB::transaction(function () use ($actorId, $materialRequest) {
            foreach ($materialRequest->items as $item) {
                if ($item->article) {
                    $returnedQuantity = $item->quantity_approved;
                    $newQuantity = $item->article->quantity + $returnedQuantity;
                    $item->article->update(Article::stockState($newQuantity, $item->article->threshold));

                    StockMovement::create([
                        'article_id' => $item->article->id,
                        'user_id' => $actorId,
                        'material_request_id' => $materialRequest->id,
                        'type' => 'Entrée',
                        'source' => StockMovement::SOURCE_REQUEST_RETURN,
                        'quantity' => $returnedQuantity,
                        'reason' => "Restitution fin d'emprunt : {$materialRequest->reference}",
                    ]);
                }
            }

            $materialRequest->update([
                'status' => 'Restituée',
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Matériel restitué avec succès. Les stocks ont été réintégrés.',
            'data' => $materialRequest,
        ]);
    }
}
