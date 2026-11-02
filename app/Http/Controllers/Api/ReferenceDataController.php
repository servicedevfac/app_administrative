<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArticleCategory;
use App\Models\MeasurementUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReferenceDataController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'categories' => ArticleCategory::query()->orderBy('name')->get(),
            'units' => MeasurementUnit::query()->orderBy('name')->get(),
        ]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:article_categories,name'],
        ]);

        $category = ArticleCategory::create($validated);
        $this->recordActivity($request, 'category.created', $category, "Catégorie {$category->name} créée.");

        return response()->json(['success' => true, 'data' => $category], 201);
    }

    public function updateCategory(Request $request, ArticleCategory $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('article_categories')->ignore($category)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category->update($validated);
        $this->recordActivity($request, 'category.updated', $category, "Catégorie {$category->name} mise à jour.");

        return response()->json(['success' => true, 'data' => $category]);
    }

    public function storeUnit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:measurement_units,name'],
            'symbol' => ['nullable', 'string', 'max:20'],
        ]);

        $unit = MeasurementUnit::create($validated);
        $this->recordActivity($request, 'unit.created', $unit, "Unité {$unit->name} créée.");

        return response()->json(['success' => true, 'data' => $unit], 201);
    }

    public function updateUnit(Request $request, MeasurementUnit $unit): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('measurement_units')->ignore($unit)],
            'symbol' => ['nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $unit->update($validated);
        $this->recordActivity($request, 'unit.updated', $unit, "Unité {$unit->name} mise à jour.");

        return response()->json(['success' => true, 'data' => $unit]);
    }
}
