<?php

namespace App\Http\Controllers;

use AfricasTalking\SDK\AfricasTalking;
use App\Http\helpers\AfricasTalkingSmsHelper;
use App\Http\helpers\ResponseHelper;
use App\Jobs\SendSmsNotificationJob;
use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Models\Product;
use App\Models\ProductOrderBatch;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LaravelIdea\Helper\App\Models\_IH_Order_C;

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

        // Transform data: replace order_ids with order_uuid
        $data = collect($productsOrderBatchPaginator->items())->map(function ($item) {
            // Ensure order_ids exists and is an array
            if (!empty($item->order_ids) && is_array($item->order_ids)) {
                $orderUuids = Order::whereIn('id', $item->order_ids)->pluck('uuid')->toArray();
                $item->order_ids = $orderUuids; // Replace IDs with UUIDs
            }
            return $item;
        });

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

        if ($productOrderBatch->moq_status !== 'PENDING') {
            return ResponseHelper::error([], 'Product order batch has already been closed awaiting shipping payment or shipping.', 422);
        }

        //check first to see if all the orders have been paid for
        $orders = Order::whereIn('id', $productOrderBatch->order_ids)->get();
        if (!$this->ordersProductPaymentStatus($orders)) {
            return ResponseHelper::error([], 'Ensure all the orders in this batch have their order payment paid first.', 422);
        }

        $productOrderBatch->moq_status = 'AWAITING_SHIPPING_FEE';
        $productOrderBatch->save();

        if ($request->input('shipping_price') == 0) {
            $this->updateShippingStatus($orders);
        } else {
            $this->updateShippingPaymentDetails($orders, $request->input('shipping_price'), $productOrderBatch->id);
        }


        return ResponseHelper::success(['data' => $productOrderBatch],
            'We have closed this Order batch and we are sending sms to the customers to pay for shipping fees.', 200);
    }

    public function closeShippingFeeCollection(Request $request)
    {
        $request->validate([
            'uuid' => ['required', 'string', 'max:255'],
        ]);
        $productOrderBatch = ProductOrderBatch::where('uuid', $request->input('uuid'))->first();
        if (!$productOrderBatch) {
            return ResponseHelper::error([], 'Product order batch not found.', 404);
        }
        $orders = Order::whereIn('id', $productOrderBatch->order_ids)->get();
        if (!$this->ordersShippingFeePaymentStatus($orders)) {
            return ResponseHelper::error([], 'Ensure all the orders in this batch have their order shipping fee paid first.', 422);
        }
        $productOrderBatch->moq_status = 'SHIPPING_FEE_PAID';
        $productOrderBatch->shipping_fee_status = 'PAID';
        $productOrderBatch->save();
        return ResponseHelper::success(['data' => $productOrderBatch], "Closed the shipping payment for the batch now awaiting shipping", 200);
    }

    public function updateOrderBatchStatus(Request $request)
    {
        $request->validate([
            'uuid' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:255'],
        ]);
        $productOrderBatch = ProductOrderBatch::where('uuid', $request->input('uuid'))->first();
        if (!$productOrderBatch) {
            return ResponseHelper::error([], 'Product order batch not found.', 404);
        }
        $allowedStatuses = ['SHIPPED', 'DELIVERED', 'CANCELLED', 'SHIPPING_FEE_PAID'];
        if (!in_array($productOrderBatch->moq_status, $allowedStatuses)) {
            return ResponseHelper::error([], "The batch is not in a desired status to apply this action", 422);
        }

        $productOrderBatch->moq_status = $request->input('status');
        $productOrderBatch->save();
        $orders = Order::whereIn('id', $productOrderBatch->order_ids)->get();

        foreach ($orders as $order) {
            $status_history_entry = [
                'status' => request()->input('status'),
                'date' => Carbon::now()->toDateTimeString(),
                'message' => request()->input('message'),
            ];

            // Get existing history (already cast to array)
            $existing_history = $order->status_history ?? [];

            // Append new entry
            $existing_history[] = $status_history_entry;
            $order->status_history = $existing_history;
            $order->status = request()->input('status');
            $order->save();

            // trigger an alert to the customer
        }
        return ResponseHelper::success(['data' => $productOrderBatch], "We have updated the order batch status successfully", 200);
    }

    private function updateShippingStatus($orders): void
    {
        foreach ($orders as $order) {
            $order->shipping_payment_status = 'PAID';
            $order->shipping_payment_receipt = 'FREE SHIPPING';
            $order->save();
        }
    }

    private function updateShippingPaymentDetails($orders, $shippingPrice, $productOrderBatchId): void
    {
        $productOrderBatch = ProductOrderBatch::where('id', $productOrderBatchId)->first();
        $totalShippingFee = count($orders) * $shippingPrice;
        $productOrderBatch->shipping_fee = $totalShippingFee;
        $productOrderBatch->save();
        foreach ($orders as $order) {

            // Build new history entry
            $status_history_entry = [
                'status' => 'MOQ_ACHIEVED',
                'date' => Carbon::now()->toDateTimeString(),
                'message' => "We have closed the MOQ for this order",
            ];

            // Get existing history (already cast to array)
            $existing_history = $order->status_history ?? [];

            // Append new entry
            $existing_history[] = $status_history_entry;

            $total = $order->total_with_shipping - $order->shipping_fee;
            $order->shipping_fee = $shippingPrice;
            $order->total_with_shipping = $total + $shippingPrice;
            $order->status = 'MOQ_ACHIEVED';
            $order->status_history = $existing_history;
            $order->update();

            $payment_schedule = PaymentSchedule::where('order_id', $order->id)->first();
            if ($payment_schedule) {
                $payment_schedule->shipping_amount = $shippingPrice;
                $payment_schedule->update();
            }
            SendSmsNotificationJob::dispatch($order->customer_phone, 'Kindly pay a shipping fee of ' . $shippingPrice . ' for your order with order number ' . $order->order_number);
        }
    }

    private function ordersProductPaymentStatus($orders)
    {
        $totalOrders = count($orders);
        $totalOrdersPaid = 0;
        foreach ($orders as $order) {
            if ($order->product_payment_status === 'PAID') {
                $totalOrdersPaid += 1;
            }
        }
        if ($totalOrdersPaid == $totalOrders) {
            return true;
        } else {
            return false;
        }
    }

    private function ordersShippingFeePaymentStatus($orders)
    {
        $totalOrders = count($orders);
        $totalOrdersPaid = 0;
        foreach ($orders as $order) {
            if ($order->shipping_payment_status === 'PAID') {
                $totalOrdersPaid += 1;
            }
        }
        if ($totalOrdersPaid == $totalOrders) {
            return true;
        } else {
            return false;
        }
    }
}
