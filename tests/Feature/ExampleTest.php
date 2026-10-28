<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\MaterialRequest;
use App\Models\RequestItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
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

        $response = $this->putJson("/api/requests/{$materialRequest->id}/return", [
            'user_id' => $admin->id,
        ]);

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

        $response = $this->putJson("/api/requests/{$materialRequest->id}/approve", [
            'approved_by' => $admin->id,
        ]);

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

        $response = $this->postJson('/api/movements', [
            'article_id' => $article->id,
            'user_id' => $admin->id,
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

        $response = $this->postJson('/api/movements', [
            'article_id' => $article->id,
            'user_id' => $collaborator->id,
            'type' => 'Sortie',
            'quantity' => 1,
            'reason' => 'Distribution directe',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('articles', ['id' => $article->id, 'quantity' => 4]);
        $this->assertDatabaseCount('stock_movements', 0);
    }
}
