<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdministrativeDocument;
use App\Models\Article;
use App\Models\MaterialRequest;
use App\Models\RequestDecision;
use App\Models\RequestItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaterialRequestController extends Controller
{
    /**
     * Liste des demandes (Toutes pour l'admin, personnelles pour le collaborateur)
     */
    public function index(Request $request): JsonResponse
    {
        $query = MaterialRequest::with(['user', 'items.article', 'approver', 'decisions.user']);

        if (! $request->user()?->isAdmin()) {
            $query->where('user_id', $request->user()?->id);
        }

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
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
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
            'save_as_draft' => 'sometimes|boolean',
        ]);

        return DB::transaction(function () use ($request, $validated) {
            $lastId = MaterialRequest::max('id') ?? 0;
            $reference = 'DEM-'.now()->format('Y').'-'.str_pad($lastId + 1, 5, '0', STR_PAD_LEFT);
            $isDraft = (bool) ($validated['save_as_draft'] ?? false);
            $status = $isDraft ? MaterialRequest::STATUS_DRAFT : MaterialRequest::STATUS_PENDING;

            $materialRequest = MaterialRequest::create([
                'reference' => $reference,
                'user_id' => $request->user()->id,
                'type_operation' => $validated['type_operation'] ?? 'Emprunt temporaire',
                'motif' => $validated['motif'],
                'service' => $validated['service'],
                'date_needed' => $validated['date_needed'] ?? now()->toDateString(),
                'date_return' => $validated['date_return'] ?? null,
                'urgency' => $validated['urgency'] ?? 'Normale',
                'status' => $status,
                'submitted_at' => $isDraft ? null : now(),
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

            $this->recordDecision($materialRequest, $request->user(), 'created', null, $status, $validated['notes'] ?? null);
            $this->recordActivity($request, 'request.created', $materialRequest, "Demande {$reference} créée.", ['status' => $status]);

            $materialRequest->load(['items.article', 'user']);

            return response()->json([
                'success' => true,
                'message' => $isDraft
                    ? "Brouillon {$reference} enregistré."
                    : "Demande {$reference} enregistrée et transmise pour approbation.",
                'data' => $materialRequest,
            ], 201);
        });
    }

    /**
     * Détails d'une demande
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $materialRequest = MaterialRequest::with(['user', 'items.article', 'approver', 'documents', 'decisions.user'])
            ->where('id', $id)
            ->orWhere('reference', $id)
            ->firstOrFail();

        if (! $request->user()?->isAdmin() && $materialRequest->user_id !== $request->user()?->id) {
            abort(403, 'Vous ne pouvez consulter que vos propres demandes.');
        }

        return response()->json([
            'success' => true,
            'data' => $materialRequest,
        ]);
    }

    /**
     * Approbation par le Gestionnaire + Décompte du stock + Génération automatique du Bon de Décharge
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $materialRequest = MaterialRequest::with(['user', 'items.article'])
            ->where('id', $id)
            ->orWhere('reference', $id)
            ->firstOrFail();

        if ($materialRequest->status !== MaterialRequest::STATUS_PENDING) {
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
                'status' => MaterialRequest::STATUS_APPROVED,
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

        $this->recordDecision($materialRequest, $request->user(), 'approved', MaterialRequest::STATUS_PENDING, MaterialRequest::STATUS_APPROVED);
        $this->recordActivity($request, 'request.approved', $materialRequest, "Demande {$materialRequest->reference} approuvée.");

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
    public function reject(Request $request, string $id): JsonResponse
    {
        $materialRequest = MaterialRequest::where('id', $id)->orWhere('reference', $id)->firstOrFail();

        $validated = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        if ($materialRequest->status !== MaterialRequest::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Seule une demande en attente peut être rejetée.',
            ], 422);
        }

        $materialRequest->update([
            'status' => MaterialRequest::STATUS_REJECTED,
            'rejection_reason' => $validated['reason'],
        ]);

        $this->recordDecision($materialRequest, $request->user(), 'rejected', MaterialRequest::STATUS_PENDING, MaterialRequest::STATUS_REJECTED, $validated['reason']);
        $this->recordActivity($request, 'request.rejected', $materialRequest, "Demande {$materialRequest->reference} rejetée.");

        return response()->json([
            'success' => true,
            'message' => 'Demande rejetée. Le collaborateur a été notifié du motif.',
            'data' => $materialRequest,
        ]);
    }

    /**
     * Clôture / Restitution du matériel prêté (réintégration en stock)
     */
    public function markReturned(Request $request, string $id): JsonResponse
    {
        $materialRequest = MaterialRequest::with(['items.article'])
            ->where('id', $id)
            ->orWhere('reference', $id)
            ->firstOrFail();

        if (! in_array($materialRequest->status, [MaterialRequest::STATUS_APPROVED, MaterialRequest::STATUS_DELIVERED], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Seule une demande approuvée peut être restituée.',
            ], 422);
        }

        $actorId = $this->resolveActorId($request);
        $previousStatus = $materialRequest->status;

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
                'status' => MaterialRequest::STATUS_RETURNED,
            ]);
        });

        $this->recordDecision($materialRequest, $request->user(), 'returned', $previousStatus, MaterialRequest::STATUS_RETURNED);
        $this->recordActivity($request, 'request.returned', $materialRequest, "Matériel de {$materialRequest->reference} restitué.");

        return response()->json([
            'success' => true,
            'message' => 'Matériel restitué avec succès. Les stocks ont été réintégrés.',
            'data' => $materialRequest,
        ]);
    }

    public function submit(Request $request, string $id): JsonResponse
    {
        $materialRequest = MaterialRequest::query()->where('id', $id)->orWhere('reference', $id)->firstOrFail();

        if ($materialRequest->user_id !== $request->user()->id || ! in_array($materialRequest->status, [MaterialRequest::STATUS_DRAFT, MaterialRequest::STATUS_CORRECTION], true)) {
            return response()->json(['success' => false, 'message' => 'Cette demande ne peut pas être soumise.'], 422);
        }

        $previousStatus = $materialRequest->status;
        $materialRequest->update(['status' => MaterialRequest::STATUS_PENDING, 'submitted_at' => now(), 'correction_reason' => null]);
        $this->recordDecision($materialRequest, $request->user(), 'submitted', $previousStatus, MaterialRequest::STATUS_PENDING);
        $this->recordActivity($request, 'request.submitted', $materialRequest, "Demande {$materialRequest->reference} soumise.");

        return response()->json(['success' => true, 'message' => 'Demande soumise pour validation.', 'data' => $materialRequest]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $materialRequest = MaterialRequest::query()->where('id', $id)->orWhere('reference', $id)->firstOrFail();

        if ($materialRequest->user_id !== $request->user()->id || ! in_array($materialRequest->status, [MaterialRequest::STATUS_DRAFT, MaterialRequest::STATUS_PENDING, MaterialRequest::STATUS_CORRECTION], true)) {
            return response()->json(['success' => false, 'message' => 'Cette demande ne peut pas être annulée.'], 422);
        }

        $previousStatus = $materialRequest->status;
        $materialRequest->update(['status' => MaterialRequest::STATUS_CANCELLED, 'closed_at' => now()]);
        $this->recordDecision($materialRequest, $request->user(), 'cancelled', $previousStatus, MaterialRequest::STATUS_CANCELLED);
        $this->recordActivity($request, 'request.cancelled', $materialRequest, "Demande {$materialRequest->reference} annulée par son auteur.");

        return response()->json(['success' => true, 'message' => 'Votre demande a été annulée.', 'data' => $materialRequest]);
    }

    public function requestCorrection(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $materialRequest = MaterialRequest::query()->where('id', $id)->orWhere('reference', $id)->firstOrFail();

        if ($materialRequest->status !== MaterialRequest::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'Seule une demande en attente peut être renvoyée pour correction.'], 422);
        }

        $materialRequest->update(['status' => MaterialRequest::STATUS_CORRECTION, 'correction_reason' => $validated['reason']]);
        $this->recordDecision($materialRequest, $request->user(), 'correction_requested', MaterialRequest::STATUS_PENDING, MaterialRequest::STATUS_CORRECTION, $validated['reason']);
        $this->recordActivity($request, 'request.correction_requested', $materialRequest, "Correction demandée pour {$materialRequest->reference}.");

        return response()->json(['success' => true, 'message' => 'La demande a été renvoyée pour correction.', 'data' => $materialRequest]);
    }

    public function markDelivered(Request $request, string $id): JsonResponse
    {
        $materialRequest = MaterialRequest::query()->where('id', $id)->orWhere('reference', $id)->firstOrFail();

        if ($materialRequest->status !== MaterialRequest::STATUS_APPROVED) {
            return response()->json(['success' => false, 'message' => 'Seule une demande approuvée peut être livrée.'], 422);
        }

        $materialRequest->update(['status' => MaterialRequest::STATUS_DELIVERED, 'delivered_at' => now()]);
        $this->recordDecision($materialRequest, $request->user(), 'delivered', MaterialRequest::STATUS_APPROVED, MaterialRequest::STATUS_DELIVERED);
        $this->recordActivity($request, 'request.delivered', $materialRequest, "Demande {$materialRequest->reference} livrée.");

        return response()->json(['success' => true, 'message' => 'Livraison enregistrée.', 'data' => $materialRequest]);
    }

    public function close(Request $request, string $id): JsonResponse
    {
        $materialRequest = MaterialRequest::query()->where('id', $id)->orWhere('reference', $id)->firstOrFail();

        if (! in_array($materialRequest->status, [MaterialRequest::STATUS_DELIVERED, MaterialRequest::STATUS_RETURNED], true)) {
            return response()->json(['success' => false, 'message' => 'La demande doit être livrée ou restituée avant sa clôture.'], 422);
        }

        $previousStatus = $materialRequest->status;
        $materialRequest->update(['status' => MaterialRequest::STATUS_CLOSED, 'closed_at' => now()]);
        $this->recordDecision($materialRequest, $request->user(), 'closed', $previousStatus, MaterialRequest::STATUS_CLOSED);
        $this->recordActivity($request, 'request.closed', $materialRequest, "Demande {$materialRequest->reference} clôturée.");

        return response()->json(['success' => true, 'message' => 'Demande clôturée.', 'data' => $materialRequest]);
    }

    public function export(): StreamedResponse
    {
        $requests = MaterialRequest::query()->with('user')->latest()->get();

        return response()->streamDownload(function () use ($requests): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Référence', 'Demandeur', 'Service', 'Type', 'Urgence', 'Statut', 'Créée le'], ';');

            foreach ($requests as $materialRequest) {
                fputcsv($output, [
                    $materialRequest->reference,
                    $materialRequest->user?->name,
                    $materialRequest->service,
                    $materialRequest->type_operation,
                    $materialRequest->urgency,
                    $materialRequest->status,
                    $materialRequest->created_at?->format('d/m/Y H:i'),
                ], ';');
            }

            fclose($output);
        }, 'demandes-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function recordDecision(
        MaterialRequest $materialRequest,
        ?User $user,
        string $action,
        ?string $statusFrom,
        string $statusTo,
        ?string $comment = null,
    ): void {
        RequestDecision::create([
            'material_request_id' => $materialRequest->id,
            'user_id' => $user?->id,
            'action' => $action,
            'status_from' => $statusFrom,
            'status_to' => $statusTo,
            'comment' => $comment,
        ]);
    }
}
