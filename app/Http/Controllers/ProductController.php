<?php

namespace App\Http\Controllers;

use App\Http\helpers\ResponseHelper;
use App\Models\Category;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function allProducts(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $startDate = $request->input('start_date', Carbon::today());
        $endDate = $request->input('end_date', Carbon::today());

        $query = Product::withTrashed();
        $query->select([
            'products.*',
            'categories.category_name',
            'categories.uuid as category_uuid',
        ])
            ->join('categories', 'products.category_id', '=', 'categories.id');
//        $query->when($startDate, function ($q) use ($startDate) {
//
//            $q->whereDate('products.created_at', '>=', $startDate);
//        });
//        $query->when($endDate, function ($q) use ($endDate) {
//
//            $q->whereDate('products.created_at', '<=', $endDate);
//        });
//        $query->orderBy('products.created_at', 'desc');
//        $productsPaginator = $query->paginate($perPage);
//        $nextPageUrl = $productsPaginator->nextPageUrl();
//        $data = $productsPaginator->items();
//        $meta = [
//            'total' => $productsPaginator->total(),
//            'perPage' => $productsPaginator->perPage(),
//            'currentPage' => $productsPaginator->currentPage(),
//            'lastPage' => $productsPaginator->lastPage(),
//            'from' => $productsPaginator->firstItem(),
//            'to' => $productsPaginator->lastItem(),
//            'nextPageUrl' => $nextPageUrl, // Null if on the last page
//            'hasMorePages' => $productsPaginator->hasMorePages()
//
//        ];
         $data =   $query->orderBy(DB::raw('products.deleted_at IS NOT NULL'))
            ->orderBy('products.created_at', 'desc')
            ->get();
        return ResponseHelper::success(['data' => $data, 'meta' => []], 'Products retrieved successfully.', 200);

    }

    public function createProduct(Request $request): JsonResponse
    {
        $request->validate([
            'product_name' => ['required', 'string', 'max:255', 'unique:products'],
            'description' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric'],
            'original_price' => ['required', 'numeric'],
            'category_id' => ['required', 'exists:categories,uuid'],
            'minimum_order_quantity' => ['required', 'numeric'],
            'estimated_shipping_cost' => ['required', 'numeric'],
            'campaign_product' => ['required', 'numeric'],
            'recent_product' => ['required', 'numeric'],
            'in_stock' => ['required', 'numeric'],
            'specifications' => ['nullable', 'json'],
            'product_image' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'], // max:2048 means 2MB

        ]);

        $category = Category::where('uuid', $request->input('category_id'))->first();

        $imagePath = null;
        if ($request->hasFile('product_image')) {
            $imagePath = $request->file('product_image')->store('products', 'public');
        }
        $product = Product::create([
            'product_name' => $request->input('product_name'),
            'description' => $request->input('description'),
            'price' => $request->input('price'),
            'original_price' => $request->input('original_price'),
            'category_id' => $category->id,
            'minimum_order_quantity' => $request->input('minimum_order_quantity'),
            'estimated_shipping_cost' => $request->input('estimated_shipping_cost'),
            'campaign_product' => $request->input('campaign_product'),
            'recent_product' => $request->input('recent_product'),
            'in_stock' => $request->input('in_stock'),
            'specifications' => $request->input('specifications'),
            'image' => $imagePath,
        ]);
        $product->save();

        return ResponseHelper::success(['data' => $product], 'Product created successfully.', 201);
    }

    public function updateProduct(Request $request): JsonResponse
    {

        $product = Product::where('uuid', $request->input('uuid'))->first();

        if (!$product) {
            return ResponseHelper::error([], 'Product not found.', 404);
        }

        $request->validate([
            'product_name' => ['required', 'string', 'max:255', 'unique:products,product_name,' . $product->id],

            'description' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric'],
            'original_price' => ['required', 'numeric'],

            'category_uuid' => ['required', 'exists:categories,uuid'],

            'minimum_order_quantity' => ['required', 'numeric'],
            'estimated_shipping_cost' => ['required', 'numeric'],

            'campaign_product' => ['required'],
            'recent_product'   => ['required'],
            'in_stock'         => ['required'],

            'specifications' => ['nullable', 'json'],

            'product_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
        ]);

        $dataToUpdate = $request->except(['product_image', 'uuid']);

        $category = Category::where('uuid', $request->input('category_uuid'))->first();
        if ($category) {
            $dataToUpdate['category_id'] = $category->id;
        } else {
            return ResponseHelper::error([], 'Invalid category provided.', 422);
        }

        if ($request->hasFile('product_image')) {
            // A. Delete the old image if it exists
            if ($product->product_image) {
                Storage::disk('public')->delete($product->product_image);
            }

            // B. Store the new image and get the path
            $imagePath = $request->file('product_image')->store('products', 'public');

            // C. Add the new image path to the update array
            $dataToUpdate['image'] = $imagePath;
        }


        $product->update($dataToUpdate);


        return ResponseHelper::success(['data' => $product], 'Product updated successfully.', 200);
    }

    public function deleteProduct(Request $request): JsonResponse
    {
        $request->validate([
            'uuid' => ['required', 'exists:products,uuid'],
        ]);

        $product = Product::where('uuid', $request->input('uuid'))->first();
        if (!$product) {
            return ResponseHelper::error([], 'Product not found.', 404);
        }
        $product->delete();
        return ResponseHelper::success(['data' => $product], 'Product deleted successfully.', 200);
    }

    public function restoreProduct(Request $request): JsonResponse
    {
        $request->validate([
            'uuid' => ['required', 'exists:products,uuid'],
        ]);
        $product = Product::withTrashed()->where('uuid', $request->input('uuid'))->first();
        if (!$product) {
            return ResponseHelper::error([], 'Product not found.', 404);
        }
        $product->restore();
        return ResponseHelper::success(['data' => $product], 'Product restored successfully.', 200);
    }
}
