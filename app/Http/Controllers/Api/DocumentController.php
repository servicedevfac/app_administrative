<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdministrativeDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    /**
     * Liste des documents administratifs et bons archivés
     */
    public function index(Request $request)
    {
        // Les collaborateurs n'ont pas accès aux documents administratifs
        if ($request->user() && $request->user()->role === 'collaborator') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé : la consultation des documents administratifs est réservée aux gestionnaires.',
                'data' => [],
            ], 403);
        }

        $query = AdministrativeDocument::with(['user', 'materialRequest']);

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('service')) {
            $query->where('service', $request->service);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('service', 'like', "%{$search}%");
            });
        }

        $documents = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'count' => $documents->count(),
            'data' => $documents,
        ]);
    }

    /**
     * Enregistrer / téléverser un document administratif
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string',
            'type' => 'required|in:PDF,Word,Excel',
            'service' => 'required|string',
            'reference' => 'nullable|string',
        ]);

        $doc = AdministrativeDocument::create(array_merge($validated, [
            'file_path' => 'documents/archives/'.Str::slug($validated['title']).'.'.strtolower($validated['type']),
            'file_size' => '240 Ko',
            'user_id' => $this->resolveActorId($request),
        ]));

        return response()->json([
            'success' => true,
            'message' => "Document \"{$doc->title}\" archivé avec succès.",
            'data' => $doc,
        ], 201);
    }
}
