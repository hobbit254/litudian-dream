<?php

namespace App\Http\Controllers;

use App\Http\helpers\ResponseHelper;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOrderBatch;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MOQController extends Controller
{
    public function createMOQ(Request $request): JsonResponse
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

    public function moqProducts(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $startDate = $request->input('start_date', Carbon::today());
        $endDate = $request->input('end_date', Carbon::today());

        $query = ProductOrderBatch::query();
        $query->join('products', 'product_order_batches.product_id', '=', 'products.id');
        $query->select('product_order_batches.*', 'products.uuid', 'products.product_name');
        $query->when($startDate, function ($q) use ($startDate) {

            $q->whereDate('product_order_batches.created_at', '>=', $startDate);
        });
        $query->when($endDate, function ($q) use ($endDate) {

            $q->whereDate('product_order_batches.created_at', '<=', $endDate);
        });
        $query->orderBy('product_order_batches.created_at', 'desc');

        $productsOrderBatchPaginator = $query->paginate($perPage);
        $nextPageUrl = $productsOrderBatchPaginator->nextPageUrl();
        $data = $productsOrderBatchPaginator->items();
        $meta = [
            'total' => $productsOrderBatchPaginator->total(),
            'perPage' => $productsOrderBatchPaginator->perPage(),
            'currentPage' => $productsOrderBatchPaginator->currentPage(),
            'lastPage' => $productsOrderBatchPaginator->lastPage(),
            'from' => $productsOrderBatchPaginator->firstItem(),
            'to' => $productsOrderBatchPaginator->lastItem(),
            'nextPageUrl' => $nextPageUrl, // Null if on the last page
            'hasMorePages' => $productsOrderBatchPaginator->hasMorePages()
        ];

        return ResponseHelper::success(['data' => $data, 'meta' => $meta], 'Product MOQ fetched successfully.', 200);
    }

    public function moqStats(Request $request): JsonResponse
    {
        $productsBelowMOQ = ProductOrderBatch::where('moq_status', 'PENDING')->count();
        $productsAwaitingConfirmation = ProductOrderBatch::where('moq_status', 'REACHED')->count();
        $batchesCompleted = ProductOrderBatch::where('shipping_fee_status', 'PROCESSED')->count();
        $totalShippingFeesCollected = Order::where('shipping_payment_status', 'PAID')->sum('shipping_fee');
        $ordersAwaitingShippingPayment = Order::where('shipping_payment_status', 'UNPAID')->count();
        $ordersBlockedFromDelivery = Order::where(['shipping_payment_status' => 'UNPAID',
            'shipping_verification_status' => 'UNVERIFIED'])->count();

        $data = [
            'products_belowMOQ' => $productsBelowMOQ,
            'products_awaitingConfirmation' => $productsAwaitingConfirmation,
            'batches_completed' => $batchesCompleted,
            'total_shipping_fees_collected' => $totalShippingFeesCollected,
            'orders_blocked_from_delivery' => $ordersBlockedFromDelivery,
            'orders_awaiting_shipping_payment' => $ordersAwaitingShippingPayment,
        ];
        return ResponseHelper::success($data, 'MOQ Stats fetched successfully.', 200);

    }
}
