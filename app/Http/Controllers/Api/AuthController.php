<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /**
     * Authentification utilisateur
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            $this->recordActivity($request, 'auth.login_failed', $user, "Tentative de connexion échouée ({$validated['email']}).", [
                'email' => $validated['email'],
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Identifiants incorrects (adresse e-mail ou mot de passe invalide).',
            ], 401);
        }

        if (! $user->is_active) {
            $this->recordActivity($request, 'auth.login_blocked', $user, "Connexion refusée (compte désactivé) pour {$user->name}.", [
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ce compte est désactivé. Contactez un administrateur.',
            ], 403);
        }

        $token = $user->issueApiToken();
        $this->recordActivity($request, 'auth.login', $user, "Connexion de {$user->name}.", [
            'user_agent' => $request->userAgent(),
            'role' => $user->role,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie.',
            'user' => $user->fresh(),
            'token' => $token,
        ]);
    }

    /**
     * Inscription d'un nouvel utilisateur
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'service' => 'required|string',
            'role' => 'nullable|string|in:admin,collaborator,manager,Administrateur,Collaborateur,Responsable',
        ]);

        $isFirstUser = User::count() === 0;
        $role = $isFirstUser ? User::ROLE_ADMIN : User::ROLE_COLLABORATOR;

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $role,
            'permissions' => User::defaultPermissionsForRole($role),
            'service' => $validated['service'],
            'matricule' => null,
            'avatar' => User::defaultAvatar($role),
        ]);

        $user->update([
            'matricule' => 'EMP-'.now()->format('Y').'-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
        ]);

        $this->recordActivity($request, 'user.registered', $user, "Inscription du compte {$user->name} ({$user->matricule}).", [
            'role' => $role,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Bienvenue {$user->name}, votre compte a été créé avec succès.",
            'user' => $user->fresh(),
            'token' => $user->issueApiToken(),
        ], 201);
    }

    /**
     * Utilisateur actuellement connecté
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'user' => $request->user(),
        ]);
    }

    /**
     * Mise à jour du profil et renouvellement du mot de passe de l'utilisateur connecté
     */
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'office_location' => ['nullable', 'string', 'max:255'],
            'current_password' => ['nullable', 'string', 'required_with:new_password'],
            'new_password' => ['nullable', 'string', 'min:6'],
        ]);

        $before = $user->only(['name', 'email', 'phone', 'office_location']);
        $passwordChanged = false;

        if (! empty($validated['new_password'])) {
            if (! Hash::check((string) ($validated['current_password'] ?? ''), $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le mot de passe actuel saisi est incorrect.',
                ], 422);
            }
            $user->password = Hash::make($validated['new_password']);
            $passwordChanged = true;
        }

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? $user->phone,
            'office_location' => $validated['office_location'] ?? $user->office_location,
        ]);
        $user->save();

        $this->recordActivity(
            $request,
            $passwordChanged ? 'auth.password_changed' : 'profile.updated',
            $user,
            $passwordChanged
                ? "Profil et mot de passe renouvelés par {$user->name}."
                : "Informations de profil mises à jour par {$user->name}.",
            [
                'before' => $before,
                'after' => $user->only(['name', 'email', 'phone', 'office_location']),
                'password_changed' => $passwordChanged,
            ],
        );

        return response()->json([
            'success' => true,
            'message' => $passwordChanged
                ? 'Profil et mot de passe mis à jour avec succès.'
                : 'Vos informations de profil ont été mises à jour.',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Demande de réinitialisation de mot de passe transmise aux administrateurs
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user) {
            $this->recordActivity($request, 'auth.password_reset_requested', $user, "Demande de réinitialisation de mot de passe pour {$user->email}.", [
                'reason' => $validated['reason'] ?? null,
            ]);

            User::where('role', User::ROLE_ADMIN)->where('is_active', true)->pluck('id')->each(
                fn (int $adminId) => $this->sendSystemNotification(
                    $adminId,
                    'auth.password_reset_requested',
                    'Réinitialisation de mot de passe demandée',
                    "L'utilisateur {$user->name} ({$user->email}) demande une réinitialisation de son mot de passe.",
                    '#users',
                ),
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Votre demande de réinitialisation a été transmise à l’administrateur.',
        ]);
    }

    /**
     * Historique des connexions de l'utilisateur connecté
     */
    public function myLoginHistory(Request $request): JsonResponse
    {
        $logs = ActivityLog::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('action', ['auth.login', 'auth.logout', 'auth.login_failed', 'auth.password_changed'])
            ->latest()
            ->limit(30)
            ->get();

        return response()->json([
            'success' => true,
            'count' => $logs->count(),
            'data' => $logs,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        if ($request->user()) {
            $this->recordActivity($request, 'auth.logout', $request->user(), "Déconnexion de {$request->user()->name}.");
            $request->user()->revokeApiToken();
        }

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie.',
        ]);
    }
}
