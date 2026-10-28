<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Liste des utilisateurs
     */
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('service')) {
            $query->where('service', $request->service);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
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
        ]);
    }

    /**
     * Création d'un nouvel utilisateur (Admin / Collaborateur / Responsable)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|string|in:admin,collaborator,manager,Administrateur,Collaborateur,Responsable',
            'service' => 'required|string|max:255',
            'matricule' => 'nullable|string|max:100',
        ]);

        $role = User::normalizeRole($validated['role']);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $role,
            'service' => $validated['service'],
            'matricule' => $validated['matricule'] ?? ('EMP-2025-'.rand(100, 999)),
            'avatar' => User::defaultAvatar($role),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Utilisateur \"{$user->name}\" créé avec succès.",
            'data' => $user,
        ], 201);
    }

    /**
     * Détails d'un utilisateur
     */
    public function show($id)
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
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => "sometimes|required|email|unique:users,email,{$id}",
            'password' => 'nullable|string|min:6',
            'role' => 'sometimes|required|string',
            'service' => 'sometimes|required|string|max:255',
            'matricule' => 'nullable|string|max:100',
        ]);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        if (isset($validated['role'])) {
            $validated['role'] = User::normalizeRole($validated['role']);
            $validated['avatar'] = User::defaultAvatar($validated['role']);
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => "Utilisateur \"{$user->name}\" mis à jour.",
            'data' => $user,
        ]);
    }

    /**
     * Suppression d'un utilisateur (Sécurisé)
     */
    public function destroy(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // 1. Interdire la suppression de son propre compte connecté
        $currentUserId = $request->header('X-User-Id') ?? $request->input('current_user_id');
        if ($currentUserId && (int) $currentUserId === (int) $id) {
            return response()->json([
                'success' => false,
                'message' => 'Action interdite : vous ne pouvez pas supprimer votre propre compte Administrateur actif.',
            ], 403);
        }

        // 2. Interdire la suppression du dernier compte Administrateur
        if ($user->role === User::ROLE_ADMIN) {
            $adminCount = User::where('role', User::ROLE_ADMIN)->count();
            if ($adminCount <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Action interdite : impossible de supprimer le dernier compte Administrateur du système.',
                ], 403);
            }
        }

        $userName = $user->name;
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => "Utilisateur \"{$userName}\" supprimé avec succès.",
        ]);
    }
}
