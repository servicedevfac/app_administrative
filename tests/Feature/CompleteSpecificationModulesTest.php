<?php

namespace Tests\Feature;

use App\Models\AdministrativeDocument;
use App\Models\Article;
use App\Models\Asset;
use App\Models\ExpenseAudit;
use App\Models\InventorySession;
use App\Models\MaterialRequest;
use App\Models\SavedReport;
use App\Models\SystemBackup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompleteSpecificationModulesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function authorizationHeaders(User $user): array
    {
        $this->app['auth']->forgetGuards();
        $token = $user->issueApiToken();

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_module_1_users_auto_matricule_permissions_password_reset_and_login_history(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'password' => 'AdminPass123!',
        ]);

        // Création d'utilisateur sans matricule manuel -> généré automatiquement EMP-YYYY-xxxxx
        $created = $this->withHeaders($this->authorizationHeaders($admin))->postJson('/api/users', [
            'name' => 'Kouassi Yao',
            'email' => 'kouassi.yao@gestionplus.ci',
            'password' => 'TempPass123!',
            'role' => User::ROLE_COLLABORATOR,
            'service' => 'Informatique',
            'phone' => '+225 07 00 00 01',
            'office_location' => 'Bureau 204',
            'permissions' => ['requests.create', 'stock.view'],
        ])->assertCreated()
            ->assertJsonPath('success', true);

        $matricule = $created->json('data.matricule');
        $this->assertMatchesRegularExpression('/^EMP-\d{4}-\d{5}$/', (string) $matricule);

        $userId = (int) $created->json('data.id');

        // Réinitialisation du mot de passe par l'administrateur
        $this->withHeaders($this->authorizationHeaders($admin))
            ->putJson("/api/users/{$userId}/reset-password", [
                'password' => 'ResetPass456!',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        // Connexion avec le nouveau mot de passe + journalisation
        $login = $this->postJson('/api/auth/login', [
            'email' => 'kouassi.yao@gestionplus.ci',
            'password' => 'ResetPass456!',
        ])->assertOk()
            ->assertJsonPath('success', true);

        $userToken = $login->json('token');

        // Demande de mot de passe oublié (notification aux administrateurs)
        $this->postJson('/api/auth/forgot-password', [
            'email' => 'kouassi.yao@gestionplus.ci',
            'reason' => 'Mot de passe perdu après congés',
        ])->assertOk()
            ->assertJsonPath('success', true);

        // Historique personnel des connexions
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => "Bearer {$userToken}"])
            ->getJson('/api/auth/login-history')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Historique global des connexions côté administrateur
        $this->withHeaders($this->authorizationHeaders($admin))
            ->getJson('/api/login-history')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Désactivation immédiate révoquant le jeton actif
        $this->withHeaders($this->authorizationHeaders($admin))
            ->putJson("/api/users/{$userId}/active", ['is_active' => false])
            ->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => "Bearer {$userToken}"])
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_module_2_full_request_lifecycle_categories_attachments_and_non_stock_requests(): void
    {
        Storage::fake('local');

        $manager = User::factory()->create(['role' => User::ROLE_MANAGER, 'service' => 'Informatique']);
        $agent = User::factory()->create(['role' => User::ROLE_MANAGER, 'service' => 'Logistique & Moyens Généraux']);
        $employee = User::factory()->create(['role' => User::ROLE_COLLABORATOR, 'service' => 'Informatique']);

        $article = Article::create([
            'code' => 'ART-101',
            'name' => 'Souris sans fil ergonomique',
            'category' => 'Informatique',
            'unit' => 'Pièce',
            'quantity' => 15,
            'threshold' => 3,
            'maximum_threshold' => 30,
            'unit_cost' => 12000,
            'status' => Article::STATUS_AVAILABLE,
            'is_available' => true,
            'created_by' => $manager->id,
        ]);

        // Création avec catégorie officielle + urgence Critique + pièces jointes multiples
        $createRes = $this->withHeaders($this->authorizationHeaders($employee))->post('/api/requests', [
            'category' => 'Matériel informatique et accessoires',
            'service' => 'Informatique',
            'urgency' => 'Critique',
            'type_operation' => 'Dotation permanente',
            'motif' => 'Remplacement périphériques défectueux salle serveur',
            'items' => [
                ['article_id' => $article->id, 'quantity' => 4],
            ],
            'attachments' => [
                UploadedFile::fake()->create('devis-souris.pdf', 120, 'application/pdf'),
                UploadedFile::fake()->image('photo-panne.jpg'),
            ],
        ], ['Accept' => 'application/json'])->assertCreated();

        $reqId = (int) $createRes->json('data.id');
        $reference = (string) $createRes->json('data.reference');
        $itemId = (int) $createRes->json('data.items.0.id');

        $this->assertMatchesRegularExpression('/^DEM-\d{4}-\d{5}$/', $reference);
        $this->assertSame(MaterialRequest::STATUS_SUBMITTED, $createRes->json('data.status'));
        $this->assertCount(2, $createRes->json('data.documents'));

        // RM-05 : Rejet sans motif doit échouer
        $this->withHeaders($this->authorizationHeaders($manager))
            ->putJson("/api/requests/{$reqId}/reject", [])
            ->assertStatus(422);

        // Étape 1 : Vérification -> En attente de validation
        $this->withHeaders($this->authorizationHeaders($manager))
            ->putJson("/api/requests/{$reqId}/verify", [
                'next_status' => MaterialRequest::STATUS_PENDING_VALIDATION,
                'comment' => 'Stock vérifié en magasin.',
            ])->assertOk()
            ->assertJsonPath('data.status', MaterialRequest::STATUS_PENDING_VALIDATION);

        // Étape 2 : Validation avec ajustement de quantité accordée (3 au lieu de 4) + génération du bon officiel
        $this->withHeaders($this->authorizationHeaders($manager))
            ->putJson("/api/requests/{$reqId}/approve", [
                'comment' => 'Accordé pour 3 unités prioritaires.',
                'quantities' => [(string) $itemId => 3],
            ])->assertOk()
            ->assertJsonPath('data.status', MaterialRequest::STATUS_VALIDATED);

        $this->assertDatabaseHas('articles', ['id' => $article->id, 'quantity' => 12]);
        $this->assertDatabaseHas('request_items', ['id' => $itemId, 'quantity_approved' => 3]);

        // Étape 3 : Affectation à un agent d'exécution
        $this->withHeaders($this->authorizationHeaders($manager))
            ->putJson("/api/requests/{$reqId}/assign", ['assigned_to' => $agent->id])
            ->assertOk()
            ->assertJsonPath('data.assigned_to', $agent->id);

        // Étape 4 : les actions d'exécution sont historisées sans créer de nouveau statut.
        $this->withHeaders($this->authorizationHeaders($agent))
            ->putJson("/api/requests/{$reqId}/execution-status", [
                'comment' => 'Préparation du colis.',
            ])->assertOk()
            ->assertJsonPath('data.status', MaterialRequest::STATUS_VALIDATED);

        $this->withHeaders($this->authorizationHeaders($agent))
            ->putJson("/api/requests/{$reqId}/deliver")
            ->assertOk()
            ->assertJsonPath('data.status', MaterialRequest::STATUS_VALIDATED);

        // Étape 5 : Confirmation de réception par le demandeur
        $this->withHeaders($this->authorizationHeaders($employee))
            ->putJson("/api/requests/{$reqId}/receive", ['comment' => 'Bien reçu les 3 souris.'])
            ->assertOk();

        // Étape 6 : Clôture finale
        $this->withHeaders($this->authorizationHeaders($manager))
            ->putJson("/api/requests/{$reqId}/close", ['comment' => 'Dossier complet et archivé.'])
            ->assertOk()
            ->assertJsonPath('data.status', MaterialRequest::STATUS_VALIDATED);

        $this->assertEmpty(
            MaterialRequest::query()->whereNotIn('status', MaterialRequest::STATUSES)->pluck('status')->all()
        );

        // Demande hors stock (Prestations de services) sans article_id
        $serviceReq = $this->withHeaders($this->authorizationHeaders($employee))->postJson('/api/requests', [
            'category' => 'Prestations de services',
            'service' => 'Informatique',
            'urgency' => 'Normale',
            'motif' => 'Intervention climatisation salle serveur',
            'service_description' => 'Maintenance préventive de 2 climatiseurs split 24000 BTU.',
        ])->assertCreated()
            ->assertJsonPath('data.category', 'Prestations de services');

        $serviceReqId = (int) $serviceReq->json('data.id');
        $this->withHeaders($this->authorizationHeaders($manager))
            ->putJson("/api/requests/{$serviceReqId}/approve", ['comment' => 'Prestation validée.'])
            ->assertOk()
            ->assertJsonPath('data.status', MaterialRequest::STATUS_VALIDATED);
    }

    public function test_module_3_stock_movements_rm07_replenishment_and_rm09_inventory(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);

        $article = Article::create([
            'code' => 'ART-202',
            'name' => 'Toner Laser Noir',
            'category' => 'Consommables informatiques',
            'unit' => 'Pièce',
            'quantity' => 5,
            'threshold' => 6,
            'maximum_threshold' => 20,
            'unit_cost' => 45000,
            'location' => 'Magasin Central',
            'status' => Article::STATUS_LOW,
            'is_available' => true,
            'created_by' => $manager->id,
        ]);

        $headers = $this->authorizationHeaders($manager);

        // RM-07 : Sortie sans demande autorisée et sans urgence refusée
        $this->withHeaders($headers)->postJson('/api/movements', [
            'article_id' => $article->id,
            'type' => 'Sortie',
            'quantity' => 1,
            'reason' => 'Sortie non rattachée',
        ])->assertStatus(422);

        // Sortie d'urgence motivée autorisée
        $this->withHeaders($headers)->postJson('/api/movements', [
            'article_id' => $article->id,
            'type' => 'Sortie',
            'quantity' => 1,
            'is_emergency' => true,
            'reason' => 'Urgence impression rapport Direction Générale',
        ])->assertCreated();

        $this->assertDatabaseHas('articles', ['id' => $article->id, 'quantity' => 4]);

        // Transfert entre emplacements et services
        $this->withHeaders($headers)->postJson('/api/movements', [
            'article_id' => $article->id,
            'type' => 'Transfert',
            'quantity' => 2,
            'from_location' => 'Magasin Central',
            'to_location' => 'Réserve Informatique',
            'from_service' => 'Logistique',
            'to_service' => 'Informatique',
            'reason' => 'Dotation réserve IT',
        ])->assertCreated();

        // Retour et Perte
        $this->withHeaders($headers)->postJson('/api/movements', [
            'article_id' => $article->id,
            'type' => 'Retour',
            'quantity' => 2,
            'reason' => 'Retour surplus mission',
        ])->assertCreated();

        $this->withHeaders($headers)->postJson('/api/movements', [
            'article_id' => $article->id,
            'type' => 'Perte',
            'quantity' => 1,
            'reason' => 'Emballage percé constaté',
        ])->assertCreated();

        $this->assertDatabaseHas('articles', ['id' => $article->id, 'quantity' => 5]);

        // État de réapprovisionnement (quantité 5 <= seuil 6, cible max 20 -> 15 à commander)
        $replenishment = $this->withHeaders($headers)->getJson('/api/articles-replenishment')
            ->assertOk()
            ->assertJsonPath('success', true);

        $item = collect($replenishment->json('data'))->firstWhere('id', $article->id);
        $this->assertNotNull($item);
        $this->assertSame(15, $item['suggested_order_quantity']);

        // RM-09 : Inventaire avec écart non justifié bloqué à la validation
        $inv = InventorySession::create([
            'reference' => 'INV-2026-00099',
            'scope' => 'Partiel',
            'status' => 'En attente de validation',
            'inventory_date' => now()->toDateString(),
            'created_by' => $manager->id,
        ]);
        $inv->items()->create([
            'article_id' => $article->id,
            'theoretical_quantity' => 5,
            'physical_quantity' => 3,
            'variance' => -2,
            'justification' => null,
        ]);

        $this->withHeaders($headers)
            ->putJson("/api/inventories/{$inv->id}/validate")
            ->assertStatus(422);
    }

    public function test_module_4_and_5_assets_incidents_and_vehicles_alerts_documents(): void
    {
        Storage::fake('local');

        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $employee = User::factory()->create(['role' => User::ROLE_COLLABORATOR, 'service' => 'Comptabilité']);
        $headers = $this->authorizationHeaders($manager);

        // Module 4 : Enregistrement bien durable avec numéro d'inventaire auto PAT-YYYY-xxxxx (RM-13)
        $assetRes = $this->withHeaders($headers)->postJson('/api/assets', [
            'name' => 'Ordinateur Portable Dell Latitude 5440',
            'category' => 'Matériel informatique',
            'serial_number' => 'SN-DELL-5440-99',
            'acquired_at' => '2026-01-15',
            'acquisition_cost' => 650000,
            'department' => 'Informatique',
            'location' => 'Siège Plateau',
            'condition' => 'Neuf',
        ])->assertCreated();

        $assetId = (int) $assetRes->json('data.id');
        $this->assertMatchesRegularExpression('/^PAT-\d{4}-\d{5}$/', (string) $assetRes->json('data.inventory_number'));

        // Remise de matériel à un collaborateur (RM-14)
        $this->withHeaders($headers)->postJson("/api/assets/{$assetId}/assign", [
            'user_id' => $employee->id,
            'department' => 'Comptabilité',
            'location' => 'Bureau 102',
            'notes' => 'Dotation annuelle',
        ])->assertOk()
            ->assertJsonPath('data.availability', 'Affecté');

        // Déclaration d'incident majeur -> État passe à Endommagé
        $incRes = $this->withHeaders($headers)->postJson("/api/assets/{$assetId}/incidents", [
            'severity' => 'Majeur',
            'title' => 'Charnière écran fissurée',
            'description' => 'Choc constaté lors d’un déplacement professionnel.',
        ])->assertCreated();

        $incidentId = (int) $incRes->json('data.id');
        $this->assertMatchesRegularExpression('/^INC-\d{4}-\d{5}$/', (string) $incRes->json('data.reference'));
        $this->assertDatabaseHas('assets', ['id' => $assetId, 'condition' => 'Endommagé']);

        // Résolution de l'incident -> retour à Bon état
        $this->withHeaders($headers)->putJson("/api/asset-incidents/{$incidentId}/resolve", [
            'resolution_notes' => 'Charnière remplacée sous garantie.',
            'asset_condition' => 'Bon état',
        ])->assertOk();

        // Restitution du matériel -> Disponibilité Disponible & historique conservé
        $this->withHeaders($headers)->postJson("/api/assets/{$assetId}/return", [
            'condition' => 'Bon état',
            'location' => 'Magasin Central',
            'notes' => 'Restitution fin de mission',
        ])->assertOk()
            ->assertJsonPath('data.availability', 'Disponible');

        $assetFresh = Asset::with('assignments')->findOrFail($assetId);
        $this->assertCount(2, $assetFresh->assignments);
        $this->assertSame(0, $assetFresh->assignments->whereNull('returned_at')->count());

        // Module 5 : Parc automobile, kilométrage, alerte entretien RM-15 & documents VDOC
        $vehRes = $this->withHeaders($headers)->postJson('/api/vehicles', [
            'registration_number' => 'AA-458-CI',
            'brand' => 'Toyota',
            'model' => 'Hilux Double Cabine',
            'type' => 'Pick-up',
            'registration_card_number' => 'CG-2026-8891',
            'mileage' => 48500,
            'next_maintenance_mileage' => 50000,
            'department' => 'Logistique',
            'driver_id' => $employee->id,
            'status' => 'En service',
        ])->assertCreated();

        $vehicleId = (int) $vehRes->json('data.id');

        // Mise à jour kilométrage (49600 km, à moins de 500 km de l'échéance 50000 km -> has_alert = true)
        $updatedVeh = $this->withHeaders($headers)->putJson("/api/vehicles/{$vehicleId}", [
            'mileage' => 49600,
        ])->assertOk();

        $this->assertTrue((bool) $updatedVeh->json('data.has_alert'));

        // Ajout d'un document administratif véhicule avec fichier
        $vdocRes = $this->withHeaders($headers)->post("/api/vehicles/{$vehicleId}/documents", [
            'document_type' => 'Assurance automobile',
            'issue_date' => '2026-01-01',
            'expires_at' => '2027-01-01',
            'file' => UploadedFile::fake()->create('attestation-assurance.pdf', 150, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertMatchesRegularExpression('/^VDOC-\d{4}-\d{5}$/', (string) $vdocRes->json('data.reference'));

        // Intervention de maintenance sur le véhicule
        $this->withHeaders($headers)->postJson('/api/maintenance-records', [
            'resource_type' => 'vehicle',
            'resource_id' => $vehicleId,
            'type' => 'Vidange & filtres 50 000 km',
            'provider' => 'CFAO Motors',
            'completed_at' => now()->toDateString(),
            'mileage' => 50010,
            'next_maintenance_mileage' => 60000,
            'cost' => 145000,
            'status' => 'Terminée',
            'description' => 'Révision complète 50 000 km effectuée.',
        ])->assertCreated();

        $this->assertDatabaseHas('vehicles', ['id' => $vehicleId, 'mileage' => 50010, 'next_maintenance_mileage' => 60000]);
    }

    public function test_module_6_expenses_rm10_rm11_rm12_and_financial_audit(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $headers = $this->authorizationHeaders($admin);

        // RM-10 : Dépense sans demande ni autorisation directe refusée
        $this->withHeaders($headers)->postJson('/api/expenses', [
            'authorized_amount' => 150000,
            'beneficiary' => 'Prestataire Externe',
            'disbursed_amount' => 150000,
        ])->assertStatus(422);

        // Création avec autorisation directe auto-générée AUT-YYYY-xxxxx + référence DEP-YYYY-xxxxx
        $expRes = $this->withHeaders($headers)->post('/api/expenses', [
            'direct_authorization' => '1',
            'beneficiary' => 'SONATEL Energie',
            'estimated_amount' => 200000,
            'authorized_amount' => 200000,
            'committed_amount' => 195000,
            'disbursed_amount' => 195000,
            'justified_amount' => 195000,
            'payment_method' => 'Virement bancaire',
            'notes' => 'Règlement facture maintenance groupe électrogène',
            'attachment_type' => 'Facture',
            'attachment' => UploadedFile::fake()->create('facture-groupe.pdf', 180, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $expenseId = (int) $expRes->json('data.id');
        $this->assertMatchesRegularExpression('/^DEP-\d{4}-\d{5}$/', (string) $expRes->json('data.reference'));
        $this->assertMatchesRegularExpression('/^AUT-\d{4}-\d{5}$/', (string) $expRes->json('data.authorization_reference'));

        // Contrôle et validation financière -> Statut Validé
        $this->withHeaders($headers)->putJson("/api/expenses/{$expenseId}/validate", [
            'disbursed_amount' => 195000,
            'justified_amount' => 195000,
        ])->assertOk()
            ->assertJsonPath('data.status', 'Validé');

        // RM-12 : Modification silencieuse d'une dépense validée sans motif explicite refusée
        $this->withHeaders($headers)->putJson("/api/expenses/{$expenseId}", [
            'disbursed_amount' => 198000,
        ])->assertStatus(422);

        // Modification avec motif de révision explicite autorisée et tracée dans expense_audits
        $this->withHeaders($headers)->putJson("/api/expenses/{$expenseId}", [
            'disbursed_amount' => 198000,
            'justified_amount' => 198000,
            'amendment_reason' => 'Ajout frais bancaires sur avis de débit.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'Révisé - à revalider');

        $this->assertGreaterThanOrEqual(3, ExpenseAudit::where('expense_id', $expenseId)->count());
    }

    public function test_modules_7_8_9_10_reports_documents_delegations_audit_and_backups(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $delegate = User::factory()->create(['role' => User::ROLE_COLLABORATOR, 'service' => 'Administration Générale']);
        $headers = $this->authorizationHeaders($admin);

        // Module 7 : Génération et archivage d'un rapport hebdomadaire (RAP-YYYY-xxxxx)
        $reportRes = $this->withHeaders($headers)->postJson('/api/reports/generate', [
            'type' => 'Hebdomadaire',
            'difficulties' => 'Retard fournisseur papeterie.',
            'action_items' => 'Relancer le fournisseur secondaire lundi.',
        ])->assertCreated();

        $reportId = (int) $reportRes->json('data.id');
        $this->assertMatchesRegularExpression('/^RAP-\d{4}-\d{5}$/', (string) $reportRes->json('data.reference'));
        $this->assertNotNull(SavedReport::find($reportId));

        // Module 8 : Téléversement d'un document administratif de type Image (DOC-YYYY-xxxxx) et suppression
        $docRes = $this->withHeaders($headers)->post('/api/documents', [
            'title' => 'Scan bordereau de livraison signé',
            'category' => 'Bon de livraison',
            'type' => 'Image',
            'service' => 'Logistique & Moyens Généraux',
            'file' => UploadedFile::fake()->image('bordereau.png'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $docId = (int) $docRes->json('data.id');
        $this->assertSame('Image', $docRes->json('data.type'));
        $this->assertMatchesRegularExpression('/^DOC-\d{4}-\d{5}$/', (string) $docRes->json('data.reference'));

        $this->withHeaders($headers)->deleteJson("/api/documents/{$docId}")
            ->assertOk();
        $this->assertNull(AdministrativeDocument::find($docId));

        // Module 10 : Délégation de validation permettant temporairement à un collaborateur d'accéder aux opérations manager
        $this->withHeaders($headers)->postJson('/api/operations/reference-data/delegations', [
            'delegator_id' => $admin->id,
            'delegate_id' => $delegate->id,
            'scope' => 'Toutes les demandes',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addDays(5)->toDateString(),
            'is_active' => true,
        ])->assertCreated();

        $delegate->refresh();
        $this->assertTrue($delegate->has_active_delegation);
        $this->assertTrue($delegate->canManageOperations());

        // Une délégation de validation n'ouvre pas les autres modules de gestion.
        $this->withHeaders($this->authorizationHeaders($delegate))
            ->getJson('/api/reports')
            ->assertForbidden();

        // Module 9 : Journal d'audit filtré + Sauvegarde (BKP-YYYY-xxxxx) & Restauration des données
        $this->app['auth']->forgetGuards();
        $this->withHeaders($headers)
            ->getJson('/api/activity-logs?module=settings')
            ->assertOk();

        $backupRes = $this->withHeaders($headers)
            ->postJson('/api/backups')
            ->assertCreated();

        $backupId = (int) $backupRes->json('data.id');
        $this->assertMatchesRegularExpression('/^BKP-\d{4}-\d{5}$/', (string) $backupRes->json('data.reference'));
        $backup = SystemBackup::findOrFail($backupId);
        Storage::disk()->assertExists($backup->file_path);

        $this->withHeaders($headers)
            ->postJson('/api/backups/restore', ['backup_id' => $backupId])
            ->assertOk()
            ->assertJsonPath('success', true);

        // Sauvegarde régulière planifiée, exécutable sans session utilisateur.
        $this->artisan('backup:data')->assertSuccessful();
        $automaticBackup = SystemBackup::whereNull('created_by')->latest()->firstOrFail();
        Storage::disk()->assertExists($automaticBackup->file_path);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'backup.created_automatically',
            'subject_id' => $automaticBackup->id,
        ]);
    }
}
