<?php

namespace App\Http\Controllers;

use App\Http\helpers\ResponseHelper;
use App\Models\Product;
use App\Models\Reviews;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function allReviews()
    {
        $reviews = Reviews::all();
        return ResponseHelper::success(['data' => $reviews], 'Product reviews fetched successfully.', 200);
    }

    public function createReview(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|string|exists:products,uuid',
            'review_name' => 'required|string|max:255',
            'review_text' => 'required|string',
            'review_image' => 'nullable|string',
            'rating' => 'required|integer|min:1|max:5',
        ]);

        $product = Product::where('uuid', $request->product_id)->first();

        if (!$product) {
            return ResponseHelper::error([], 'Product does not exist', 404);
        }
        $validated['status'] = 'pending';
        $validated['product_id'] = $product->id;
        $validated['review_image'] = "test";
        $review = Reviews::create($validated);
        return ResponseHelper::success(['data' => $review], 'Review created successfully.', 201);
    }

    public function updateReview(Request $request)
    {
        $request->validate([
            'uuid' => 'required|string|exists:reviews,uuid',
            'status' => 'required|string|in:pending,approved,rejected',
        ]);
        $review = Reviews::where('uuid', $request->uuid)->first();
        if (!$review) {
            return ResponseHelper::error([], 'Review does not exist', 404);
        }
        $review->status = $request->status;
        $review->save();
        return ResponseHelper::success(['data' => $review], 'Review updated successfully.', 200);
    }

}
