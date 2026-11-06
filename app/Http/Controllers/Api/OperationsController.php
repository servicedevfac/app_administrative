<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalDelegation;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetIncident;
use App\Models\Department;
use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Models\ExpenseAudit;
use App\Models\InventoryItem;
use App\Models\InventorySession;
use App\Models\MaintenanceRecord;
use App\Models\MeasurementUnit;
use App\Models\RequestCategory;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\SystemBackup;
use App\Models\SystemNotification;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsController extends Controller
{
    public function overview(): JsonResponse
    {
        $vehicles = Vehicle::all();
        $expenses = Expense::all();

        return response()->json(['success' => true, 'data' => [
            'assets' => [
                'total' => Asset::count(),
                'available' => Asset::where('availability', 'Disponible')->count(),
                'assigned' => Asset::where('availability', 'Affecté')->count(),
                'unavailable' => Asset::whereIn('condition', ['Endommagé', 'Hors service'])->count(),
                'open_incidents' => Schema::hasTable('asset_incidents') ? AssetIncident::where('status', '!=', 'Résolu')->count() : 0,
                'total_value' => (float) Asset::sum('acquisition_cost'),
            ],
            'vehicles' => [
                'total' => $vehicles->count(),
                'available' => $vehicles->where('status', 'Disponible')->count(),
                'in_service' => $vehicles->where('status', 'En service')->count(),
                'unavailable' => $vehicles->whereIn('status', ['Immobilisé', 'Maintenance'])->count(),
                'alerts' => $vehicles->filter(fn (Vehicle $v): bool => $v->has_alert)->count(),
                'maintenance_cost' => (float) MaintenanceRecord::where('maintainable_type', Vehicle::class)->sum('cost'),
            ],
            'expenses' => [
                'estimated' => (float) $expenses->sum('estimated_amount'),
                'authorized' => (float) $expenses->sum('authorized_amount'),
                'committed' => (float) $expenses->sum('committed_amount'),
                'disbursed' => (float) $expenses->sum('disbursed_amount'),
                'justified' => (float) $expenses->sum('justified_amount'),
                'remaining' => (float) $expenses->sum(fn (Expense $e): float => $e->remaining_amount),
                'unjustified' => (float) $expenses->sum(fn (Expense $e): float => $e->amount_to_justify),
                'reapproval' => $expenses->filter(fn (Expense $e): bool => $e->requires_reapproval)->count(),
            ],
            'inventory' => [
                'open' => InventorySession::whereNotIn('status', ['Validé', 'Annulé'])->count(),
                'variances' => InventoryItem::where('variance', '!=', 0)->count(),
            ],
        ]]);
    }

    public function assignees(): JsonResponse
    {
        return $this->collectionResponse(
            User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'service', 'role', 'matricule']),
        );
    }

    /* =========================================================================
       MODULE 4 : PATRIMOINE & ÉQUIPEMENTS
       ========================================================================= */

    public function assets(Request $request): JsonResponse
    {
        $query = Asset::with(['assignedUser', 'assignments.user', 'incidents.reporter', 'maintenanceRecords']);

        if ($request->filled('condition')) {
            $query->where('condition', $request->condition);
        }
        if ($request->filled('availability')) {
            $query->where('availability', $request->availability);
        }
        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($sub) use ($search): void {
                $sub->where('inventory_number', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        return $this->collectionResponse($query->latest()->get());
    }

    public function storeAsset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255', 'unique:assets,serial_number'],
            'acquired_at' => ['nullable', 'date'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'condition' => ['nullable', Rule::in(['Neuf', 'Bon état', 'Endommagé', 'Hors service'])],
            'notes' => ['nullable', 'string'],
        ]);

        $asset = DB::transaction(function () use ($request, $validated): Asset {
            $nextNumber = (int) Asset::query()->lockForUpdate()->max('id') + 1;
            $hasInitialHolder = ! empty($validated['assigned_user_id']);

            $asset = Asset::create(array_merge($validated, [
                'inventory_number' => 'PAT-'.now()->format('Y').'-'.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT),
                'condition' => $validated['condition'] ?? 'Neuf',
                'availability' => $hasInitialHolder ? 'Affecté' : 'Disponible',
            ]));

            if ($hasInitialHolder || ! empty($validated['department'])) {
                AssetAssignment::create([
                    'asset_id' => $asset->id,
                    'user_id' => $validated['assigned_user_id'] ?? null,
                    'department' => $validated['department'] ?? null,
                    'location' => $validated['location'] ?? null,
                    'assigned_at' => now(),
                    'notes' => 'Affectation initiale lors de l’enregistrement.',
                    'recorded_by' => $request->user()?->id,
                ]);
            }

            return $asset;
        }, attempts: 5);

        return $this->created($request, $asset->load(['assignedUser', 'assignments.user']), 'asset.created', "Équipement {$asset->inventory_number} enregistré dans le patrimoine.");
    }

    public function updateAsset(Request $request, Asset $asset): JsonResponse
    {
        $before = $asset->only(['name', 'category', 'serial_number', 'acquired_at', 'acquisition_cost', 'location', 'department', 'condition', 'availability', 'notes']);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'required', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255', Rule::unique('assets', 'serial_number')->ignore($asset->id)],
            'acquired_at' => ['nullable', 'date'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'condition' => ['sometimes', Rule::in(['Neuf', 'Bon état', 'Endommagé', 'Hors service'])],
            'notes' => ['nullable', 'string'],
        ]);

        $asset->update($validated);
        $this->recordActivity($request, 'asset.updated', $asset, "Fiche équipement {$asset->inventory_number} mise à jour.", [
            'before' => $before,
            'after' => $asset->only(array_keys($before)),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Équipement mis à jour.',
            'data' => $asset->fresh(['assignedUser', 'assignments.user', 'incidents.reporter', 'maintenanceRecords']),
        ]);
    }

    public function assignAsset(Request $request, Asset $asset): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'department' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'condition' => ['nullable', Rule::in(['Neuf', 'Bon état', 'Endommagé', 'Hors service'])],
            'notes' => ['nullable', 'string'],
        ]);

        if (empty($validated['user_id']) && empty($validated['department'])) {
            return response()->json(['success' => false, 'message' => 'Un utilisateur ou un service doit être indiqué.'], 422);
        }

        if ($asset->condition === 'Hors service') {
            return response()->json(['success' => false, 'message' => 'Cet équipement est hors service et ne peut pas être remis.'], 422);
        }

        DB::transaction(function () use ($asset, $request, $validated): void {
            $asset->assignments()->whereNull('returned_at')->update(['returned_at' => now()]);
            AssetAssignment::create([
                'asset_id' => $asset->id,
                'user_id' => $validated['user_id'] ?? null,
                'department' => $validated['department'] ?? $asset->department,
                'location' => $validated['location'] ?? $asset->location,
                'assigned_at' => now(),
                'notes' => $validated['notes'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);
            $asset->update([
                'assigned_user_id' => $validated['user_id'] ?? null,
                'department' => $validated['department'] ?? $asset->department,
                'location' => $validated['location'] ?? $asset->location,
                'condition' => $validated['condition'] ?? $asset->condition,
                'availability' => 'Affecté',
            ]);
        });

        if (! empty($validated['user_id'])) {
            $this->sendSystemNotification(
                (int) $validated['user_id'],
                'asset.assigned',
                'Équipement remis',
                "L'équipement {$asset->inventory_number} ({$asset->name}) vous a été affecté.",
                '#dashboard',
            );
        }

        $this->recordActivity($request, 'asset.assigned', $asset, "Remise/affectation de l'équipement {$asset->inventory_number}.", $validated);

        return response()->json([
            'success' => true,
            'message' => 'Remise / affectation enregistrée et historisée.',
            'data' => $asset->fresh(['assignedUser', 'assignments.user', 'incidents.reporter', 'maintenanceRecords']),
        ]);
    }

    public function returnAsset(Request $request, Asset $asset): JsonResponse
    {
        $validated = $request->validate([
            'condition' => ['nullable', Rule::in(['Neuf', 'Bon état', 'Endommagé', 'Hors service'])],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($asset, $validated): void {
            $openAssignment = $asset->assignments()->whereNull('returned_at')->latest('id')->first();
            if ($openAssignment) {
                $returnNote = $validated['notes'] ?? null;
                $openAssignment->update([
                    'returned_at' => now(),
                    'notes' => trim(($openAssignment->notes ? $openAssignment->notes.' | ' : '').($returnNote ? "Retour : {$returnNote}" : 'Restitué')),
                ]);
            }
            $asset->update([
                'assigned_user_id' => null,
                'availability' => 'Disponible',
                'condition' => $validated['condition'] ?? $asset->condition,
                'location' => $validated['location'] ?? $asset->location,
            ]);
        });

        $this->recordActivity($request, 'asset.returned', $asset, "Restitution de l'équipement {$asset->inventory_number}.", $validated);

        return response()->json([
            'success' => true,
            'message' => 'Restitution de matériel enregistrée.',
            'data' => $asset->fresh(['assignedUser', 'assignments.user', 'incidents.reporter', 'maintenanceRecords']),
        ]);
    }

    public function storeAssetIncident(Request $request, Asset $asset): JsonResponse
    {
        $validated = $request->validate([
            'severity' => ['required', Rule::in(['Mineur', 'Majeur', 'Critique'])],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'incident_date' => ['nullable', 'date'],
        ]);

        $incident = DB::transaction(function () use ($request, $asset, $validated): AssetIncident {
            $nextNumber = (int) AssetIncident::query()->lockForUpdate()->max('id') + 1;
            $incident = AssetIncident::create([
                'reference' => 'INC-'.now()->format('Y').'-'.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT),
                'asset_id' => $asset->id,
                'reported_by' => $request->user()->id,
                'severity' => $validated['severity'],
                'title' => $validated['title'],
                'description' => $validated['description'],
                'status' => 'Ouvert',
                'incident_date' => $validated['incident_date'] ?? now()->toDateString(),
            ]);

            if ($validated['severity'] === 'Critique') {
                $asset->update(['condition' => 'Hors service']);
            } elseif ($validated['severity'] === 'Majeur') {
                $asset->update(['condition' => 'Endommagé']);
            }

            return $incident;
        }, attempts: 5);

        $this->recordActivity($request, 'asset.incident_reported', $asset, "Incident {$incident->reference} signalé sur {$asset->inventory_number}.", $validated);

        return response()->json([
            'success' => true,
            'message' => "Incident {$incident->reference} enregistré.",
            'data' => $incident->load('reporter'),
        ], 201);
    }

    public function resolveAssetIncident(Request $request, AssetIncident $incident): JsonResponse
    {
        $validated = $request->validate([
            'resolution_notes' => ['required', 'string'],
            'asset_condition' => ['nullable', Rule::in(['Neuf', 'Bon état', 'Endommagé', 'Hors service'])],
        ]);

        $incident->update([
            'status' => 'Résolu',
            'resolved_at' => now(),
            'resolution_notes' => $validated['resolution_notes'],
        ]);

        if (! empty($validated['asset_condition']) && $incident->asset) {
            $incident->asset->update(['condition' => $validated['asset_condition']]);
        }

        $this->recordActivity($request, 'asset.incident_resolved', $incident, "Incident {$incident->reference} résolu.");

        return response()->json([
            'success' => true,
            'message' => 'Incident marqué comme résolu.',
            'data' => $incident->fresh(['asset', 'reporter']),
        ]);
    }

    public function exportAssets(): StreamedResponse
    {
        $assets = Asset::with('assignedUser')->orderBy('inventory_number')->get();

        return response()->streamDownload(function () use ($assets): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['N° Inventaire', 'Désignation', 'Catégorie', 'N° Série', 'État', 'Disponibilité', 'Service', 'Emplacement', 'Détenteur', 'Date Acquisition', 'Coût Acquisition'], ';');
            foreach ($assets as $asset) {
                fputcsv($output, [
                    $asset->inventory_number,
                    $asset->name,
                    $asset->category,
                    $asset->serial_number ?? '-',
                    $asset->condition,
                    $asset->availability,
                    $asset->department ?? '-',
                    $asset->location ?? '-',
                    $asset->assignedUser?->name ?? '-',
                    $asset->acquired_at?->format('d/m/Y') ?? '-',
                    $asset->acquisition_cost ?? 0,
                ], ';');
            }
            fclose($output);
        }, 'etat-patrimoine-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* =========================================================================
       MODULE 5 : PARC AUTOMOBILE & MAINTENANCE
       ========================================================================= */

    public function vehicles(Request $request): JsonResponse
    {
        $query = Vehicle::with(['driver', 'documents', 'maintenanceRecords']);
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        $vehicles = $query->latest()->get();

        return response()->json([
            'success' => true,
            'count' => $vehicles->count(),
            'kpis' => [
                'total' => $vehicles->count(),
                'available' => $vehicles->where('status', 'Disponible')->count(),
                'in_service' => $vehicles->where('status', 'En service')->count(),
                'maintenance' => $vehicles->whereIn('status', ['Immobilisé', 'Maintenance'])->count(),
                'alerts' => $vehicles->filter(fn (Vehicle $v): bool => $v->has_alert)->count(),
                'total_cost' => (float) MaintenanceRecord::where('maintainable_type', Vehicle::class)->sum('cost'),
            ],
            'data' => $vehicles,
        ]);
    }

    public function storeVehicle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'registration_number' => ['required', 'string', 'max:50', 'unique:vehicles'],
            'brand' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:100'],
            'registration_card_number' => ['nullable', 'string', 'max:100'],
            'mileage' => ['nullable', 'integer', 'min:0'],
            'department' => ['nullable', 'string', 'max:255'],
            'driver_id' => ['nullable', 'exists:users,id'],
            'status' => ['nullable', Rule::in(['Disponible', 'En service', 'Immobilisé', 'Maintenance'])],
            'insurance_expires_at' => ['nullable', 'date'],
            'inspection_expires_at' => ['nullable', 'date'],
            'tax_expires_at' => ['nullable', 'date'],
            'next_maintenance_at' => ['nullable', 'date'],
            'next_maintenance_mileage' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);
        $vehicle = Vehicle::create($validated);

        if ($vehicle->has_alert) {
            $this->notifyOperationalManagers(
                'vehicle.alert',
                'Alerte Parc Automobile',
                "Véhicule {$vehicle->registration_number} : ".implode(', ', $vehicle->alerts_summary),
                '#operations',
                'notify_on_vehicle_alert',
            );
        }

        return $this->created($request, $vehicle->load(['driver', 'documents', 'maintenanceRecords']), 'vehicle.created', 'Véhicule enregistré au parc automobile.');
    }

    public function updateVehicle(Request $request, Vehicle $vehicle): JsonResponse
    {
        $before = $vehicle->only(['registration_number', 'mileage', 'department', 'driver_id', 'status', 'insurance_expires_at', 'inspection_expires_at', 'tax_expires_at', 'next_maintenance_at', 'next_maintenance_mileage']);

        $validated = $request->validate([
            'registration_number' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('vehicles')->ignore($vehicle->id)],
            'brand' => ['sometimes', 'required', 'string', 'max:100'],
            'model' => ['sometimes', 'required', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:100'],
            'registration_card_number' => ['nullable', 'string', 'max:100'],
            'mileage' => ['sometimes', 'integer', 'min:0'],
            'department' => ['nullable', 'string', 'max:255'],
            'driver_id' => ['nullable', 'exists:users,id'],
            'status' => ['sometimes', Rule::in(['Disponible', 'En service', 'Immobilisé', 'Maintenance'])],
            'insurance_expires_at' => ['nullable', 'date'],
            'inspection_expires_at' => ['nullable', 'date'],
            'tax_expires_at' => ['nullable', 'date'],
            'next_maintenance_at' => ['nullable', 'date'],
            'next_maintenance_mileage' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $vehicle->update($validated);
        $vehicle->refresh();

        if ($vehicle->has_alert) {
            $this->notifyOperationalManagers(
                'vehicle.alert',
                'Alerte d’entretien / échéance véhicule',
                "Véhicule {$vehicle->registration_number} : ".implode(', ', $vehicle->alerts_summary),
                '#operations',
                'notify_on_vehicle_alert',
            );
        }

        $this->recordActivity($request, 'vehicle.updated', $vehicle, "Véhicule {$vehicle->registration_number} mis à jour (km : {$vehicle->mileage}).", [
            'before' => $before,
            'after' => $vehicle->only(array_keys($before)),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Véhicule mis à jour avec succès.',
            'data' => $vehicle->load(['driver', 'documents', 'maintenanceRecords']),
        ]);
    }

    public function storeVehicleDocument(Request $request, Vehicle $vehicle): JsonResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', 'string', 'max:80'],
            'issue_date' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
        ]);

        $doc = DB::transaction(function () use ($request, $vehicle, $validated): VehicleDocument {
            $nextNumber = (int) VehicleDocument::query()->lockForUpdate()->max('id') + 1;
            $filePath = null;
            $originalName = null;

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $filePath = $file->store("documents/vehicules/{$vehicle->registration_number}");
                $originalName = $file->getClientOriginalName();
            }

            $doc = VehicleDocument::create([
                'reference' => 'VDOC-'.now()->format('Y').'-'.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT),
                'vehicle_id' => $vehicle->id,
                'document_type' => $validated['document_type'],
                'issue_date' => $validated['issue_date'] ?? null,
                'expires_at' => $validated['expires_at'] ?? null,
                'file_path' => $filePath,
                'original_name' => $originalName,
                'notes' => $validated['notes'] ?? null,
                'uploaded_by' => $request->user()->id,
            ]);

            if (! empty($validated['expires_at'])) {
                $typeLower = mb_strtolower($validated['document_type']);
                if (str_contains($typeLower, 'assurance')) {
                    $vehicle->update(['insurance_expires_at' => $validated['expires_at']]);
                } elseif (str_contains($typeLower, 'visite')) {
                    $vehicle->update(['inspection_expires_at' => $validated['expires_at']]);
                } elseif (str_contains($typeLower, 'vignette')) {
                    $vehicle->update(['tax_expires_at' => $validated['expires_at']]);
                }
            }

            return $doc;
        }, attempts: 5);

        $this->recordActivity($request, 'vehicle.document_added', $vehicle, "Document {$doc->document_type} ({$doc->reference}) enregistré pour {$vehicle->registration_number}.");

        return response()->json([
            'success' => true,
            'message' => "Document véhicule ({$doc->reference}) enregistré.",
            'data' => $doc,
        ], 201);
    }

    public function downloadVehicleDocument(Request $request, VehicleDocument $document): StreamedResponse
    {
        abort_unless($document->file_path && Storage::disk()->exists($document->file_path), 404, 'Fichier introuvable.');
        $this->recordActivity($request, 'vehicle.document_downloaded', $document, "Téléchargement du document véhicule {$document->reference}.");

        return Storage::disk()->download($document->file_path, $document->original_name ?? "{$document->reference}.pdf");
    }

    public function storeMaintenance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'resource_type' => ['required', Rule::in(['asset', 'vehicle'])],
            'resource_id' => ['required', 'integer'],
            'type' => ['required', 'string', 'max:100'],
            'provider' => ['nullable', 'string', 'max:255'],
            'scheduled_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'mileage' => ['nullable', 'integer', 'min:0'],
            'next_maintenance_at' => ['nullable', 'date'],
            'next_maintenance_mileage' => ['nullable', 'integer', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['Planifiée', 'En cours', 'Terminée', 'Annulée'])],
            'description' => ['required', 'string'],
        ]);

        $resourceType = $validated['resource_type'];
        $requiredPermission = $resourceType === 'vehicle' ? 'vehicles.manage' : 'assets.manage';
        abort_unless($request->user()?->hasPermission($requiredPermission), 403, 'Vous ne disposez pas de la permission nécessaire pour gérer cette maintenance.');

        $model = $resourceType === 'vehicle'
            ? Vehicle::findOrFail($validated['resource_id'])
            : Asset::findOrFail($validated['resource_id']);

        $nextAt = $validated['next_maintenance_at'] ?? null;
        $nextKm = $validated['next_maintenance_mileage'] ?? null;
        unset($validated['resource_type'], $validated['resource_id'], $validated['next_maintenance_at'], $validated['next_maintenance_mileage']);

        $validated['cost'] = $validated['cost'] ?? 0;
        $maintenance = $model->maintenanceRecords()->create(array_merge($validated, ['recorded_by' => $request->user()->id]));

        if ($model instanceof Vehicle) {
            $vehicleUpdates = [];
            if (! empty($validated['mileage']) && $validated['mileage'] > $model->mileage) {
                $vehicleUpdates['mileage'] = $validated['mileage'];
            }
            if ($nextAt !== null) {
                $vehicleUpdates['next_maintenance_at'] = $nextAt;
            }
            if ($nextKm !== null) {
                $vehicleUpdates['next_maintenance_mileage'] = $nextKm;
            }
            if ($validated['status'] === 'En cours') {
                $vehicleUpdates['status'] = 'Maintenance';
            } elseif ($validated['status'] === 'Terminée' && $model->status === 'Maintenance') {
                $vehicleUpdates['status'] = 'Disponible';
            }
            if ($vehicleUpdates !== []) {
                $model->update($vehicleUpdates);
            }
        } elseif ($model instanceof Asset && $validated['status'] === 'Terminée' && $model->condition === 'Endommagé') {
            $model->update(['condition' => 'Bon état']);
        }

        $this->recordActivity($request, 'maintenance.created', $maintenance, "Intervention ({$maintenance->type}) enregistrée.");

        return response()->json(['success' => true, 'message' => 'Intervention enregistrée avec succès.', 'data' => $maintenance], 201);
    }

    public function updateMaintenance(Request $request, MaintenanceRecord $maintenanceRecord): JsonResponse
    {
        $requiredPermission = $maintenanceRecord->maintainable instanceof Vehicle ? 'vehicles.manage' : 'assets.manage';
        abort_unless($request->user()?->hasPermission($requiredPermission), 403, 'Vous ne disposez pas de la permission nécessaire pour gérer cette maintenance.');

        $validated = $request->validate([
            'status' => ['required', Rule::in(['Planifiée', 'En cours', 'Terminée', 'Annulée'])],
            'completed_at' => ['nullable', 'date'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'mileage' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        $maintenanceRecord->update(array_merge($validated, [
            'completed_at' => $validated['completed_at'] ?? ($validated['status'] === 'Terminée' ? now()->toDateString() : $maintenanceRecord->completed_at),
        ]));

        if ($maintenanceRecord->maintainable instanceof Vehicle && $validated['status'] === 'Terminée') {
            $updates = [];
            if ($maintenanceRecord->maintainable->status === 'Maintenance') {
                $updates['status'] = 'Disponible';
            }
            if (! empty($validated['mileage']) && $validated['mileage'] > $maintenanceRecord->maintainable->mileage) {
                $updates['mileage'] = $validated['mileage'];
            }
            if ($updates !== []) {
                $maintenanceRecord->maintainable->update($updates);
            }
        }

        $this->recordActivity($request, 'maintenance.updated', $maintenanceRecord, "Intervention #{$maintenanceRecord->id} mise à jour ({$validated['status']}).");

        return response()->json(['success' => true, 'message' => 'Intervention mise à jour.', 'data' => $maintenanceRecord->fresh()]);
    }

    public function exportVehicles(): StreamedResponse
    {
        $vehicles = Vehicle::with('driver')->orderBy('registration_number')->get();

        return response()->streamDownload(function () use ($vehicles): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Immatriculation', 'Marque', 'Modèle', 'Type', 'Kilométrage', 'État', 'Service', 'Conducteur', 'Échéance Assurance', 'Visite Technique', 'Vignette', 'Prochain Entretien (Date)', 'Prochain Entretien (Km)'], ';');
            foreach ($vehicles as $vehicle) {
                fputcsv($output, [
                    $vehicle->registration_number,
                    $vehicle->brand,
                    $vehicle->model,
                    $vehicle->vehicle_type ?? '-',
                    $vehicle->mileage ?? 0,
                    $vehicle->status,
                    $vehicle->department ?? '-',
                    $vehicle->driver?->name ?? '-',
                    $vehicle->insurance_expires_at?->format('d/m/Y') ?? '-',
                    $vehicle->inspection_expires_at?->format('d/m/Y') ?? '-',
                    $vehicle->tax_expires_at?->format('d/m/Y') ?? '-',
                    $vehicle->next_maintenance_at?->format('d/m/Y') ?? '-',
                    $vehicle->next_maintenance_mileage ?? '-',
                ], ';');
            }
            fclose($output);
        }, 'parc-automobile-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* =========================================================================
       MODULE 6 : DÉPENSES & DÉCAISSEMENTS (RM-10, RM-11, RM-12)
       ========================================================================= */

    public function expenses(Request $request): JsonResponse
    {
        $query = Expense::with(['materialRequest', 'supplier', 'attachments', 'audits.user', 'creator', 'validator']);

        if ($request->filled('filter')) {
            if ($request->filter === 'unjustified') {
                $query->whereColumn('disbursed_amount', '>', 'justified_amount');
            } elseif ($request->filter === 'overrun') {
                $query->where(function ($sub): void {
                    $sub->whereColumn('committed_amount', '>', 'authorized_amount')
                        ->orWhereColumn('disbursed_amount', '>', 'authorized_amount')
                        ->orWhere('status', 'Nouvelle validation requise');
                });
            } elseif ($request->filter === 'validated') {
                $query->where('status', 'Validé');
            }
        }

        return $this->collectionResponse($query->latest()->get());
    }

    public function storeExpense(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'material_request_id' => ['nullable', 'exists:material_requests,id'],
            'direct_authorization' => ['sometimes', 'boolean'],
            'authorization_reference' => ['nullable', 'string', 'max:100'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'beneficiary' => ['nullable', 'string', 'max:255'],
            'estimated_amount' => ['nullable', 'numeric', 'min:0'],
            'authorized_amount' => ['required', 'numeric', 'min:0'],
            'committed_amount' => ['nullable', 'numeric', 'min:0'],
            'disbursed_amount' => ['nullable', 'numeric', 'min:0'],
            'justified_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'overrun_reason' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
            'attachment_type' => ['nullable', 'string', 'max:60'],
        ]);

        $useDirectAuth = $request->boolean('direct_authorization');

        if (empty($validated['material_request_id']) && empty($validated['authorization_reference']) && ! $useDirectAuth) {
            return response()->json([
                'success' => false,
                'message' => 'Une dépense doit obligatoirement être rattachée à une demande ou à une autorisation directe.',
            ], 422);
        }

        $expense = DB::transaction(function () use ($request, $validated, $useDirectAuth): Expense {
            $nextNumber = (int) Expense::query()->lockForUpdate()->max('id') + 1;
            $committed = (float) ($validated['committed_amount'] ?? 0);
            $disbursed = (float) ($validated['disbursed_amount'] ?? 0);
            $authorized = (float) $validated['authorized_amount'];
            $isOverrun = $committed > $authorized || $disbursed > $authorized;

            $payload = collect($validated)->except(['attachment', 'attachments', 'attachment_type', 'direct_authorization'])->all();
            if (empty($payload['material_request_id']) && empty($payload['authorization_reference']) && $useDirectAuth) {
                $payload['authorization_reference'] = 'AUT-'.now()->format('Y').'-'.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
            }
            $expense = Expense::create(array_merge($payload, [
                'reference' => 'DEP-'.now()->format('Y').'-'.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT),
                'estimated_amount' => $validated['estimated_amount'] ?? $authorized,
                'committed_amount' => $committed,
                'disbursed_amount' => $disbursed,
                'justified_amount' => $validated['justified_amount'] ?? 0,
                'status' => $isOverrun ? 'Nouvelle validation requise' : 'Autorisé',
                'created_by' => $request->user()->id,
            ]));

            if ($request->hasFile('attachment')) {
                $this->saveExpenseFile($expense, $request->file('attachment'), $validated['attachment_type'] ?? 'Facture', $request->user()->id);
            }
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $this->saveExpenseFile($expense, $file, $validated['attachment_type'] ?? 'Facture', $request->user()->id);
                }
            }

            ExpenseAudit::create([
                'expense_id' => $expense->id,
                'user_id' => $request->user()->id,
                'action' => 'created',
                'before_state' => null,
                'after_state' => $expense->only(['estimated_amount', 'authorized_amount', 'committed_amount', 'disbursed_amount', 'justified_amount', 'status']),
                'reason' => $validated['notes'] ?? 'Enregistrement initial de la dépense.',
            ]);

            if ($isOverrun) {
                $this->notifyOperationalManagers(
                    'expense.overrun',
                    'Dépassement budgétaire détecté',
                    "La dépense {$expense->reference} dépasse le montant autorisé et requiert une nouvelle validation.",
                    '#operations',
                    'notify_on_expense_overrun',
                );
            }

            return $expense;
        }, attempts: 5);

        return $this->created($request, $expense->load(['materialRequest', 'supplier', 'attachments', 'audits.user']), 'expense.created', "Dépense {$expense->reference} enregistrée.");
    }

    public function updateExpense(Request $request, Expense $expense): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'beneficiary' => ['nullable', 'string', 'max:255'],
            'estimated_amount' => ['sometimes', 'numeric', 'min:0'],
            'committed_amount' => ['sometimes', 'numeric', 'min:0'],
            'disbursed_amount' => ['sometimes', 'numeric', 'min:0'],
            'justified_amount' => ['sometimes', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'amendment_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($expense->status === 'Validé' && empty(trim((string) ($validated['amendment_reason'] ?? '')))) {
            return response()->json([
                'success' => false,
                'message' => 'Une opération financière déjà validée ne peut pas être modifiée sans motif explicite de révision.',
            ], 422);
        }

        $before = $expense->only(['estimated_amount', 'authorized_amount', 'committed_amount', 'disbursed_amount', 'justified_amount', 'payment_method', 'beneficiary', 'supplier_id', 'status']);

        DB::transaction(function () use ($request, $expense, $validated, $before): void {
            $reason = $validated['amendment_reason'] ?? null;
            unset($validated['amendment_reason']);

            $expense->fill($validated);
            $overrun = (float) $expense->committed_amount > (float) $expense->authorized_amount
                || (float) $expense->disbursed_amount > (float) $expense->authorized_amount;

            if ($overrun) {
                $expense->status = 'Nouvelle validation requise';
                $expense->overrun_reason = $reason ?? 'Dépassement du montant autorisé.';
                $this->notifyOperationalManagers(
                    'expense.overrun',
                    'Nouvelle validation requise (dépassement)',
                    "La dépense {$expense->reference} présente un dépassement du montant autorisé.",
                    '#operations',
                    'notify_on_expense_overrun',
                );
            } elseif ($before['status'] === 'Validé' && $reason !== null) {
                $expense->status = 'Révisé - à revalider';
            }

            $expense->save();

            ExpenseAudit::create([
                'expense_id' => $expense->id,
                'user_id' => $request->user()->id,
                'action' => 'updated',
                'before_state' => $before,
                'after_state' => $expense->only(array_keys($before)),
                'reason' => $reason ?? 'Suivi d’exécution financière / justificatifs.',
            ]);
        });

        $this->recordActivity($request, 'expense.updated', $expense, "Dépense {$expense->reference} modifiée et tracée.", [
            'before' => $before,
            'after' => $expense->only(array_keys($before)),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Opération financière mise à jour et historisée.',
            'data' => $expense->fresh(['materialRequest', 'supplier', 'attachments', 'audits.user']),
        ]);
    }

    public function validateExpense(Request $request, Expense $expense): JsonResponse
    {
        $validated = $request->validate([
            'authorized_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $before = $expense->only(['authorized_amount', 'committed_amount', 'disbursed_amount', 'justified_amount', 'status']);
        $newAuthorized = (float) $validated['authorized_amount'];
        $stillOver = (float) $expense->committed_amount > $newAuthorized || (float) $expense->disbursed_amount > $newAuthorized;

        $expense->update([
            'authorized_amount' => $newAuthorized,
            'notes' => $validated['notes'] ?? $expense->notes,
            'status' => $stillOver ? 'Nouvelle validation requise' : 'Validé',
            'validated_by' => $request->user()->id,
            'validated_at' => now(),
        ]);

        ExpenseAudit::create([
            'expense_id' => $expense->id,
            'user_id' => $request->user()->id,
            'action' => 'validated',
            'before_state' => $before,
            'after_state' => $expense->only(['authorized_amount', 'committed_amount', 'disbursed_amount', 'justified_amount', 'status']),
            'reason' => $validated['notes'] ?? 'Contrôle et validation financière.',
        ]);

        $this->recordActivity($request, 'expense.validated', $expense, "Dépense {$expense->reference} contrôlée/validée par {$request->user()->name}.", [
            'before' => $before,
            'after' => $expense->only(['authorized_amount', 'status']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Validation financière enregistrée.',
            'data' => $expense->fresh(['materialRequest', 'supplier', 'attachments', 'audits.user']),
        ]);
    }

    public function addExpenseAttachment(Request $request, Expense $expense): JsonResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', Rule::in(['Facture', 'Reçu', 'Bon de livraison', 'Autre'])],
            'title' => ['nullable', 'string', 'max:255'],
            'justified_amount_increment' => ['nullable', 'numeric', 'min:0'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
        ]);

        $attachment = $this->saveExpenseFile(
            $expense,
            $request->file('file'),
            $validated['document_type'],
            $request->user()->id,
            $validated['title'] ?? null,
        );

        if (! empty($validated['justified_amount_increment'])) {
            $before = $expense->only(['justified_amount']);
            $expense->update([
                'justified_amount' => (float) $expense->justified_amount + (float) $validated['justified_amount_increment'],
            ]);
            ExpenseAudit::create([
                'expense_id' => $expense->id,
                'user_id' => $request->user()->id,
                'action' => 'justification_added',
                'before_state' => $before,
                'after_state' => $expense->only(['justified_amount']),
                'reason' => "Pièce justificative jointe : {$attachment->title}",
            ]);
        }

        $this->recordActivity($request, 'expense.attachment_added', $expense, "Justificatif ({$attachment->document_type}) ajouté à {$expense->reference}.");

        return response()->json([
            'success' => true,
            'message' => 'Pièce justificative enregistrée.',
            'data' => $expense->fresh(['attachments', 'audits.user']),
        ], 201);
    }

    public function downloadExpenseAttachment(Request $request, ExpenseAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->file_path && Storage::disk()->exists($attachment->file_path), 404, 'Justificatif introuvable.');
        $this->recordActivity($request, 'expense.attachment_downloaded', $attachment, "Téléchargement du justificatif {$attachment->title}.");

        return Storage::disk()->download($attachment->file_path, $attachment->original_name);
    }

    public function exportExpenses(): StreamedResponse
    {
        $expenses = Expense::with(['materialRequest', 'supplier', 'creator'])->latest()->get();

        return response()->streamDownload(function () use ($expenses): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Référence', 'Demande Rattachée', 'Fournisseur', 'Bénéficiaire', 'Mode Paiement', 'Montant Estimatif', 'Montant Autorisé', 'Montant Engagé', 'Montant Décaissé', 'Montant Justifié', 'Solde Restant', 'Statut Dépassement', 'Date Enregistrement'], ';');
            foreach ($expenses as $expense) {
                fputcsv($output, [
                    $expense->expense_reference,
                    $expense->materialRequest?->request_reference ?? 'Direct',
                    $expense->supplier?->name ?? '-',
                    $expense->beneficiary ?? '-',
                    $expense->payment_method ?? '-',
                    $expense->estimated_amount ?? 0,
                    $expense->authorized_amount ?? 0,
                    $expense->committed_amount ?? 0,
                    $expense->disbursed_amount ?? 0,
                    $expense->justified_amount ?? 0,
                    $expense->balance ?? 0,
                    $expense->is_overrun ? 'Dépassement détecté' : 'Conforme',
                    $expense->created_at?->format('d/m/Y H:i') ?? '-',
                ], ';');
            }
            fclose($output);
        }, 'depenses-decaissements-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* =========================================================================
       MODULE 3 : INVENTAIRES PHYSIQUES (RM-09)
       ========================================================================= */

    public function inventories(): JsonResponse
    {
        return $this->collectionResponse(InventorySession::with(['items.article', 'creator', 'validator'])->latest()->get());
    }

    public function storeInventory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scope' => ['required', Rule::in(['Général', 'Partiel'])],
            'location' => ['nullable', 'string', 'max:255'],
            'inventory_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.article_id' => ['required', 'exists:articles,id', 'distinct'],
            'items.*.physical_quantity' => ['required', 'integer', 'min:0'],
            'items.*.justification' => ['nullable', 'string'],
        ]);

        $inventory = DB::transaction(function () use ($request, $validated): InventorySession {
            $nextNumber = (int) InventorySession::query()->lockForUpdate()->max('id') + 1;
            $inventory = InventorySession::create([
                'reference' => 'INV-'.now()->format('Y').'-'.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT),
                'scope' => $validated['scope'],
                'location' => $validated['location'] ?? null,
                'inventory_date' => $validated['inventory_date'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->id,
                'status' => 'En attente de validation',
            ]);
            $articles = Article::whereIn('id', collect($validated['items'])->pluck('article_id'))->get()->keyBy('id');
            foreach ($validated['items'] as $item) {
                $theoretical = $articles[$item['article_id']]->quantity;
                InventoryItem::create([
                    'inventory_session_id' => $inventory->id,
                    'article_id' => $item['article_id'],
                    'theoretical_quantity' => $theoretical,
                    'physical_quantity' => $item['physical_quantity'],
                    'variance' => $item['physical_quantity'] - $theoretical,
                    'justification' => $item['justification'] ?? null,
                ]);
            }

            return $inventory;
        }, attempts: 5);

        return $this->created($request, $inventory->load('items.article'), 'inventory.created', "Session d'inventaire {$inventory->reference} soumise à validation.");
    }

    public function validateInventory(Request $request, InventorySession $inventory): JsonResponse
    {
        if ($inventory->status !== 'En attente de validation') {
            return response()->json(['success' => false, 'message' => 'Cet inventaire ne peut plus être validé.'], 422);
        }

        $invalidItem = $inventory->items()->where('variance', '!=', 0)->where(function ($query): void {
            $query->whereNull('justification')->orWhere('justification', '');
        })->exists();
        if ($invalidItem) {
            return response()->json(['success' => false, 'message' => 'Chaque écart d’inventaire doit obligatoirement comporter une justification avant validation.'], 422);
        }

        DB::transaction(function () use ($inventory, $request): void {
            $inventory->load('items.article');
            foreach ($inventory->items as $item) {
                if ($item->variance === 0) {
                    continue;
                }
                $article = Article::query()->lockForUpdate()->findOrFail($item->article_id);
                $article->update(Article::stockState($item->physical_quantity, $article->threshold));
                StockMovement::create([
                    'article_id' => $article->id,
                    'user_id' => $request->user()->id,
                    'type' => $item->variance > 0 ? 'Entrée' : 'Sortie',
                    'source' => StockMovement::SOURCE_INVENTORY_ADJUSTMENT,
                    'quantity' => abs($item->variance),
                    'reason' => "Ajustement inventaire {$inventory->reference}",
                    'justification' => $item->justification,
                    'validation_status' => 'Validé',
                ]);
            }
            $inventory->update(['status' => 'Validé', 'validated_by' => $request->user()->id, 'validated_at' => now()]);
        });

        $this->recordActivity($request, 'inventory.validated', $inventory, "Inventaire {$inventory->reference} validé et stocks ajustés.");

        return response()->json(['success' => true, 'message' => 'Inventaire validé et stocks corrigés.', 'data' => $inventory->load('items.article')]);
    }

    /* =========================================================================
       MODULE 10 : PARAMÉTRAGE, RÉFÉRENTIELS & DÉLÉGATIONS
       ========================================================================= */

    public function publicCatalogReferences(): JsonResponse
    {
        $publicKeys = ['company_name', 'company_short_code', 'company_address', 'company_phone', 'company_email', 'default_min_threshold', 'default_max_threshold', 'sla_processing_hours'];

        return response()->json(['success' => true, 'data' => [
            'departments' => Schema::hasTable('departments') ? Department::where('is_active', true)->orderBy('name')->get() : [],
            'locations' => Schema::hasTable('storage_locations') ? StorageLocation::where('is_active', true)->orderBy('name')->get() : [],
            'suppliers' => Schema::hasTable('suppliers') ? Supplier::where('is_active', true)->orderBy('name')->get() : [],
            'request_categories' => Schema::hasTable('request_categories') ? RequestCategory::where('is_active', true)->orderBy('id')->get() : [],
            'article_categories' => Schema::hasTable('article_categories') ? ArticleCategory::where('is_active', true)->orderBy('name')->get() : [],
            'units' => Schema::hasTable('measurement_units') ? MeasurementUnit::where('is_active', true)->orderBy('name')->get() : [],
            'settings' => Schema::hasTable('system_settings') ? SystemSetting::whereIn('key', $publicKeys)->pluck('value', 'key') : [],
        ]]);
    }

    public function referenceData(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [
            'departments' => Department::orderBy('name')->get(),
            'locations' => StorageLocation::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'request_categories' => Schema::hasTable('request_categories') ? RequestCategory::orderBy('id')->get() : [],
            'article_categories' => ArticleCategory::orderBy('name')->get(),
            'units' => MeasurementUnit::orderBy('name')->get(),
            'delegations' => Schema::hasTable('approval_delegations') ? ApprovalDelegation::with(['delegator:id,name,role,service', 'delegate:id,name,role,service'])->latest()->get() : [],
            'settings' => SystemSetting::orderBy('group')->orderBy('key')->get(),
        ]]);
    }

    public function storeReference(Request $request, string $type): JsonResponse
    {
        if ($type === 'delegations') {
            $validated = $request->validate([
                'delegator_id' => ['nullable', 'exists:users,id'],
                'delegate_id' => ['required', 'exists:users,id'],
                'scope' => ['required', Rule::in(['Toutes les demandes', 'Service spécifique', 'Dépenses'])],
                'department' => ['nullable', 'string', 'max:255'],
                'starts_at' => ['required', 'date'],
                'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
                'is_active' => ['sometimes', 'boolean'],
                'notes' => ['nullable', 'string'],
            ]);
            $validated['delegator_id'] = $validated['delegator_id'] ?? $request->user()->id;
            $delegation = ApprovalDelegation::create($validated);
            $this->sendSystemNotification(
                (int) $validated['delegate_id'],
                'delegation.granted',
                'Délégation de validation accordée',
                "Vous avez reçu une délégation de validation ({$delegation->scope}) à compter du {$delegation->starts_at->format('d/m/Y')}.",
                '#dashboard',
            );

            return $this->created($request, $delegation->load(['delegator', 'delegate']), 'delegation.created', 'Délégation de validation enregistrée.');
        }

        $modelClass = match ($type) {
            'departments' => Department::class,
            'locations' => StorageLocation::class,
            'suppliers' => Supplier::class,
            'request-categories' => RequestCategory::class,
            default => abort(404),
        };
        $rules = match ($type) {
            'departments' => ['name' => ['required', 'string', 'max:255', 'unique:departments'], 'code' => ['nullable', 'string', 'max:30', 'unique:departments'], 'is_active' => ['sometimes', 'boolean']],
            'locations' => ['name' => ['required', 'string', 'max:255', 'unique:storage_locations'], 'site' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'is_active' => ['sometimes', 'boolean']],
            'suppliers' => ['name' => ['required', 'string', 'max:255'], 'contact_name' => ['nullable', 'string', 'max:255'], 'email' => ['nullable', 'email'], 'phone' => ['nullable', 'string', 'max:50'], 'address' => ['nullable', 'string'], 'is_active' => ['sometimes', 'boolean']],
            'request-categories' => ['name' => ['required', 'string', 'max:255', 'unique:request_categories'], 'description' => ['nullable', 'string'], 'requires_stock_items' => ['sometimes', 'boolean'], 'is_active' => ['sometimes', 'boolean']],
        };
        /** @var Model $model */
        $model = $modelClass::create($request->validate($rules));

        return $this->created($request, $model, "reference.{$type}.created", 'Élément de paramétrage créé.');
    }

    public function updateReference(Request $request, string $type, int $id): JsonResponse
    {
        $modelClass = match ($type) {
            'departments' => Department::class,
            'locations' => StorageLocation::class,
            'suppliers' => Supplier::class,
            'request-categories' => RequestCategory::class,
            'delegations' => ApprovalDelegation::class,
            default => abort(404),
        };

        /** @var Model $model */
        $model = $modelClass::findOrFail($id);
        $before = $model->toArray();

        $rules = match ($type) {
            'departments' => ['name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('departments')->ignore($id)], 'code' => ['nullable', 'string', 'max:30', Rule::unique('departments')->ignore($id)], 'is_active' => ['sometimes', 'boolean']],
            'locations' => ['name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('storage_locations')->ignore($id)], 'site' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'is_active' => ['sometimes', 'boolean']],
            'suppliers' => ['name' => ['sometimes', 'required', 'string', 'max:255'], 'contact_name' => ['nullable', 'string', 'max:255'], 'email' => ['nullable', 'email'], 'phone' => ['nullable', 'string', 'max:50'], 'address' => ['nullable', 'string'], 'is_active' => ['sometimes', 'boolean']],
            'request-categories' => ['name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('request_categories')->ignore($id)], 'description' => ['nullable', 'string'], 'requires_stock_items' => ['sometimes', 'boolean'], 'is_active' => ['sometimes', 'boolean']],
            'delegations' => ['scope' => ['sometimes', 'string'], 'department' => ['nullable', 'string'], 'starts_at' => ['sometimes', 'date'], 'ends_at' => ['nullable', 'date'], 'is_active' => ['sometimes', 'boolean'], 'notes' => ['nullable', 'string']],
        };

        $model->update($request->validate($rules));
        $this->recordActivity($request, "reference.{$type}.updated", $model, "Paramètre {$type} #{$id} mis à jour.", ['before' => $before, 'after' => $model->toArray()]);

        return response()->json(['success' => true, 'message' => 'Élément mis à jour.', 'data' => $model->fresh()]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string', 'max:255'],
            'settings.*.value' => ['nullable'],
            'settings.*.group' => ['nullable', 'string', 'max:80'],
        ]);

        foreach ($validated['settings'] as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => is_scalar($setting['value'] ?? null) || ($setting['value'] ?? null) === null
                        ? (string) ($setting['value'] ?? '')
                        : json_encode($setting['value']),
                    'group' => $setting['group'] ?? 'general',
                ],
            );
        }

        $firstSetting = SystemSetting::first();
        $this->recordActivity($request, 'settings.updated', $firstSetting, 'Paramètres généraux de l’application mis à jour.', [
            'keys_count' => count($validated['settings']),
        ]);

        return response()->json(['success' => true, 'message' => 'Paramètres enregistrés avec succès.']);
    }

    /* =========================================================================
       MODULE 9 : SAUVEGARDE & RESTAURATION DES DONNÉES
       ========================================================================= */

    public function backups(): JsonResponse
    {
        return $this->collectionResponse(SystemBackup::with('creator:id,name')->latest()->get());
    }

    public function createBackup(Request $request): JsonResponse
    {
        $backup = SystemBackup::createSnapshot($request->user());

        $this->recordActivity($request, 'backup.created', $backup, "Sauvegarde système {$backup->reference} générée ({$backup->records_count} enregistrements).");

        return response()->json([
            'success' => true,
            'message' => "Sauvegarde {$backup->reference} générée avec succès.",
            'data' => $backup->load('creator:id,name'),
        ], 201);
    }

    public function downloadBackup(Request $request, SystemBackup $backup): StreamedResponse
    {
        abort_unless(Storage::disk()->exists($backup->file_path), 404, 'Archive de sauvegarde introuvable.');
        $this->recordActivity($request, 'backup.downloaded', $backup, "Téléchargement de la sauvegarde {$backup->reference}.");

        return Storage::disk()->download($backup->file_path, $backup->filename, ['Content-Type' => 'application/json']);
    }

    public function restoreBackup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'backup_id' => ['nullable', 'exists:system_backups,id'],
            'file' => ['nullable', 'file', 'max:20480'],
        ]);

        if (empty($validated['backup_id']) && ! $request->hasFile('file')) {
            return response()->json(['success' => false, 'message' => 'Veuillez sélectionner une sauvegarde existante ou téléverser une archive JSON.'], 422);
        }

        $backupModel = null;
        if (! empty($validated['backup_id'])) {
            $backupModel = SystemBackup::findOrFail($validated['backup_id']);
            abort_unless(Storage::disk()->exists($backupModel->file_path), 404, 'Fichier de sauvegarde introuvable.');
            $rawContent = Storage::disk()->get($backupModel->file_path);
        } else {
            $rawContent = (string) file_get_contents($request->file('file')->getRealPath());
        }

        $decoded = json_decode($rawContent, true);
        if (! is_array($decoded) || ! isset($decoded['tables']) || ! is_array($decoded['tables'])) {
            return response()->json(['success' => false, 'message' => 'Format d’archive de sauvegarde invalide.'], 422);
        }

        $restoredTables = 0;
        $restoredRows = 0;

        DB::transaction(function () use ($decoded, &$restoredTables, &$restoredRows): void {
            Schema::disableForeignKeyConstraints();
            try {
                foreach ($decoded['tables'] as $table => $rows) {
                    if (! is_string($table) || $table === 'users' || ! Schema::hasTable($table) || ! is_array($rows)) {
                        continue;
                    }
                    DB::table($table)->delete();
                    foreach (array_chunk($rows, 100) as $chunk) {
                        if ($chunk !== []) {
                            DB::table($table)->insert($chunk);
                            $restoredRows += count($chunk);
                        }
                    }
                    $restoredTables++;
                }
            } finally {
                Schema::enableForeignKeyConstraints();
            }
        });

        if ($backupModel) {
            $backupModel->update(['restored_at' => now()]);
        }

        $this->recordActivity($request, 'backup.restored', $backupModel, "Restauration des données effectuée ({$restoredTables} tables, {$restoredRows} lignes).");

        return response()->json([
            'success' => true,
            'message' => "Restauration terminée avec succès ({$restoredTables} tables restaurées).",
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $items = SystemNotification::where('user_id', $request->user()->id)->latest()->limit(50)->get();

        return response()->json([
            'success' => true,
            'count' => $items->count(),
            'unread_count' => $items->whereNull('read_at')->count(),
            'data' => $items,
        ]);
    }

    public function readNotification(Request $request, SystemNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['read_at' => now()]);

        return response()->json(['success' => true, 'data' => $notification]);
    }

    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        SystemNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Toutes les notifications ont été marquées comme lues.']);
    }

    private function saveExpenseFile(
        Expense $expense,
        UploadedFile $file,
        string $docType,
        int $userId,
        ?string $customTitle = null,
    ): ExpenseAttachment {
        $path = $file->store("documents/depenses/{$expense->reference}");

        return ExpenseAttachment::create([
            'expense_id' => $expense->id,
            'document_type' => $docType,
            'title' => $customTitle ?: $file->getClientOriginalName(),
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'file_size' => number_format($file->getSize() / 1024, 0, ',', ' ').' Ko',
            'uploaded_by' => $userId,
        ]);
    }

    private function collectionResponse($data): JsonResponse
    {
        return response()->json(['success' => true, 'count' => $data->count(), 'data' => $data]);
    }

    private function created(Request $request, Model $model, string $action, string $message): JsonResponse
    {
        $this->recordActivity($request, $action, $model, $message);

        return response()->json(['success' => true, 'message' => $message, 'data' => $model], 201);
    }
}
