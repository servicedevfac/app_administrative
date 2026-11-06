<?php

namespace Tests\Feature;

use App\Models\ApprovalDelegation;
use App\Models\MaterialRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function authorizationHeaders(User $user): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$user->issueApiToken()];
    }

    public function test_a_manager_cannot_use_a_module_removed_from_their_permissions(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
            'permissions' => ['reports.view'],
        ]);

        $headers = $this->authorizationHeaders($manager);

        $this->withHeaders($headers)
            ->getJson('/api/reports')
            ->assertOk();

        $this->withHeaders($headers)
            ->postJson('/api/articles', [
                'name' => 'Article non autorisé',
                'category' => 'Papeterie',
                'unit' => 'Pièce',
                'quantity' => 1,
                'threshold' => 1,
            ])
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_a_validation_delegation_only_grants_validation_permissions(): void
    {
        $delegator = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);
        $delegate = User::factory()->create([
            'role' => User::ROLE_COLLABORATOR,
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole(User::ROLE_COLLABORATOR),
        ]);
        $requester = User::factory()->create([
            'role' => User::ROLE_COLLABORATOR,
            'is_active' => true,
        ]);

        ApprovalDelegation::create([
            'delegator_id' => $delegator->id,
            'delegate_id' => $delegate->id,
            'scope' => 'Toutes les demandes',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addWeek()->toDateString(),
            'is_active' => true,
        ]);

        $materialRequest = MaterialRequest::create([
            'reference' => 'DEM-'.now()->format('Y').'-00999',
            'user_id' => $requester->id,
            'type_operation' => 'Dotation permanente',
            'category' => 'Prestations de services',
            'motif' => 'Vérification des droits délégués',
            'service_description' => 'Prestation sans mouvement de stock.',
            'service' => 'Informatique',
            'urgency' => 'Normale',
            'status' => MaterialRequest::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $headers = $this->authorizationHeaders($delegate);

        $this->withHeaders($headers)
            ->putJson("/api/requests/{$materialRequest->id}/verify", [
                'next_status' => MaterialRequest::STATUS_PENDING_VALIDATION,
                'comment' => 'Disponibilité et pertinence vérifiées.',
            ])
            ->assertOk();

        $this->withHeaders($headers)
            ->getJson('/api/reports')
            ->assertForbidden();

        $this->withHeaders($headers)
            ->getJson('/api/assets')
            ->assertForbidden();
    }

    public function test_asset_permission_cannot_be_used_to_manage_vehicle_maintenance(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
            'permissions' => ['assets.manage'],
        ]);

        $this->withHeaders($this->authorizationHeaders($manager))
            ->postJson('/api/maintenance-records', [
                'resource_type' => 'vehicle',
                'resource_id' => 999,
                'type' => 'Entretien périodique',
                'status' => 'Planifiée',
                'description' => 'Cette opération ne doit pas être autorisée.',
            ])
            ->assertForbidden();
    }
}
