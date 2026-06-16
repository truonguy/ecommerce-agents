<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreCategoryRequest;
use App\Http\Requests\Crm\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\Crm\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categories,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->categories->list()]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categories->create($request->validated());

        return response()->json($category, 201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category = $this->categories->update($category, $request->validated());

        return response()->json($category);
    }

    public function destroy(Category $category): Response
    {
        $this->categories->delete($category);

        return response()->noContent();
    }
}
