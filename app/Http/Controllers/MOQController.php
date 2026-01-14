<?php

namespace App\Http\Controllers;

use AfricasTalking\SDK\AfricasTalking;
use App\Http\helpers\AfricasTalkingSmsHelper;
use App\Http\helpers\ResponseHelper;
use App\Models\Order;
use App\Models\PaymentSchedule;
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
            'uuid' => ['required', 'string', 'max:255'],
            'minimum_order_quantity' => ['required', 'numeric'],
        ]);

        $productOrderBatch = ProductOrderBatch::where(['uuid' => $request->input('uuid')])->first();

        if (!$productOrderBatch) {
            return ResponseHelper::error([], 'Product Order Batch not found.', 404);
        }

        $productOrderBatch->moq_value = $request->input('minimum_order_quantity');
        $productOrderBatch->update();
        return ResponseHelper::success(['data' => $productOrderBatch], 'Product MOQ value updated successfully.', 200);
    }

    public function moqProducts(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = ProductOrderBatch::query();
        $query->join('products', 'product_order_batches.product_id', '=', 'products.id');
        $query->select('product_order_batches.*', 'products.uuid as product_uuid', 'products.product_name');
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
        // Get start and end dates from request
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // If not provided, default to last month
        if (!$startDate || !$endDate) {
            $startDate = now()->subMonth()->startOfDay();
            $endDate = now()->endOfDay();
        } else {
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate = Carbon::parse($endDate)->endOfDay();
        }

        // Apply date filters to queries
        $productsBelowMOQ = ProductOrderBatch::where('moq_status', 'PENDING')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $productsAwaitingConfirmation = ProductOrderBatch::where('moq_status', 'REACHED')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $batchesCompleted = ProductOrderBatch::where('shipping_fee_status', 'PROCESSED')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $totalShippingFeesCollected = Order::where('shipping_payment_status', 'PAID')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('shipping_fee');

        $ordersAwaitingShippingPayment = Order::where('shipping_payment_status', 'UNPAID')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $ordersBlockedFromDelivery = Order::where([
            'shipping_payment_status' => 'UNPAID',
            'shipping_verification_status' => 'UNVERIFIED'
        ])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

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


    public function closeMOQ(Request $request): JsonResponse
    {
        $request->validate([
            'uuid' => ['required', 'string', 'max:255'],
            'shipping_price' => ['required', 'numeric'],
        ]);

        $productOrderBatch = ProductOrderBatch::where('uuid', $request->input('uuid'))->first();
        if (!$productOrderBatch) {
            return ResponseHelper::error([], 'Product order batch not found.', 404);
        }
        $productOrderBatch->moq_status = 'AWAITING_SHIPPING_FEE';
        $productOrderBatch->update();

        // Fetch all the orders and send sms for them to pay the shipping fee
        $orders = Order::whereIn('id', $productOrderBatch->order_ids)->get();
        if ($request->input('shipping_price') == 0) {
            $this->updateShippingStatus($orders);
        } else {
            $this->updateShippingPaymentDetails($orders, $request->input('shipping_price'));
        }


        return ResponseHelper::success(['data' => $productOrderBatch],
            'We have closed this Order batch and we are sending sms to the customers to pay for shipping fees.', 200);
    }

    private function updateShippingStatus($orders): void
    {
        foreach ($orders as $order) {
            $order->shipping_payment_status = 'PAID';
            $order->shipping_payment_receipt = 'FREE SHIPPING';
            $order->update();
        }
    }

    private function updateShippingPaymentDetails($orders, $shippingPrice): void
    {
        foreach ($orders as $order) {
            $total = $order->total_with_shipping - $order->shipping_fee;
            $order->shipping_fee = $shippingPrice;
            $order->total_with_shipping = $total + $shippingPrice;
            $order->update();

            $payment_schedule = PaymentSchedule::where('order_id', $order->id)->first();
            if ($payment_schedule) {
                $payment_schedule->shipping_amount = $shippingPrice;
                $payment_schedule->update();
            }
            $africasTalking = new AfricasTalkingSmsHelper();
            $africasTalking::sendSmsNotification($order->customer_phone, 'Kindly pay a shipping fee of ' . $shippingPrice . '
             for your order with order number ' . $order->order_number);
        }
    }
}
