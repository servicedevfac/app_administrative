<?php

namespace Tests\Feature;

use App\Models\AdministrativeDocument;
use App\Models\Article;
use App\Models\Expense;
use App\Models\InventorySession;
use App\Models\MaterialRequest;
use App\Models\RequestDecision;
use App\Models\RequestItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExampleTest extends TestCase
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

    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_article_codes_are_generated_automatically(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Article::create([
            'code' => 'ART-008',
            'name' => 'Article existant',
            'category' => 'Fournitures',
            'unit' => 'Pièce',
            'quantity' => 1,
            'threshold' => 1,
            'status' => Article::STATUS_LOW,
            'is_available' => true,
            'created_by' => $admin->id,
        ]);

        $response = $this->withHeaders($this->authorizationHeaders($admin))->postJson('/api/articles', [
            'name' => 'Nouvel article',
            'category' => 'Fournitures',
            'unit' => 'Pièce',
            'quantity' => 2,
            'threshold' => 1,
            'location' => 'Rayon B3',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'ART-009');
        $this->assertDatabaseHas('articles', [
            'code' => 'ART-009',
            'name' => 'Nouvel article',
            'location' => 'Rayon B3',
        ]);
    }

    public function test_administrator_can_update_an_article_and_its_stock_state(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $article = Article::create([
            'code' => 'ART-010',
            'name' => 'Ancienne désignation',
            'category' => 'Fournitures',
            'unit' => 'Pièce',
            'quantity' => 8,
            'threshold' => 2,
            'status' => Article::STATUS_AVAILABLE,
            'is_available' => true,
            'created_by' => $admin->id,
        ]);

        $response = $this->withHeaders($this->authorizationHeaders($admin))->putJson("/api/articles/{$article->id}", [
            'name' => 'Nouvelle désignation',
            'category' => 'Fournitures',
            'unit' => 'Pièce',
            'quantity' => 1,
            'threshold' => 3,
            'location' => 'Rayon C2',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Nouvelle désignation')
            ->assertJsonPath('data.status', Article::STATUS_LOW);
        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'name' => 'Nouvelle désignation',
            'quantity' => 1,
            'threshold' => 3,
            'status' => Article::STATUS_LOW,
            'location' => 'Rayon C2',
        ]);
    }

    public function test_returning_material_restores_quantity_and_stock_state(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);
        $article = Article::create([
            'code' => 'ART-001',
            'name' => 'Ordinateur portable',
            'category' => 'Informatique',
            'unit' => 'Pièce',
            'quantity' => 1,
            'threshold' => 2,
            'status' => Article::STATUS_LOW,
            'is_available' => true,
            'created_by' => $admin->id,
        ]);
        $materialRequest = MaterialRequest::create([
            'reference' => 'DEM-2026-00001',
            'user_id' => $collaborator->id,
            'type_operation' => 'Emprunt temporaire',
            'motif' => 'Mission',
            'service' => 'Informatique',
            'urgency' => 'Normale',
            'status' => 'Approuvée',
            'approved_by' => $admin->id,
        ]);
        RequestItem::create([
            'material_request_id' => $materialRequest->id,
            'article_id' => $article->id,
            'quantity_requested' => 3,
            'quantity_approved' => 2,
        ]);

        $response = $this->withHeaders($this->authorizationHeaders($admin))
            ->putJson("/api/requests/{$materialRequest->id}/return");

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'quantity' => 3,
            'status' => Article::STATUS_AVAILABLE,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'article_id' => $article->id,
            'user_id' => $admin->id,
            'material_request_id' => $materialRequest->id,
            'type' => 'Entrée',
            'source' => StockMovement::SOURCE_REQUEST_RETURN,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('material_requests', [
            'id' => $materialRequest->id,
            'status' => 'Restituée',
        ]);
    }

    public function test_request_cannot_be_approved_when_stock_is_insufficient(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);
        $article = Article::create([
            'code' => 'ART-002',
            'name' => 'Écran',
            'category' => 'Informatique',
            'unit' => 'Pièce',
            'quantity' => 1,
            'threshold' => 1,
            'status' => Article::STATUS_LOW,
            'is_available' => true,
            'created_by' => $admin->id,
        ]);
        $materialRequest = MaterialRequest::create([
            'reference' => 'DEM-2026-00002',
            'user_id' => $collaborator->id,
            'type_operation' => 'Emprunt temporaire',
            'motif' => 'Télétravail',
            'service' => 'Informatique',
            'urgency' => 'Normale',
            'status' => 'En attente',
        ]);
        RequestItem::create([
            'material_request_id' => $materialRequest->id,
            'article_id' => $article->id,
            'quantity_requested' => 2,
            'quantity_approved' => 0,
        ]);

        $response = $this->withHeaders($this->authorizationHeaders($admin))
            ->putJson("/api/requests/{$materialRequest->id}/approve");

        $response->assertStatus(422)->assertJsonPath('success', false);
        $this->assertDatabaseHas('articles', ['id' => $article->id, 'quantity' => 1]);
        $this->assertDatabaseHas('material_requests', [
            'id' => $materialRequest->id,
            'status' => 'En attente',
        ]);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_public_registration_cannot_create_another_administrator(): void
    {
        User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Nouvel utilisateur',
            'email' => 'nouvel.utilisateur@example.test',
            'password' => 'secret123',
            'service' => 'Finance',
            'role' => 'admin',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', User::ROLE_COLLABORATOR);
        $this->assertDatabaseHas('users', [
            'email' => 'nouvel.utilisateur@example.test',
            'role' => User::ROLE_COLLABORATOR,
        ]);
    }

    public function test_administrator_can_record_a_manual_stock_adjustment(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $article = Article::create([
            'code' => 'ART-003',
            'name' => 'Clavier',
            'category' => 'Informatique',
            'unit' => 'Pièce',
            'quantity' => 4,
            'threshold' => 2,
            'status' => Article::STATUS_AVAILABLE,
            'is_available' => true,
            'created_by' => $admin->id,
        ]);

        $response = $this->withHeaders($this->authorizationHeaders($admin))->postJson('/api/movements', [
            'article_id' => $article->id,
            'type' => 'Entrée',
            'quantity' => 3,
            'reason' => 'Livraison fournisseur',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.source', StockMovement::SOURCE_MANUAL_ADJUSTMENT);
        $this->assertDatabaseHas('articles', ['id' => $article->id, 'quantity' => 7]);
        $this->assertDatabaseHas('stock_movements', [
            'article_id' => $article->id,
            'material_request_id' => null,
            'source' => StockMovement::SOURCE_MANUAL_ADJUSTMENT,
            'quantity' => 3,
        ]);
    }

    public function test_collaborator_cannot_adjust_stock_directly(): void
    {
        $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);
        $article = Article::create([
            'code' => 'ART-004',
            'name' => 'Souris',
            'category' => 'Informatique',
            'unit' => 'Pièce',
            'quantity' => 4,
            'threshold' => 2,
            'status' => Article::STATUS_AVAILABLE,
            'is_available' => true,
        ]);

        $response = $this->withHeaders($this->authorizationHeaders($collaborator))->postJson('/api/movements', [
            'article_id' => $article->id,
            'type' => 'Sortie',
            'quantity' => 1,
            'reason' => 'Distribution directe',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('articles', ['id' => $article->id, 'quantity' => 4]);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_private_api_rejects_an_unauthenticated_request(): void
    {
        $this->getJson('/api/articles')->assertUnauthorized();
    }

    public function test_collaborator_cannot_access_administration_routes(): void
    {
        $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);

        $this->withHeaders($this->authorizationHeaders($collaborator))
            ->getJson('/api/users')
            ->assertForbidden();
    }

    public function test_administrator_can_store_and_download_a_real_document(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $headers = $this->authorizationHeaders($admin);

        $response = $this->withHeaders($headers)->post('/api/documents', [
            'title' => 'Procédure achats',
            'category' => 'Procédures',
            'type' => 'PDF',
            'service' => 'Administration',
            'reference' => 'DOC-2026-001',
            'file' => UploadedFile::fake()->create('procedure.pdf', 48, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonPath('success', true);
        $document = AdministrativeDocument::query()->firstOrFail();
        Storage::disk('local')->assertExists($document->file_path);

        $this->withHeaders($headers)
            ->get("/api/documents/{$document->id}/download")
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_request_workflow_records_each_decision(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);
        $article = Article::create([
            'code' => 'ART-005',
            'name' => 'Bloc-notes',
            'category' => 'Papeterie',
            'unit' => 'Pièce',
            'quantity' => 10,
            'threshold' => 2,
            'status' => Article::STATUS_AVAILABLE,
            'is_available' => true,
            'created_by' => $admin->id,
        ]);

        $created = $this->withHeaders($this->authorizationHeaders($collaborator))->postJson('/api/requests', [
            'motif' => 'Réunion',
            'service' => 'Direction',
            'save_as_draft' => true,
            'items' => [['article_id' => $article->id, 'quantity' => 2]],
        ])->assertCreated();

        $requestId = $created->json('data.id');
        $this->withHeaders($this->authorizationHeaders($collaborator))
            ->putJson("/api/requests/{$requestId}/submit")
            ->assertOk();
        $this->withHeaders($this->authorizationHeaders($admin))
            ->putJson("/api/requests/{$requestId}/request-correction", ['reason' => 'Préciser le motif.'])
            ->assertOk();
        $this->withHeaders($this->authorizationHeaders($collaborator))
            ->putJson("/api/requests/{$requestId}/cancel", ['reason' => 'Besoin annulé'])
            ->assertOk();

        $this->assertSame(4, RequestDecision::query()->where('material_request_id', $requestId)->count());
        $this->assertDatabaseHas('material_requests', [
            'id' => $requestId,
            'status' => MaterialRequest::STATUS_CANCELLED,
        ]);
    }

    public function test_requester_can_modify_their_submitted_request(): void
    {
        $requester = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);
        $otherUser = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $articleA = Article::create([
            'code' => 'ART-MOD-A',
            'name' => 'Clavier Standard',
            'category' => 'Informatique',
            'unit' => 'Pièce',
            'quantity' => 10,
            'threshold' => 2,
            'status' => Article::STATUS_AVAILABLE,
            'is_available' => true,
            'created_by' => $admin->id,
        ]);
        $articleB = Article::create([
            'code' => 'ART-MOD-B',
            'name' => 'Souris Ergonomique',
            'category' => 'Informatique',
            'unit' => 'Pièce',
            'quantity' => 10,
            'threshold' => 2,
            'status' => Article::STATUS_AVAILABLE,
            'is_available' => true,
            'created_by' => $admin->id,
        ]);

        $created = $this->withHeaders($this->authorizationHeaders($requester))->postJson('/api/requests', [
            'motif' => 'Besoin initial',
            'service' => 'Finance',
            'urgency' => 'Normale',
            'items' => [['article_id' => $articleA->id, 'quantity' => 2]],
        ])->assertCreated();

        $requestId = $created->json('data.id');

        // Another user cannot modify it
        $this->withHeaders($this->authorizationHeaders($otherUser))
            ->putJson("/api/requests/{$requestId}", [
                'motif' => 'Piratage',
            ])->assertStatus(403);

        // The requester CAN modify their submitted request
        $this->withHeaders($this->authorizationHeaders($requester))
            ->putJson("/api/requests/{$requestId}", [
                'motif' => 'Besoin révisé et urgent',
                'urgency' => 'Urgente',
                'service' => 'Direction Financière',
                'items' => [['article_id' => $articleB->id, 'quantity' => 5]],
            ])->assertOk();

        $this->assertDatabaseHas('material_requests', [
            'id' => $requestId,
            'motif' => 'Besoin révisé et urgent',
            'urgency' => 'Urgente',
            'service' => 'Direction Financière',
        ]);

        $this->assertDatabaseHas('request_items', [
            'material_request_id' => $requestId,
            'article_id' => $articleB->id,
            'quantity_requested' => 5,
        ]);
    }

    public function test_administrator_can_export_requests_as_csv(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->withHeaders($this->authorizationHeaders($admin))
            ->get('/api/requests-export')
            ->assertOk()
            ->assertDownload();
    }

    public function test_manager_can_approve_a_request_and_stock_is_traced(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $employee = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);
        $article = Article::create([
            'code' => 'ART-020', 'name' => 'Ramette A4', 'category' => 'Papeterie', 'unit' => 'Rame',
            'quantity' => 10, 'threshold' => 2, 'status' => Article::STATUS_AVAILABLE, 'is_available' => true,
        ]);
        $materialRequest = MaterialRequest::create([
            'reference' => 'DEM-2026-00020', 'user_id' => $employee->id, 'motif' => 'Réunion',
            'service' => 'Direction', 'status' => MaterialRequest::STATUS_PENDING,
        ]);
        RequestItem::create(['material_request_id' => $materialRequest->id, 'article_id' => $article->id, 'quantity_requested' => 3]);

        $this->withHeaders($this->authorizationHeaders($manager))
            ->putJson("/api/requests/{$materialRequest->id}/approve")
            ->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('articles', ['id' => $article->id, 'quantity' => 7]);
        $this->assertDatabaseHas('stock_movements', ['material_request_id' => $materialRequest->id, 'quantity' => 3]);
    }

    public function test_expense_over_authorized_amount_requires_new_validation(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $employee = User::factory()->create();
        $materialRequest = MaterialRequest::create([
            'reference' => 'DEM-2026-00021', 'user_id' => $employee->id, 'motif' => 'Entretien',
            'service' => 'Logistique', 'status' => MaterialRequest::STATUS_APPROVED,
        ]);

        $this->withHeaders($this->authorizationHeaders($admin))->postJson('/api/expenses', [
            'material_request_id' => $materialRequest->id,
            'authorized_amount' => 100000,
            'committed_amount' => 125000,
            'disbursed_amount' => 0,
        ])->assertCreated()->assertJsonPath('data.status', 'Nouvelle validation requise');

        $expense = Expense::firstOrFail();
        $this->assertTrue($expense->requires_reapproval);
    }

    public function test_validated_inventory_adjusts_stock_with_a_traced_movement(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $article = Article::create([
            'code' => 'ART-021', 'name' => 'Cartouche', 'category' => 'Informatique', 'unit' => 'Pièce',
            'quantity' => 8, 'threshold' => 2, 'status' => Article::STATUS_AVAILABLE, 'is_available' => true,
        ]);
        $headers = $this->authorizationHeaders($admin);
        $response = $this->withHeaders($headers)->postJson('/api/inventories', [
            'scope' => 'Partiel', 'inventory_date' => '2026-11-04',
            'items' => [['article_id' => $article->id, 'physical_quantity' => 6, 'justification' => 'Deux unités endommagées']],
        ])->assertCreated();
        $inventoryId = $response->json('data.id');

        $this->withHeaders($headers)->putJson("/api/inventories/{$inventoryId}/validate")
            ->assertOk()->assertJsonPath('data.status', 'Validé');

        $this->assertDatabaseHas('articles', ['id' => $article->id, 'quantity' => 6]);
        $this->assertDatabaseHas('stock_movements', ['article_id' => $article->id, 'source' => StockMovement::SOURCE_INVENTORY_ADJUSTMENT, 'quantity' => 2]);
        $this->assertSame('Validé', InventorySession::findOrFail($inventoryId)->status);
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['password' => 'secret123', 'is_active' => false]);

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertForbidden()->assertJsonPath('success', false);
    }
}
