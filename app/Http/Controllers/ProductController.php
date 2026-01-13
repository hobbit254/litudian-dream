<?php

namespace App\Http\Controllers;

use App\Http\helpers\ResponseHelper;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImages;
use App\Models\Reviews;
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
        $category_name = request()->input('category_name');
        $product_name = request()->input('product_name');
        $min_price = request()->input('min_price');
        $max_price = request()->input('max_price');

        $query = Product::withTrashed()->with('images');
        $query->select([
            'products.*',
            'categories.category_name',
            'categories.uuid as category_uuid',
        ])
            ->join('categories', 'products.category_id', '=', 'categories.id');
        $query->when($startDate, function ($q) use ($startDate) {

            $q->whereDate('products.created_at', '>=', $startDate);
        });
        $query->when($endDate, function ($q) use ($endDate) {

            $q->whereDate('products.created_at', '<=', $endDate);
        });
        $query->when($category_name, function ($q) use ($category_name) {
            $q->whereLike('categories.category_name', $category_name);
        });
        $query->when($product_name, function ($q) use ($product_name) {
            $q->where('products.product_name', $product_name);
        });
        $query->when(request()->filled('min_price') && request()->filled('max_price'), function ($q) {
            $q->whereBetween('products.price', [request()->input('min_price'), request()->input('max_price')]);
        });
        $query->when(request()->filled('min_price') && !request()->filled('max_price'), function ($q) {
            $q->where('products.price', '>=', request()->input('min_price'));
        });
        $query->when(!request()->filled('min_price') && request()->filled('max_price'), function ($q) {
            $q->where('products.price', '<=', request()->input('max_price'));
        });
        $query->orderBy(DB::raw('products.deleted_at IS NOT NULL'))
            ->orderBy('products.created_at', 'desc');
        $productsPaginator = $query->with('images')->paginate($perPage);
        $nextPageUrl = $productsPaginator->nextPageUrl();
        $data = $productsPaginator->items();
        $meta = [
            'total' => $productsPaginator->total(),
            'perPage' => $productsPaginator->perPage(),
            'currentPage' => $productsPaginator->currentPage(),
            'lastPage' => $productsPaginator->lastPage(),
            'from' => $productsPaginator->firstItem(),
            'to' => $productsPaginator->lastItem(),
            'nextPageUrl' => $nextPageUrl, // Null if on the last page
            'hasMorePages' => $productsPaginator->hasMorePages()

        ];
        return ResponseHelper::success(['data' => $data, 'meta' => $meta], 'Products retrieved successfully.', 200);

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
            'campaign_product' => ['required'],
            'recent_product' => ['required'],
            'in_stock' => ['required'],
            'specifications' => ['nullable', 'json'],
            'product_image' => ['required', 'array'],
            'product_image.*' => ['image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],

        ]);

        $category = Category::where('uuid', $request->input('category_id'))->first();

        $imagePath = null;

        $product = Product::create([
            'product_name' => $request->input('product_name'),
            'description' => $request->input('description'),
            'price' => $request->input('price'),
            'original_price' => $request->input('original_price'),
            'category_id' => $category->id,
            'minimum_order_quantity' => $request->input('minimum_order_quantity'),
            'estimated_shipping_cost' => $request->input('estimated_shipping_cost'),
            'campaign_product' => $request->boolean('campaign_product'),
            'recent_product' => $request->boolean('recent_product'),
            'in_stock' => $request->boolean('in_stock'),
            'specifications' => $request->input('specifications'),
            'image' => $imagePath,
        ]);
        $product->save();

        if ($request->hasFile('product_image')) {
            $images = $request->file('product_image');
            foreach ($images as $index => $image) {
                $imagePath = $image->store('products', 'public');
                ProductImages::create(['product_id' => $product->id, 'product_image' => $imagePath, 'primary_image' => $index === 0 ? 1 : 0,]);
            }
        }

        return ResponseHelper::success(['data' => $product->load('images')], 'Product created successfully.', 201);
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
            'recent_product' => ['required'],
            'in_stock' => ['required'],
            'specifications' => ['nullable', 'json'],

            // ✅ Accept multiple images
            'product_image' => ['nullable', 'array'],
            'product_image.*' => ['image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
        ]);

        $dataToUpdate = $request->except(['product_images', 'uuid']);

        $category = Category::where('uuid', $request->input('category_uuid'))->first();
        if ($category) {
            $dataToUpdate['category_id'] = $category->id;
        } else {
            return ResponseHelper::error([], 'Invalid category provided.', 422);
        }

        // ✅ Update product details
        $dataToUpdate['campaign_product'] = $request->boolean('campaign_product');
        $dataToUpdate['recent_product'] = $request->boolean('recent_product');
        $dataToUpdate['in_stock'] = $request->boolean('in_stock');
        $product->update($dataToUpdate);

        // ✅ Handle multiple images

        // Option A: Delete old images if you want to replace them
        $imageIdsToKeep = (array)$request->input('existing_product_image', []);

        // Step 1: Delete images not in the keep array
        ProductImages::where('product_id', $product->id)
            ->whereNotIn('uuid', $imageIdsToKeep)
            ->delete();

        // Step 2: Reset all current product images to primary_image = 0
        ProductImages::where('product_id', $product->id)
            ->update(['primary_image' => 0]);

        // Step 3: If we have at least one ID to keep, set the first one as primary
        if (!empty($imageIdsToKeep)) {
            ProductImages::where('product_id', $product->id)
                ->where('uuid', $imageIdsToKeep[0])
                ->update(['primary_image' => 1]);
        }


        // Option B: Keep old images and just add new ones (comment out the above line)
        if ($request->hasFile('product_image')) {
            foreach ($request->file('product_image') as $image) {
                $imagePath = $image->store('products', 'public');

                ProductImages::create([
                    'product_id' => $product->id,
                    'product_image' => $imagePath,
                ]);
            }
        }


        return ResponseHelper::success(['data' => $product->load('images')], 'Product updated successfully.', 200);
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

    public function getProduct(string $id)
    {
        $product = Product::join('categories', 'categories.id', '=', 'products.category_id')
            ->where('products.uuid', $id)
            ->with('images')
            ->first();
        if (!$product) {
            return ResponseHelper::error([], 'Product not found.', 404);
        } else {
            $reviews = Reviews::where('product_id', $product->id)->get();
            $product['reviews'] = $reviews;
            return ResponseHelper::success(['data' => $product->load('images')], 'Product retrieved successfully.', 200);
        }
    }
}
