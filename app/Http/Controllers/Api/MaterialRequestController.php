<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdministrativeDocument;
use App\Models\Article;
use App\Models\MaterialRequest;
use App\Models\RequestCategory;
use App\Models\RequestDecision;
use App\Models\RequestItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaterialRequestController extends Controller
{
    /**
     * Liste des demandes selon les responsabilités (Admin/Responsable/Délégataire vs Employé)
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User|null $actor */
        $actor = $request->user();

        $query = MaterialRequest::with(['user', 'items.article', 'verifier', 'approver', 'assignee', 'documents', 'decisions.user']);

        if (! $actor?->canManageOperations()) {
            $query->where(function ($sub) use ($actor): void {
                $sub->where('user_id', $actor?->id)
                    ->orWhere('assigned_to', $actor?->id);
            });
        } elseif (! $actor->isAdmin() && ! $actor->isManager() && $actor->hasActiveDelegation()) {
            $delegatedDepts = $actor->delegationsReceived()
                ->where('is_active', true)
                ->pluck('department')
                ->filter()
                ->values()
                ->all();

            if ($delegatedDepts !== []) {
                $query->where(function ($sub) use ($actor, $delegatedDepts): void {
                    $sub->where('user_id', $actor->id)
                        ->orWhere('assigned_to', $actor->id)
                        ->orWhereIn('service', $delegatedDepts);
                });
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('urgency')) {
            $query->where('urgency', $request->urgency);
        }

        if ($request->filled('user_id') && $actor?->canManageOperations()) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('service')) {
            $query->where('service', $request->service);
        }

        if ($request->boolean('overdue')) {
            $query->whereNotIn('status', array_merge(MaterialRequest::TERMINAL_STATUSES, [MaterialRequest::STATUS_DRAFT, MaterialRequest::STATUS_EXECUTED]))
                ->whereDate('date_needed', '<', today());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($sub) use ($search): void {
                $sub->where('reference', 'like', "%{$search}%")
                    ->orWhere('motif', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('service', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        $requests = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'count' => $requests->count(),
            'data' => $requests,
        ]);
    }

    /**
     * Création d'une nouvelle demande (avec articles en stock et/ou prestation/besoin hors stock)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type_operation' => 'nullable|in:Emprunt temporaire,Dotation permanente,Prestation / Service',
            'category' => 'nullable|string|max:255',
            'motif' => 'required|string|max:255',
            'service_description' => 'nullable|string|max:2000',
            'service' => 'required|string|max:255',
            'date_needed' => 'nullable|date',
            'date_return' => 'nullable|date|after_or_equal:date_needed',
            'urgency' => 'nullable|in:Faible,Normale,Haute,Urgente,Critique,Planifiée',
            'notes' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.article_id' => 'required|exists:articles,id',
            'items.*.quantity' => 'required|integer|min:1',
            'save_as_draft' => 'sometimes|boolean',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240',
        ]);

        $items = $validated['items'] ?? [];
        $categoryName = $validated['category'] ?? 'Autres besoins administratifs et logistiques';
        $requiresItems = true;
        if (Schema::hasTable('request_categories')) {
            $categoryRow = RequestCategory::where('name', $categoryName)->first();
            if ($categoryRow !== null) {
                $requiresItems = $categoryRow->requires_stock_items;
            }
        }

        if ($items === [] && $requiresItems && empty($validated['service_description'])) {
            return response()->json([
                'success' => false,
                'message' => 'Veuillez sélectionner au moins un article ou décrire précisément le besoin demandé.',
            ], 422);
        }

        return DB::transaction(function () use ($request, $validated, $items, $categoryName): JsonResponse {
            $lastId = (int) MaterialRequest::query()->lockForUpdate()->max('id');
            $reference = 'DEM-'.now()->format('Y').'-'.str_pad((string) ($lastId + 1), 5, '0', STR_PAD_LEFT);
            $isDraft = (bool) ($validated['save_as_draft'] ?? false);
            $status = $isDraft ? MaterialRequest::STATUS_DRAFT : MaterialRequest::STATUS_SUBMITTED;
            $typeOp = ($validated['type_operation'] ?? null) === 'Prestation / Service'
                ? 'Dotation permanente'
                : ($validated['type_operation'] ?? 'Emprunt temporaire');

            $materialRequest = MaterialRequest::create([
                'reference' => $reference,
                'user_id' => $request->user()->id,
                'type_operation' => $typeOp,
                'category' => $categoryName,
                'motif' => $validated['motif'],
                'service_description' => $validated['service_description'] ?? null,
                'service' => $validated['service'],
                'date_needed' => $validated['date_needed'] ?? now()->toDateString(),
                'date_return' => $validated['date_return'] ?? null,
                'urgency' => $validated['urgency'] ?? 'Normale',
                'status' => $status,
                'submitted_at' => $isDraft ? null : now(),
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                RequestItem::create([
                    'material_request_id' => $materialRequest->id,
                    'article_id' => $item['article_id'],
                    'quantity_requested' => $item['quantity'],
                    'quantity_approved' => 0,
                ]);
            }

            if ($request->hasFile('attachment')) {
                $this->storeRequestAttachment($materialRequest, $request->file('attachment'), $request->user()->id);
            }

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $this->storeRequestAttachment($materialRequest, $file, $request->user()->id);
                }
            }

            $this->recordDecision($materialRequest, $request->user(), $isDraft ? 'draft_saved' : 'submitted', null, $status, $validated['notes'] ?? null);
            $this->recordActivity($request, 'request.created', $materialRequest, "Demande {$reference} créée ({$status}).", ['status' => $status, 'category' => $categoryName]);

            if (! $isDraft) {
                $this->notifyOperationalManagers(
                    'request.submitted',
                    'Nouvelle demande à traiter',
                    "La demande {$reference} ({$categoryName}) a été soumise par {$request->user()->name}.",
                    "#request-detail/{$reference}",
                    'notify_on_request_submitted',
                );
            }

            $materialRequest->load(['items.article', 'user', 'documents', 'decisions.user']);

            return response()->json([
                'success' => true,
                'message' => $isDraft
                    ? "Brouillon {$reference} enregistré."
                    : "Demande {$reference} soumise avec succès.",
                'data' => $materialRequest,
            ], 201);
        }, attempts: 5);
    }

    /**
     * Mise à jour d'un brouillon ou d'une demande en attente de complément par son auteur
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $materialRequest = MaterialRequest::where('id', $id)->orWhere('reference', $id)->firstOrFail();

        if ($materialRequest->user_id !== $request->user()->id && ! $request->user()->is_admin) {
            return response()->json(['success' => false, 'message' => 'Seul le demandeur peut modifier sa demande.'], 403);
        }

        $editableStatuses = [
            MaterialRequest::STATUS_DRAFT,
            MaterialRequest::STATUS_CORRECTION,
            MaterialRequest::STATUS_SUBMITTED,
            MaterialRequest::STATUS_PENDING,
            MaterialRequest::STATUS_PENDING_VERIFICATION,
            MaterialRequest::STATUS_PENDING_VALIDATION,
            MaterialRequest::STATUS_PENDING_JUSTIFICATION,
        ];

        if (! in_array($materialRequest->status, $editableStatuses, true)) {
            return response()->json(['success' => false, 'message' => 'Une demande déjà validée, exécutée ou clôturée ne peut plus être modifiée.'], 422);
        }

        $validated = $request->validate([
            'category' => 'sometimes|string|max:255',
            'type_operation' => 'nullable|string|max:255',
            'motif' => 'sometimes|required|string|max:255',
            'service_description' => 'nullable|string|max:2000',
            'service' => 'sometimes|required|string|max:255',
            'date_needed' => 'nullable|date',
            'date_return' => 'nullable|date',
            'urgency' => 'nullable|in:Faible,Normale,Haute,Urgente,Critique,Planifiée',
            'notes' => 'nullable|string',
            'items' => 'sometimes|array',
            'items.*.article_id' => 'required|exists:articles,id',
            'items.*.quantity' => 'required|integer|min:1',
            'submit_now' => 'sometimes|boolean',
        ]);

        $before = $materialRequest->only(['motif', 'category', 'service_description', 'urgency', 'notes', 'status']);

        DB::transaction(function () use ($materialRequest, $validated, $request): void {
            $materialRequest->fill(collect($validated)->except(['items', 'submit_now'])->all());

            if (array_key_exists('items', $validated)) {
                $materialRequest->items()->delete();
                foreach ($validated['items'] as $item) {
                    RequestItem::create([
                        'material_request_id' => $materialRequest->id,
                        'article_id' => $item['article_id'],
                        'quantity_requested' => $item['quantity'],
                        'quantity_approved' => 0,
                    ]);
                }
            }

            $isSubmittedWorkflow = in_array($materialRequest->status, [
                MaterialRequest::STATUS_SUBMITTED,
                MaterialRequest::STATUS_PENDING,
                MaterialRequest::STATUS_PENDING_VERIFICATION,
                MaterialRequest::STATUS_PENDING_VALIDATION,
            ], true);

            if (($validated['submit_now'] ?? false) || $isSubmittedWorkflow) {
                $prev = $materialRequest->status;
                if (in_array($prev, [MaterialRequest::STATUS_CORRECTION, MaterialRequest::STATUS_DRAFT, MaterialRequest::STATUS_PENDING_JUSTIFICATION], true)) {
                    $materialRequest->status = MaterialRequest::STATUS_SUBMITTED;
                    $materialRequest->submitted_at = now();
                }
                $materialRequest->correction_reason = null;
                $materialRequest->save();
                $this->recordDecision($materialRequest, $request->user(), 'updated', $prev, $materialRequest->status, 'Demande modifiée par le demandeur.');
                $this->notifyOperationalManagers('request.submitted', 'Demande modifiée', "La demande {$materialRequest->reference} a été mise à jour par le demandeur.", "#request-detail/{$materialRequest->reference}", 'notify_on_request_submitted');
            } else {
                $materialRequest->save();
            }
        });

        $this->recordActivity($request, 'request.updated', $materialRequest, "Demande {$materialRequest->reference} modifiée.", [
            'before' => $before,
            'after' => $materialRequest->only(['motif', 'category', 'service_description', 'urgency', 'notes', 'status']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Demande mise à jour avec succès.',
            'data' => $materialRequest->fresh(['items.article', 'user', 'documents', 'decisions.user']),
        ]);
    }

    /**
     * Ajout d'une pièce jointe justificative sur une demande existante
     */
    public function addAttachment(Request $request, string $id): JsonResponse
    {
        $materialRequest = MaterialRequest::where('id', $id)->orWhere('reference', $id)->firstOrFail();

        if ($materialRequest->user_id !== $request->user()->id && ! $request->user()->canManageOperations()) {
            return response()->json(['success' => false, 'message' => 'Accès refusé.'], 403);
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240',
        ]);

        $doc = $this->storeRequestAttachment($materialRequest, $request->file('file'), $request->user()->id);
        $this->recordActivity($request, 'request.attachment_added', $materialRequest, "Pièce jointe ajoutée à {$materialRequest->reference} : {$doc->original_name}.");

        return response()->json([
            'success' => true,
            'message' => 'Pièce jointe ajoutée avec succès.',
            'data' => $doc,
        ], 201);
    }

    /**
     * Détails d'une demande
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $materialRequest = MaterialRequest::with([
            'user',
            'items.article',
            'verifier',
            'approver',
            'assignee',
            'documents',
            'decisions.user',
            'expenses.supplier',
            'expenses.attachments',
        ])
            ->where('id', $id)
            ->orWhere('reference', $id)
            ->firstOrFail();

        $actor = $request->user();
        if (! $actor?->canManageOperations() && $materialRequest->user_id !== $actor?->id && $materialRequest->assigned_to !== $actor?->id) {
            abort(403, 'Vous ne pouvez consulter que vos propres demandes.');
        }

        return response()->json([
            'success' => true,
            'data' => $materialRequest,
        ]);
    }

    /**
     * Étape de Vérification (pertinence et disponibilité) -> En attente de vérification / En attente de validation / En attente d'approvisionnement
     */
    public function verify(Request $request, string $id): JsonResponse
    {
        $materialRequest = MaterialRequest::with(['items.article'])->where('id', $id)->orWhere('reference', $id)->firstOrFail();

        if (! in_array($materialRequest->status, MaterialRequest::PENDING_WORKFLOW_STATUSES, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Cette demande ne peut pas être vérifiée dans son statut actuel.',
            ], 422);
        }

        $validated = $request->validate([
            'next_status' => [
                'nullable',
                Rule::in([
                    MaterialRequest::STATUS_PENDING_VERIFICATION,
                    MaterialRequest::STATUS_PENDING_VALIDATION,
                    MaterialRequest::STATUS_PENDING_SUPPLY,
                ]),
            ],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $previousStatus = $materialRequest->status;
        $nextStatus = $validated['next_status'] ?? MaterialRequest::STATUS_PENDING_VALIDATION;

        $materialRequest->update([
            'status' => $nextStatus,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        $this->recordDecision($materialRequest, $request->user(), 'verified', $previousStatus, $nextStatus, $validated['comment'] ?? 'Disponibilité et pertinence vérifiées.');
        $this->recordActivity($request, 'request.verified', $materialRequest, "Demande {$materialRequest->reference} vérifiée -> {$nextStatus}.", [
            'status_from' => $previousStatus,
            'status_to' => $nextStatus,
        ]);
        $this->sendSystemNotification(
            $materialRequest->user_id,
            'request.verified',
            'Suivi de votre demande',
            "Votre demande {$materialRequest->reference} est passée au statut « {$nextStatus} ».",
            "#request-detail/{$materialRequest->reference}",
            'notify_on_request_decision',
        );

        return response()->json([
            'success' => true,
            'message' => "Statut mis à jour : {$nextStatus}.",
            'data' => $materialRequest->fresh(['user', 'items.article', 'verifier', 'decisions.user']),
        ]);
    }

    /**
     * Validation de la demande + Décompte du stock + Génération automatique du Bon de Décharge
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $materialRequest = MaterialRequest::with(['user', 'items.article'])
            ->where('id', $id)
            ->orWhere('reference', $id)
            ->firstOrFail();

        $approvableStatuses = array_merge(MaterialRequest::PENDING_WORKFLOW_STATUSES, [MaterialRequest::STATUS_PENDING_SUPPLY]);
        if (! in_array($materialRequest->status, $approvableStatuses, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Seule une demande soumise ou en attente de validation peut être validée.',
            ], 422);
        }

        $approverId = $this->resolveActorId($request, 'approved_by');
        $approver = $approverId === null ? null : User::find($approverId);

        if (! $approver?->canManageOperations()) {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée : Seul un responsable, délégataire actif ou administrateur peut valider les demandes.',
            ], 403);
        }

        $validated = $request->validate([
            'comment' => ['nullable', 'string', 'max:1000'],
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['integer', 'min:0'],
        ]);

        $customQuantities = $validated['quantities'] ?? [];

        $insufficientItem = $materialRequest->items->first(function (RequestItem $item) use ($customQuantities): bool {
            $qtyToApprove = array_key_exists((string) $item->id, $customQuantities)
                ? (int) $customQuantities[(string) $item->id]
                : $item->quantity_requested;

            return $item->article === null || $item->article->quantity < $qtyToApprove;
        });

        if ($insufficientItem !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Stock insuffisant pour approuver tous les articles de cette demande.',
            ], 422);
        }

        $previousStatus = $materialRequest->status;

        DB::transaction(function () use ($materialRequest, $approverId, $customQuantities): void {
            foreach ($materialRequest->items as $item) {
                $article = $item->article ? Article::query()->lockForUpdate()->find($item->article->id) : null;
                $qty = array_key_exists((string) $item->id, $customQuantities)
                    ? (int) $customQuantities[(string) $item->id]
                    : $item->quantity_requested;

                $item->update([
                    'quantity_approved' => $qty,
                ]);

                if ($article && $qty > 0) {
                    $newQty = $article->quantity - $qty;
                    $article->update(Article::stockState($newQty, $article->threshold));

                    StockMovement::create([
                        'article_id' => $article->id,
                        'user_id' => $approverId,
                        'material_request_id' => $materialRequest->id,
                        'type' => 'Sortie',
                        'source' => StockMovement::SOURCE_REQUEST_APPROVAL,
                        'quantity' => $qty,
                        'from_location' => $article->location,
                        'to_service' => $materialRequest->service,
                        'reason' => "Attribution demande {$materialRequest->reference} - {$materialRequest->user?->name}",
                    ]);

                    if ($newQty <= $article->threshold) {
                        $this->notifyOperationalManagers(
                            'stock.low',
                            'Alerte de stock faible',
                            "L'article {$article->code} ({$article->name}) a atteint le seuil d'alerte ({$newQty} restant(s)).",
                            '#stock-articles',
                            'notify_on_low_stock',
                        );
                    }
                }
            }

            $materialRequest->update([
                'status' => MaterialRequest::STATUS_VALIDATED,
                'approved_by' => $approverId,
                'approved_at' => now(),
            ]);

            $bonPath = "documents/bons/Bon_de_Decharge_{$materialRequest->reference}.html";
            $bonHtml = $this->buildDischargeDocumentHtml($materialRequest->fresh(['user', 'items.article', 'approver']));
            Storage::disk('local')->put($bonPath, $bonHtml);

            AdministrativeDocument::create([
                'material_request_id' => $materialRequest->id,
                'title' => "Bon_de_Decharge_{$materialRequest->reference}.pdf",
                'category' => 'Formulaires',
                'type' => 'PDF',
                'service' => $materialRequest->service,
                'file_path' => $bonPath,
                'original_name' => "Bon_de_Decharge_{$materialRequest->reference}.html",
                'mime_type' => 'text/html',
                'file_size_bytes' => strlen($bonHtml),
                'file_size' => max(1, (int) round(strlen($bonHtml) / 1024)).' Ko',
                'reference' => 'BON-'.$materialRequest->reference,
                'user_id' => $materialRequest->user_id,
            ]);
        });

        $this->recordDecision($materialRequest, $request->user(), 'approved', $previousStatus, MaterialRequest::STATUS_VALIDATED, $validated['comment'] ?? null);
        $this->recordActivity($request, 'request.approved', $materialRequest, "Demande {$materialRequest->reference} validée.");
        $this->sendSystemNotification(
            $materialRequest->user_id,
            'request.approved',
            'Demande validée',
            "Votre demande {$materialRequest->reference} a été validée.",
            "#request-detail/{$materialRequest->reference}",
            'notify_on_request_decision',
        );

        $materialRequest->load(['items.article', 'approver', 'documents', 'decisions.user']);

        return response()->json([
            'success' => true,
            'message' => "Demande {$materialRequest->reference} validée ! Le Bon de Décharge officiel a été généré.",
            'data' => $materialRequest,
        ]);
    }

    /**
     * Rejet de la demande avec motif obligatoire (RM-05)
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $materialRequest = MaterialRequest::where('id', $id)->orWhere('reference', $id)->firstOrFail();

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        if (! in_array($materialRequest->status, MaterialRequest::PENDING_WORKFLOW_STATUSES, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Seule une demande en attente peut être rejetée.',
            ], 422);
        }

        $previousStatus = $materialRequest->status;
        $materialRequest->update([
            'status' => MaterialRequest::STATUS_REJECTED,
            'rejection_reason' => $validated['reason'],
            'closed_at' => now(),
        ]);

        $this->recordDecision($materialRequest, $request->user(), 'rejected', $previousStatus, MaterialRequest::STATUS_REJECTED, $validated['reason']);
        $this->recordActivity($request, 'request.rejected', $materialRequest, "Demande {$materialRequest->reference} rejetée.", ['reason' => $validated['reason']]);
        $this->sendSystemNotification(
            $materialRequest->user_id,
            'request.rejected',
            'Demande rejetée',
            "Votre demande {$materialRequest->reference} a été rejetée : {$validated['reason']}",
            "#request-detail/{$materialRequest->reference}",
            'notify_on_request_decision',
        );

        return response()->json([
            'success' => true,
            'message' => 'Demande rejetée. Le demandeur a été notifié du motif.',
            'data' => $materialRequest->fresh(['decisions.user']),
        ]);
    }

    /**
     * Restitution du matériel prêté (réintégration en stock)
     */
    public function markReturned(Request $request, string $id): JsonResponse
    {
        $materialRequest = MaterialRequest::with(['items.article'])
            ->where('id', $id)
            ->orWhere('reference', $id)
            ->firstOrFail();

        if (! in_array($materialRequest->status, MaterialRequest::AUTHORIZED_STATUSES, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Seule une demande validée ou exécutée peut faire l’objet d’une restitution.',
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

        DB::transaction(function () use ($actorId, $materialRequest): void {
            foreach ($materialRequest->items as $item) {
                if ($item->article && $item->quantity_approved > 0) {
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
                        'to_location' => $item->article->location,
                        'from_service' => $materialRequest->service,
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
            'data' => $materialRequest->fresh(['items.article', 'decisions.user']),
        ]);
    }

    /**
     * Soumission d'un brouillon ou d'une demande corrigée (RM-04)
     */
    public function submit(Request $request, string $id): JsonResponse
    {
        $materialRequest = MaterialRequest::query()->where('id', $id)->orWhere('reference', $id)->firstOrFail();

        if ($materialRequest->user_id !== $request->user()->id || ! in_array($materialRequest->status, [MaterialRequest::STATUS_DRAFT, MaterialRequest::STATUS_CORRECTION], true)) {
            return response()->json(['success' => false, 'message' => 'Cette demande ne peut pas être soumise.'], 422);
        }

        $previousStatus = $materialRequest->status;
        $materialRequest->update([
            'status' => MaterialRequest::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'correction_reason' => null,
        ]);

        $this->recordDecision($materialRequest, $request->user(), 'submitted', $previousStatus, MaterialRequest::STATUS_SUBMITTED, $request->string('comment')->toString() ?: null);
        $this->recordActivity($request, 'request.submitted', $materialRequest, "Demande {$materialRequest->reference} soumise.");
        $this->notifyOperationalManagers(
            'request.submitted',
            'Demande à valider',
            "La demande {$materialRequest->reference} a été soumise.",
            "#request-detail/{$materialRequest->reference}",
            'notify_on_request_submitted',
        );

        return response()->json([
            'success' => true,
            'message' => 'Demande soumise pour traitement.',
            'data' => $materialRequest->fresh(['decisions.user']),
        ]);
    }

    /**
     * Annulation d'une demande avec motif obligatoire
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $materialRequest = MaterialRequest::query()->where('id', $id)->orWhere('reference', $id)->firstOrFail();

        $cancellable = array_merge([MaterialRequest::STATUS_DRAFT, MaterialRequest::STATUS_CORRECTION], MaterialRequest::PENDING_WORKFLOW_STATUSES);
        $canCancel = ($materialRequest->user_id === $request->user()->id || $request->user()->isAdmin())
            && in_array($materialRequest->status, $cancellable, true);

        if (! $canCancel) {
            return response()->json(['success' => false, 'message' => 'Cette demande ne peut plus être annulée.'], 422);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $previousStatus = $materialRequest->status;
        $materialRequest->update([
            'status' => MaterialRequest::STATUS_CANCELLED,
            'closed_at' => now(),
            'cancellation_reason' => $validated['reason'],
        ]);

        $this->recordDecision($materialRequest, $request->user(), 'cancelled', $previousStatus, MaterialRequest::STATUS_CANCELLED, $validated['reason']);
        $this->recordActivity($request, 'request.cancelled', $materialRequest, "Demande {$materialRequest->reference} annulée.", ['reason' => $validated['reason']]);

        return response()->json([
            'success' => true,
            'message' => 'La demande a été annulée.',
            'data' => $materialRequest->fresh(['decisions.user']),
        ]);
    }

    /**
     * Demande de complément d'information
     */
    public function requestCorrection(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $materialRequest = MaterialRequest::query()->where('id', $id)->orWhere('reference', $id)->firstOrFail();

        if (! in_array($materialRequest->status, MaterialRequest::PENDING_WORKFLOW_STATUSES, true)) {
            return response()->json(['success' => false, 'message' => 'Seule une demande en cours d’instruction peut faire l’objet d’une demande de complément.'], 422);
        }

        $previousStatus = $materialRequest->status;
        $materialRequest->update(['status' => MaterialRequest::STATUS_CORRECTION, 'correction_reason' => $validated['reason']]);
        $this->recordDecision($materialRequest, $request->user(), 'correction_requested', $previousStatus, MaterialRequest::STATUS_CORRECTION, $validated['reason']);
        $this->recordActivity($request, 'request.correction_requested', $materialRequest, "Complément demandé pour {$materialRequest->reference}.", ['reason' => $validated['reason']]);
        $this->sendSystemNotification(
            $materialRequest->user_id,
            'request.correction_requested',
            'Complément demandé',
            "Un complément est requis pour {$materialRequest->reference} : {$validated['reason']}",
            "#request-detail/{$materialRequest->reference}",
            'notify_on_request_decision',
        );

        return response()->json([
            'success' => true,
            'message' => 'Demande de complément transmise au demandeur.',
            'data' => $materialRequest->fresh(['decisions.user']),
        ]);
    }

    /**
     * Suivi des étapes d'exécution (En cours d'exécution, En attente d'approvisionnement, Exécutée, En attente de justification)
     */
    public function updateExecutionStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    MaterialRequest::STATUS_EXECUTING,
                    MaterialRequest::STATUS_PENDING_SUPPLY,
                    MaterialRequest::STATUS_EXECUTED,
                    MaterialRequest::STATUS_PENDING_JUSTIFICATION,
                    MaterialRequest::STATUS_DELIVERED,
                ]),
            ],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $materialRequest = MaterialRequest::query()->where('id', $id)->orWhere('reference', $id)->firstOrFail();

        if (in_array($materialRequest->status, MaterialRequest::TERMINAL_STATUSES, true)) {
            return response()->json(['success' => false, 'message' => 'Cette demande est clôturée ou annulée.'], 422);
        }

        $previousStatus = $materialRequest->status;
        $updates = ['status' => $validated['status']];
        if (in_array($validated['status'], [MaterialRequest::STATUS_EXECUTED, MaterialRequest::STATUS_DELIVERED], true)) {
            $updates['executed_at'] = $materialRequest->executed_at ?? now();
            $updates['delivered_at'] = $materialRequest->delivered_at ?? now();
        }

        $materialRequest->update($updates);
        $this->recordDecision($materialRequest, $request->user(), 'execution_step', $previousStatus, $validated['status'], $validated['comment'] ?? null);
        $this->recordActivity($request, 'request.execution_updated', $materialRequest, "Étape d'exécution de {$materialRequest->reference} -> {$validated['status']}.");
        $this->sendSystemNotification(
            $materialRequest->user_id,
            'request.execution_updated',
            'Mise à jour de votre demande',
            "Votre demande {$materialRequest->reference} est désormais au statut « {$validated['status']} ».",
            "#request-detail/{$materialRequest->reference}",
            'notify_on_request_decision',
        );

        return response()->json([
            'success' => true,
            'message' => "Statut d'exécution enregistré : {$validated['status']}.",
            'data' => $materialRequest->fresh(['assignee', 'decisions.user']),
        ]);
    }

    public function markDelivered(Request $request, string $id): JsonResponse
    {
        $materialRequest = MaterialRequest::query()->where('id', $id)->orWhere('reference', $id)->firstOrFail();

        if (! in_array($materialRequest->status, [MaterialRequest::STATUS_VALIDATED, MaterialRequest::STATUS_APPROVED, MaterialRequest::STATUS_EXECUTING, 'En cours d’exécution'], true)) {
            return response()->json(['success' => false, 'message' => 'Seule une demande validée ou en cours d’exécution peut être marquée exécutée/livrée.'], 422);
        }

        $previousStatus = $materialRequest->status;
        $materialRequest->update([
            'status' => MaterialRequest::STATUS_EXECUTED,
            'executed_at' => now(),
            'delivered_at' => now(),
        ]);
        $this->recordDecision($materialRequest, $request->user(), 'executed', $previousStatus, MaterialRequest::STATUS_EXECUTED, $request->string('comment')->toString() ?: 'Prestation réalisée / matériel remis.');
        $this->recordActivity($request, 'request.delivered', $materialRequest, "Demande {$materialRequest->reference} exécutée / livrée.");
        $this->sendSystemNotification(
            $materialRequest->user_id,
            'request.executed',
            'Demande exécutée — Confirmez la réception',
            "Votre demande {$materialRequest->reference} a été exécutée. Merci de confirmer la réception.",
            "#request-detail/{$materialRequest->reference}",
            'notify_on_request_decision',
        );

        return response()->json(['success' => true, 'message' => 'Exécution / remise enregistrée.', 'data' => $materialRequest->fresh(['decisions.user'])]);
    }

    public function close(Request $request, string $id): JsonResponse
    {
        $materialRequest = MaterialRequest::query()->where('id', $id)->orWhere('reference', $id)->firstOrFail();

        if (! in_array($materialRequest->status, [
            MaterialRequest::STATUS_EXECUTED,
            MaterialRequest::STATUS_DELIVERED,
            MaterialRequest::STATUS_RETURNED,
            MaterialRequest::STATUS_VALIDATED,
            MaterialRequest::STATUS_APPROVED,
        ], true)) {
            return response()->json(['success' => false, 'message' => 'La demande doit être exécutée, livrée ou restituée avant sa clôture.'], 422);
        }

        $previousStatus = $materialRequest->status;
        $materialRequest->update(['status' => MaterialRequest::STATUS_CLOSED, 'closed_at' => now()]);
        $this->recordDecision($materialRequest, $request->user(), 'closed', $previousStatus, MaterialRequest::STATUS_CLOSED, $request->string('comment')->toString() ?: 'Clôture après vérification.');
        $this->recordActivity($request, 'request.closed', $materialRequest, "Demande {$materialRequest->reference} clôturée.");

        return response()->json(['success' => true, 'message' => 'Demande clôturée avec succès.', 'data' => $materialRequest->fresh(['decisions.user'])]);
    }

    public function assign(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);
        $materialRequest = MaterialRequest::query()->where('id', $id)->orWhere('reference', $id)->firstOrFail();
        $assignableStatuses = array_merge(MaterialRequest::PENDING_WORKFLOW_STATUSES, MaterialRequest::AUTHORIZED_STATUSES);

        if (! in_array($materialRequest->status, $assignableStatuses, true)) {
            return response()->json(['success' => false, 'message' => 'Cette demande ne peut pas être affectée à ce stade.'], 422);
        }

        $previousStatus = $materialRequest->status;
        $agent = User::findOrFail($validated['assigned_to']);
        $materialRequest->update([
            'assigned_to' => $agent->id,
            'status' => MaterialRequest::STATUS_EXECUTING,
        ]);

        $this->recordDecision($materialRequest, $request->user(), 'assigned', $previousStatus, MaterialRequest::STATUS_EXECUTING, $validated['comment'] ?? "Affectée à {$agent->name}.");
        $this->recordActivity($request, 'request.assigned', $materialRequest, "Demande {$materialRequest->reference} affectée à {$agent->name}.", $validated);
        $this->sendSystemNotification(
            $agent->id,
            'request.assigned',
            'Demande affectée pour exécution',
            "La demande {$materialRequest->reference} vous a été affectée pour exécution.",
            "#request-detail/{$materialRequest->reference}",
        );

        return response()->json([
            'success' => true,
            'message' => "Demande affectée à {$agent->name} (En cours d'exécution).",
            'data' => $materialRequest->fresh(['assignee', 'decisions.user']),
        ]);
    }

    /**
     * Confirmation de réception par le demandeur (ou gestionnaire)
     */
    public function confirmReceipt(Request $request, string $id): JsonResponse
    {
        $materialRequest = MaterialRequest::query()->where('id', $id)->orWhere('reference', $id)->firstOrFail();
        $actor = $request->user();

        if ($materialRequest->user_id !== $actor?->id && ! $actor?->canManageOperations()) {
            return response()->json(['success' => false, 'message' => 'Seul le demandeur ou un responsable peut confirmer la réception.'], 403);
        }

        if (! in_array($materialRequest->status, MaterialRequest::AUTHORIZED_STATUSES, true)) {
            return response()->json(['success' => false, 'message' => 'La réception ne peut être confirmée qu’après validation ou exécution.'], 422);
        }

        $previousStatus = $materialRequest->status;
        $nextStatus = $actor?->canManageOperations() && $request->boolean('close_immediately')
            ? MaterialRequest::STATUS_CLOSED
            : MaterialRequest::STATUS_EXECUTED;

        $materialRequest->update([
            'received_at' => now(),
            'executed_at' => $materialRequest->executed_at ?? now(),
            'status' => $nextStatus,
            'closed_at' => $nextStatus === MaterialRequest::STATUS_CLOSED ? now() : $materialRequest->closed_at,
        ]);

        $comment = $request->string('comment')->toString() ?: "Réception confirmée par {$actor?->name}.";
        $this->recordDecision($materialRequest, $actor, 'receipt_confirmed', $previousStatus, $nextStatus, $comment);
        $this->recordActivity($request, 'request.received', $materialRequest, "Réception confirmée pour {$materialRequest->reference} par {$actor?->name}.");
        $this->notifyOperationalManagers(
            'request.received',
            'Réception confirmée',
            "La réception de la demande {$materialRequest->reference} a été confirmée par {$actor?->name}.",
            "#request-detail/{$materialRequest->reference}",
        );

        return response()->json([
            'success' => true,
            'message' => 'Réception confirmée avec succès.',
            'data' => $materialRequest->fresh(['decisions.user']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = MaterialRequest::query()->with(['user', 'assignee']);
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('service')) {
            $query->where('service', $request->service);
        }
        $requests = $query->latest()->get();

        return response()->streamDownload(function () use ($requests): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Référence', 'Demandeur', 'Service', 'Catégorie', 'Motif', 'Urgence', 'Agent affecté', 'Statut', 'Échéance', 'En retard', 'Créée le'], ';');

            foreach ($requests as $materialRequest) {
                fputcsv($output, [
                    $materialRequest->reference,
                    $materialRequest->user?->name,
                    $materialRequest->service,
                    $materialRequest->category,
                    $materialRequest->motif,
                    $materialRequest->urgency,
                    $materialRequest->assignee?->name ?? '-',
                    $materialRequest->status,
                    $materialRequest->date_needed?->format('d/m/Y'),
                    $materialRequest->is_overdue ? 'Oui' : 'Non',
                    $materialRequest->created_at?->format('d/m/Y H:i'),
                ], ';');
            }

            fclose($output);
        }, 'demandes-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function storeRequestAttachment(MaterialRequest $materialRequest, UploadedFile $file, int $userId): AdministrativeDocument
    {
        $path = $file->store("documents/demandes/{$materialRequest->reference}", 'local');
        $extension = strtolower($file->getClientOriginalExtension());
        $type = match ($extension) {
            'doc', 'docx' => 'Word',
            'xls', 'xlsx' => 'Excel',
            default => 'PDF',
        };

        $doc = AdministrativeDocument::create([
            'material_request_id' => $materialRequest->id,
            'title' => $file->getClientOriginalName(),
            'category' => 'Justificatifs de demande',
            'type' => $type,
            'service' => $materialRequest->service,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size_bytes' => $file->getSize(),
            'file_size' => number_format($file->getSize() / 1024, 0, ',', ' ').' Ko',
            'reference' => null,
            'user_id' => $userId,
        ]);

        $doc->update([
            'reference' => 'PJ-'.now()->format('Y').'-'.str_pad((string) $doc->id, 5, '0', STR_PAD_LEFT),
        ]);

        return $doc;
    }

    private function buildDischargeDocumentHtml(MaterialRequest $materialRequest): string
    {
        $rows = '';
        foreach ($materialRequest->items as $item) {
            $code = e($item->article?->code ?? '-');
            $name = e($item->article?->name ?? '-');
            $reqQty = (int) $item->quantity_requested;
            $appQty = (int) $item->quantity_approved;
            $rows .= "<tr><td>{$code}</td><td>{$name}</td><td>{$reqQty}</td><td><strong>{$appQty}</strong></td></tr>";
        }
        if ($rows === '') {
            $desc = e($materialRequest->service_description ?: $materialRequest->motif);
            $rows = "<tr><td colspan=\"4\">{$desc}</td></tr>";
        }

        $ref = e($materialRequest->reference);
        $requester = e($materialRequest->user?->name ?? '-');
        $service = e($materialRequest->service);
        $category = e($materialRequest->category);
        $motif = e($materialRequest->motif);
        $approver = e($materialRequest->approver?->name ?? '-');
        $date = now()->format('d/m/Y H:i');

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Bon de Décharge {$ref}</title>
<style>body{font-family:Arial,sans-serif;padding:32px;color:#1e293b}table{width:100%;border-collapse:collapse;margin-top:20px}th,td{border:1px solid #cbd5e1;padding:8px 12px;text-align:left}th{background:#f8fafc}</style>
</head>
<body>
<h2>GESTION+ — BON DE VALIDATION & DÉCHARGE</h2>
<p><strong>Référence :</strong> {$ref} | <strong>Date :</strong> {$date}</p>
<p><strong>Demandeur :</strong> {$requester} ({$service}) | <strong>Catégorie :</strong> {$category}</p>
<p><strong>Motif :</strong> {$motif} | <strong>Validé par :</strong> {$approver}</p>
<table><thead><tr><th>Code</th><th>Désignation</th><th>Qté demandée</th><th>Qté validée</th></tr></thead><tbody>{$rows}</tbody></table>
</body>
</html>
HTML;
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
