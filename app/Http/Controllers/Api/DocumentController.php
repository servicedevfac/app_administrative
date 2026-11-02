<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdministrativeDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    /**
     * Liste des documents administratifs et bons archivés
     */
    public function index(Request $request): JsonResponse
    {
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
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string',
            'type' => 'required|in:PDF,Word,Excel',
            'service' => 'required|string',
            'reference' => 'nullable|string',
            'file' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240',
        ]);

        $file = $request->file('file');
        $storedName = Str::uuid().'.'.$file->extension();
        $storedPath = $file->storeAs('documents/archives', $storedName, 'local');
        $fileSize = $file->getSize();

        $doc = AdministrativeDocument::create([
            'title' => $validated['title'],
            'category' => $validated['category'],
            'type' => $validated['type'],
            'service' => $validated['service'],
            'reference' => $validated['reference'] ?? null,
            'file_path' => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size_bytes' => $fileSize,
            'file_size' => $this->formatFileSize($fileSize),
            'user_id' => $this->resolveActorId($request),
        ]);

        $this->recordActivity($request, 'document.uploaded', $doc, "Document {$doc->title} déposé.");

        return response()->json([
            'success' => true,
            'message' => "Document \"{$doc->title}\" archivé avec succès.",
            'data' => $doc,
        ], 201);
    }

    public function download(Request $request, AdministrativeDocument $document): StreamedResponse
    {
        abort_unless($document->file_path && Storage::disk('local')->exists($document->file_path), 404, 'Fichier introuvable.');

        $this->recordActivity($request, 'document.downloaded', $document, "Document {$document->title} téléchargé.");

        return Storage::disk('local')->download(
            $document->file_path,
            $document->original_name ?? Str::slug($document->title).'.'.strtolower($document->type),
        );
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1_048_576) {
            return number_format($bytes / 1_048_576, 2, ',', ' ').' Mo';
        }

        return number_format($bytes / 1024, 0, ',', ' ').' Ko';
    }
}
