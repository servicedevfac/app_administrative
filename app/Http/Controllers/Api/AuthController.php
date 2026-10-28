<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Authentification utilisateur
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Identifiants incorrects (adresse e-mail ou mot de passe invalide).',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie.',
            'user' => $user,
        ]);
    }

    /**
     * Inscription d'un nouvel utilisateur
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'service' => 'required|string',
            'matricule' => 'nullable|string',
            'role' => 'nullable|string|in:admin,collaborator,Administrateur,Collaborateur',
        ]);

        // Si la base ne contient aucun utilisateur, le premier compte devient obligatoirement Administrateur
        $isFirstUser = User::count() === 0;
        $role = $isFirstUser
            ? User::ROLE_ADMIN
            : User::ROLE_COLLABORATOR;

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $role,
            'service' => $validated['service'],
            'matricule' => $validated['matricule'] ?? ('EMP-2025-'.rand(100, 999)),
            'avatar' => User::defaultAvatar($role),
        ]);

        $roleLabel = $role === User::ROLE_ADMIN ? 'Administrateur' : 'Collaborateur';

        return response()->json([
            'success' => true,
            'message' => "Compte {$roleLabel} créé avec succès pour {$user->name}.",
            'user' => $user,
        ], 201);
    }

    /**
     * Utilisateur actuellement connecté
     */
    public function me(Request $request)
    {
        $user = $request->user() ?? User::first();

        return response()->json([
            'success' => true,
            'user' => $user,
        ]);
    }
}
