<?php

namespace App\Http\Controllers;

use App\Http\helpers\ResponseHelper;
use App\Models\Product;
use Illuminate\Http\Request;

class MOQController extends Controller
{
    public function createMOQ(Request $request)
    {
        $request->validate([
            'product_uuid' => ['required', 'string', 'max:255'],
            'minimum_order_quantity' => ['required', 'numeric'],
        ]);

        $product = Product::where(['uuid' => $request->input('product_uuid')])->first();

        if (!$product) {
            return ResponseHelper::error([], 'Product not found.', 404);
        }

        $product->minimum_order_quantity = $request->input('minimum_order_quantity');
        $product->update();
        return ResponseHelper::success(['data' => $product], 'Product MOQ value updated successfully.', 200);
    }
}
