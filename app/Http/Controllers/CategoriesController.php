<?php

namespace App\Http\Controllers;

use App\Http\helpers\ResponseHelper;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CategoriesController extends Controller
{
    public function allCategories(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $startDate = $request->input('start_date', Carbon::today());
        $endDate = $request->input('end_date', Carbon::today());

        $query = Category::withTrashed();
        $query->when($startDate, function ($q) use ($startDate) {

            $q->whereDate('created_at', '>=', $startDate);
        });
        $query->when($endDate, function ($q) use ($endDate) {

            $q->whereDate('created_at', '<=', $endDate);
        });
        $query->orderBy('created_at', 'desc');
        $categoriesPaginator = $query->paginate($perPage);
        $data = $categoriesPaginator->items();
        $meta = [
            'total' => $categoriesPaginator->total(),
            'perPage' => $categoriesPaginator->perPage(),
            'currentPage' => $categoriesPaginator->currentPage(),
            'lastPage' => $categoriesPaginator->lastPage(),
            'from' => $categoriesPaginator->firstItem(),
            'to' => $categoriesPaginator->lastItem()
        ];
        return ResponseHelper::success(['data' => $data, 'meta' => $meta], 'Category list retrieved successfully.', 200);

    }

    public function createCategory(Request $request): JsonResponse
    {
        $request->validate([
            'category_name' => ['required', 'string', 'max:255', 'unique:categories'],
            'slug' => ['required', 'string', 'max:255', 'unique:categories'],
            'description' => ['required', 'string', 'max:255'],
            'heroImage' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'], // max:2048 means 2MB
            'tagline' => ['required', 'string'],
        ]);

        $imagePath = null;

        // 2. Handle Image Upload
        if ($request->hasFile('heroImage')) {
            // Store the image in the 'public/categories' directory and get the file path
            // The 'public' disk often maps to the storage/app/public directory.
            // You should run 'php artisan storage:link' to make it accessible via a URL.
            $imagePath = $request->file('heroImage')->store('categories', 'public');
        }

        $category = Category::create([
            'category_name' => $request->input('category_name'),
            'slug' => $request->input('slug'),
            'description' => $request->input('description'),
            'heroImage' => $imagePath,
            'tagline' => $request->input('tagline'),
        ]);
        $category->save();

        return ResponseHelper::success(['data' => $category], 'Category created successfully.', 200);
    }

    public function updateCategory(Request $request): JsonResponse
    {
        $category = Category::where('uuid', $request->input('uuid'))->first();

        if (empty($category)) {
            return ResponseHelper::error([], 'Category not found.', 404);
        }
        $request->validate([
            'category_name' => ['required', 'string', 'max:255', 'unique:categories,category_name,' . $category->id],
            'slug' => ['required', 'string', 'max:255', 'unique:categories,slug,' . $category->id],
            'description' => ['required', 'string', 'max:255'],
            'heroImage' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'], // 'nullable' and file-specific rules
            'tagline' => ['required', 'string'],
        ]);

        $dataToUpdate = [
            'category_name' => $request->input('category_name'),
            'slug' => $request->input('slug'),
            'description' => $request->input('description'),
            'tagline' => $request->input('tagline'),
        ];
        if ($request->hasFile('heroImage')) {
            // A. Delete the old image if it exists
            if ($category->heroImage) {
                Storage::disk('public')->delete($category->heroImage);
            }

            // B. Store the new image and get the path
            $imagePath = $request->file('heroImage')->store('categories', 'public');

            // C. Add the new image path to the update array
            $dataToUpdate['heroImage'] = $imagePath;
        }

        // 4. Perform the Update
        $category->update($dataToUpdate);
        return ResponseHelper::success(['data' => $category], 'Category updated successfully.', 200);
    }

    public function deleteCategory(Request $request): JsonResponse
    {
        $request->validate([
            'uuid' => ['required', 'string', 'max:255'],
        ]);
        $category = Category::where('uuid', $request->input('uuid'))->first();
        if (empty($category)) {
            return ResponseHelper::error([], 'Category not found.', 404);
        }
        $category->delete();
        return ResponseHelper::success([], 'Category deleted successfully.', 200);
    }

    public function restoreCategory(Request $request): JsonResponse
    {
        $request->validate([
            'uuid' => ['required', 'string', 'max:255'],
        ]);
        $category = Category::where('uuid', $request->input('uuid'))->first();
        if (empty($category)) {
            return ResponseHelper::error([], 'Category not found.', 404);
        }
        $category->restore();
        return ResponseHelper::success([], 'Category restored successfully.', 200);
    }
}
