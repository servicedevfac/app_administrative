<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\MaterialRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Liste des utilisateurs
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', User::normalizeRole($request->string('role')->toString()));
        }

        if ($request->filled('service')) {
            $query->where('service', $request->service);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('service', 'like', "%{$search}%")
                    ->orWhere('matricule', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'count' => $users->count(),
            'data' => $users,
            'available_permissions' => User::ALL_PERMISSIONS,
        ]);
    }

    /**
     * Création d'un nouvel utilisateur (Admin / Collaborateur / Responsable)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|string|in:admin,collaborator,manager,Administrateur,Collaborateur,Employé,Responsable',
            'service' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'office_location' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in(User::ALL_PERMISSIONS)],
        ]);

        $role = User::normalizeRole($validated['role']);
        $permissions = isset($validated['permissions'])
            ? array_values(array_unique($validated['permissions']))
            : User::defaultPermissionsForRole($role);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $role,
            'permissions' => $permissions,
            'service' => $validated['service'],
            'phone' => $validated['phone'] ?? null,
            'office_location' => $validated['office_location'] ?? null,
            'matricule' => null,
            'avatar' => User::defaultAvatar($role),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $user->update([
            'matricule' => 'EMP-'.now()->format('Y').'-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
        ]);

        $this->recordActivity($request, 'user.created', $user, "Utilisateur {$user->name} ({$user->matricule}) créé.", [
            'role' => $role,
            'service' => $user->service,
            'permissions' => $permissions,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Utilisateur \"{$user->name}\" créé avec succès.",
            'data' => $user->fresh(),
        ], 201);
    }

    /**
     * Détails d'un utilisateur
     */
    public function show(string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    /**
     * Mise à jour d'un utilisateur
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $before = $user->only(['name', 'email', 'role', 'service', 'phone', 'office_location', 'is_active', 'permissions']);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => "sometimes|required|email|unique:users,email,{$id}",
            'password' => 'nullable|string|min:6',
            'role' => 'sometimes|required|string',
            'service' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'office_location' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in(User::ALL_PERMISSIONS)],
        ]);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        if (isset($validated['role'])) {
            $newRole = User::normalizeRole($validated['role']);
            if ($user->isAdmin() && $newRole !== User::ROLE_ADMIN && User::where('role', User::ROLE_ADMIN)->count() <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de retirer le rôle Administrateur au dernier administrateur du système.',
                ], 422);
            }
            $validated['role'] = $newRole;
            $validated['avatar'] = User::defaultAvatar($newRole);
            if (! array_key_exists('permissions', $validated) && $newRole !== $user->role) {
                $validated['permissions'] = User::defaultPermissionsForRole($newRole);
            }
        }

        if (array_key_exists('is_active', $validated)) {
            if (! $validated['is_active'] && $request->user()?->is($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas désactiver votre propre compte.',
                ], 403);
            }
            $user->deactivated_at = $validated['is_active'] ? null : now();
            if (! $validated['is_active']) {
                $user->api_token_hash = null;
            }
        }

        $user->fill($validated);
        $user->save();

        $this->recordActivity($request, 'user.updated', $user, "Utilisateur {$user->name} mis à jour.", [
            'before' => $before,
            'after' => $user->only(['name', 'email', 'role', 'service', 'phone', 'office_location', 'is_active', 'permissions']),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Utilisateur \"{$user->name}\" mis à jour.",
            'data' => $user->fresh(),
        ]);
    }

    /**
     * Validation explicite du profil et du rôle par un administrateur.
     */
    public function updateRole(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in([
                User::ROLE_ADMIN,
                User::ROLE_MANAGER,
                User::ROLE_COLLABORATOR,
                'Administrateur',
                'Responsable',
                'Collaborateur',
            ])],
        ]);

        $previousRole = $user->role;
        $newRole = User::normalizeRole($validated['role']);

        if ($user->isAdmin() && $newRole !== User::ROLE_ADMIN && User::where('role', User::ROLE_ADMIN)->count() <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de retirer le rôle Administrateur au dernier administrateur du système.',
            ], 422);
        }

        $user->update([
            'role' => $newRole,
            'permissions' => User::defaultPermissionsForRole($newRole),
            'avatar' => User::defaultAvatar($newRole),
        ]);

        $routedRequestCount = 0;
        if ($newRole === User::ROLE_MANAGER && $user->is_active) {
            $pendingRequests = MaterialRequest::query()
                ->whereIn('status', MaterialRequest::PENDING_WORKFLOW_STATUSES)
                ->where('service', $user->service)
                ->whereNull('assigned_to')
                ->get();

            foreach ($pendingRequests as $pendingRequest) {
                $pendingRequest->update(['assigned_to' => $user->id]);
                $this->sendSystemNotification(
                    $user->id,
                    'request.submitted',
                    'Demande de votre département à traiter',
                    "La demande {$pendingRequest->reference} vous a été transmise après validation de votre profil Responsable.",
                    "#request-detail/{$pendingRequest->reference}",
                    'notify_on_request_submitted',
                );
            }

            $routedRequestCount = $pendingRequests->count();
        }

        $this->recordActivity($request, 'user.role_validated', $user, "Profil et rôle de {$user->name} validés par l’administrateur.", [
            'previous_role' => $previousRole,
            'new_role' => $newRole,
            'routed_request_count' => $routedRequestCount,
        ]);
        $this->sendSystemNotification(
            $user->id,
            'user.role_validated',
            'Votre profil a été validé',
            'Votre profil est désormais configuré avec le rôle '.($newRole === User::ROLE_MANAGER ? 'Responsable' : ($newRole === User::ROLE_ADMIN ? 'Administrateur' : 'Collaborateur')).'.',
            '#profile',
        );

        return response()->json([
            'success' => true,
            'message' => "Le profil et le rôle de {$user->name} ont été validés.",
            'data' => $user->fresh(),
            'routed_request_count' => $routedRequestCount,
        ]);
    }

    /**
     * Réinitialisation du mot de passe d'un utilisateur par l'Administrateur
     */
    public function resetPassword(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'api_token_hash' => $request->user()?->is($user) ? $user->api_token_hash : null,
        ])->save();

        $this->recordActivity($request, 'user.password_reset', $user, "Mot de passe réinitialisé pour {$user->name} par {$request->user()?->name}.");
        $this->sendSystemNotification(
            $user->id,
            'user.password_reset',
            'Mot de passe réinitialisé',
            'Votre mot de passe a été réinitialisé par un administrateur. Pensez à le modifier depuis votre profil.',
            '#profile',
        );

        return response()->json([
            'success' => true,
            'message' => "Mot de passe de \"{$user->name}\" réinitialisé avec succès.",
        ]);
    }

    /**
     * Historique global des connexions des utilisateurs
     */
    public function loginHistory(Request $request): JsonResponse
    {
        $query = ActivityLog::query()
            ->with('user:id,name,email,role,service,matricule')
            ->whereIn('action', ['auth.login', 'auth.logout', 'auth.login_failed', 'auth.login_blocked', 'auth.password_changed', 'user.password_reset']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($sub) use ($search): void {
                $sub->where('description', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $logs = $query->latest()->limit(150)->get();

        return response()->json([
            'success' => true,
            'count' => $logs->count(),
            'data' => $logs,
        ]);
    }

    /**
     * Suppression d'un utilisateur (Sécurisé - empêche la suppression si opérations historisées)
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($request->user()?->is($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Action interdite : vous ne pouvez pas supprimer votre propre compte Administrateur actif.',
            ], 403);
        }

        if ($user->role === User::ROLE_ADMIN && User::where('role', User::ROLE_ADMIN)->count() <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'Action interdite : impossible de supprimer le dernier compte Administrateur du système.',
            ], 403);
        }

        if ($user->materialRequests()->exists() || $user->stockMovements()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cet utilisateur est rattaché à des opérations historisées. Désactivez son compte pour préserver la traçabilité.',
            ], 422);
        }

        $userName = $user->name;
        $this->recordActivity($request, 'user.deleted', $user, "Utilisateur {$userName} supprimé.");
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => "Utilisateur \"{$userName}\" supprimé avec succès.",
        ]);
    }

    public function toggleActive(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        if ($request->user()?->is($user)) {
            return response()->json(['success' => false, 'message' => 'Vous ne pouvez pas désactiver votre propre compte.'], 403);
        }
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);
        $user->forceFill([
            'is_active' => $validated['is_active'],
            'deactivated_at' => $validated['is_active'] ? null : now(),
            'api_token_hash' => $validated['is_active'] ? $user->api_token_hash : null,
        ])->save();
        $this->recordActivity(
            $request,
            $validated['is_active'] ? 'user.activated' : 'user.deactivated',
            $user,
            $validated['is_active'] ? "Compte {$user->name} activé." : "Compte {$user->name} désactivé (session révoquée).",
        );

        return response()->json([
            'success' => true,
            'message' => $validated['is_active'] ? 'Compte activé.' : 'Compte désactivé immédiatement.',
            'data' => $user->fresh(),
        ]);
    }
}
