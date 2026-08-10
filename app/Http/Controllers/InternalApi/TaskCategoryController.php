<?php

namespace App\Http\Controllers\InternalApi;

use App\Http\Requests\Task\StoreTaskCategoryRequest;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class TaskCategoryController extends Controller
{
    public function store(StoreTaskCategoryRequest $request, Workspace $workspace): JsonResponse|RedirectResponse
    {
        $category = $workspace->taskCategories()->firstOrCreate(
            ['name' => $request->string('name')->toString()],
            ['color' => $request->string('color')->toString()],
        );

        return $this->success($request, [
            'public_id' => $category->public_id,
            'name' => $category->name,
            'color' => $category->color,
        ], 'Category saved.', back()->getTargetUrl(), 201);
    }
}
