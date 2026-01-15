<?php

namespace App\Http\Controllers;

use App\Http\helpers\ResponseHelper;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Models\Product;
use App\Models\ProductOrderBatch;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrdersController extends Controller
{
    public function allOrders(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $startDate = $request->input('start_date', Carbon::today());
        $endDate = $request->input('end_date', Carbon::today());
        $order_number = $request->input('order_number');
        $customer_name = $request->input('customer_name');
        $customer_email = $request->input('customer_email');
        $status = $request->input('status');
        $product_payment_status = $request->input('product_payment_status');
        $moq_status = $request->input('moq_status');

        $query = Order::query();
        $query->select(['orders.*']);
        $query->when($startDate, function ($q) use ($startDate) {

            $q->whereDate('orders.created_at', '>=', $startDate);
        });
        $query->when($endDate, function ($q) use ($endDate) {

            $q->whereDate('orders.created_at', '<=', $endDate);
        });
        $query->when($order_number, function ($q) use ($order_number) {
            $q->where('orders.order_number', $order_number);
        });
        $query->when($customer_name, function ($q) use ($customer_name) {
            $q->where('orders.customer_name', $customer_name);
        });
        $query->when($customer_email, function ($q) use ($customer_email) {
            $q->where('orders.customer_email', $customer_email);
        });
        $query->when($status, function ($q) use ($status) {
            $q->where('orders.status', $status);
        });
        $query->when($product_payment_status, function ($q) use ($product_payment_status) {
            $q->where('orders.product_payment_status', $product_payment_status);
        });
        $query->when($moq_status, function ($q) use ($moq_status) {
            $q->where('orders.moq_status', $moq_status);
        });

        $query->orderBy('orders.created_at', 'desc');

        $ordersPaginator = $query->paginate($perPage);
        $nextPageUrl = $ordersPaginator->nextPageUrl();
        $data = $ordersPaginator->items();
        $meta = [
            'total' => $ordersPaginator->total(),
            'perPage' => $ordersPaginator->perPage(),
            'currentPage' => $ordersPaginator->currentPage(),
            'lastPage' => $ordersPaginator->lastPage(),
            'from' => $ordersPaginator->firstItem(),
            'to' => $ordersPaginator->lastItem(),
            'nextPageUrl' => $nextPageUrl,
            'hasMorePages' => $ordersPaginator->hasMorePages()
        ];
        return ResponseHelper::success(['data' => $data, 'meta' => $meta], 'Orders retrieved successfully.', 200);
    }

    public function singleOrder(Request $request, $id): JsonResponse
    {
        $order = Order::where('uuid', $id)->first();
        if (!$order) {
            return ResponseHelper::error([], 'Order not found.', 404);
        }
        return ResponseHelper::success(['data' => $order], 'Order retrieved successfully.', 200);
    }

    public function createOrder(Request $request): JsonResponse
    {
        $request->validate([
            // 'customer_name' => ['required', 'string'],
            //   'customer_email' => ['required', 'email'],
            'customer_phone' => ['required', 'string'],
            'products' => ['required', 'json'],
            'total' => ['required', 'numeric'],
            'is_anonymous' => ['required', 'boolean'],
            // 'payment_reference' => ['required', 'string', 'unique:payments,merchant_ref'],
            'shipping_fee' => ['required', 'numeric'],
            'service_fee' => ['required', 'numeric'],
            'balance_due' => ['required', 'numeric'],
            'amount_paid' => ['required', 'numeric'],
        ]);

        if ($request->has('payment_reference')) {
            $request->validate([
                'payment_reference' => ['required', 'string', 'unique:payments,merchant_ref'],
            ]);
        }

        DB::beginTransaction();
        try {
            $order = $this->saveOrder($request);

            $this->savePaymentSchedule($order, $request);

            if ($request->has('payment_reference')) {
                $this->savePayment($order, $request);
            }
            $this->saveProductOrderBatch(json_decode($request->input('products'), true), $order);
            DB::commit();

            return ResponseHelper::success(['data' => $order], 'Order created successfully.', 201);
        } catch (\Exception $exception) {
            DB::rollBack();
            logger()->error($exception->getMessage());
            return ResponseHelper::error([], 'There was an issue when saving the data to the database', 500);
        }
    }

    /**
     * @param $order
     * @param Request $request
     * @return void
     */
    public function savePaymentSchedule($order, Request $request): void
    {
        $payment_schedule = PaymentSchedule::create([
            'order_id' => $order->id,
            'deposit_amount' => $request->input('amount_paid'),
            'deposit_paid' => False,
            'deposit_due_date' => Carbon::now()->addDays(10),
            'deposit_receipt' => '',
            'balance_due' => $request->input('total') - $request->input('amount_paid') - $request->input('shipping_fee'),
            'balance_due_date' => Carbon::now()->addDays(30),
            'balance_paid' => False,
            'balance_receipt' => '',
            'shipping_amount' => $request->input('shipping_fee'),
            'shipping_paid' => False,
            'shipping_receipt' => '',
            'service_fee' => $request->input('service_fee'),
            'balance_amount' => 0.00
        ]);
        $payment_schedule->save();
    }

    /**
     * @param $order
     * @param Request $request
     * @return void
     */
    public function savePayment($order, Request $request): void
    {
        $payment_history[] = [
            'status' => 'UNVERIFIED',
            'date' => Carbon::now(),
            'message' => 'Payment received successfully awaiting confirmation by the administrator based on the payment ref passed.',
        ];

        $payment = Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'MPESA',
            'phone_number' => $request->input('customer_phone'),
            'payment_amount' => $request->input('amount_paid'),
            'payment_status' => 'UNVERIFIED',
            'payment_unique_ref' => Str::uuid(),
            'payment_history' => $payment_history,
            'merchant_ref' => $request->input('payment_reference'),
        ]);
        $payment->save();
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function saveOrder(Request $request): mixed
    {
        $message = 'Deposit of KES ' . $request->input('amount_paid') . ' received (includes KES ' .
            $request->input('service_fee') / 2 . ' service fee)';
        $status_history[] = [
            'status' => 'PENDING',
            'date' => Carbon::now(),
            'message' => $message
        ];

        $order = Order::create([
            'customer_name' => $request->input('customer_name') ?? '',
            'customer_email' => $request->input('customer_email') ?? '',
            'customer_phone' => $request->input('customer_phone'),
            'products' => $request->input('products'),
            'total' => $request->input('total'),
            'is_anonymous' => $request->input('is_anonymous'),
            'status' => 'AWAITING_BALANCE',
            'status_history' => $status_history,
            'payment_receipt' => $request->input('payment_reference') ?? '',
            'shipping_fee' => $request->input('shipping_fee'),
            'total_with_shipping' => $request->input('total'),
            'product_payment_status' => 'UNPAID',
            'shipping_payment_status' => 'UNPAID',
            'shipping_payment_receipt' => '',
            'shipping_verification_status' => 'UNVERIFIED',
            'moq_status' => 'AWAITING_CONFIRMATION',
        ]);
        $order->save();
        return $order;
    }

    private function saveProductOrderBatch(mixed $products, $order): void
    {
        foreach ($products as $product) {

            $prod = Product::where('uuid', $product['id'])->first();

            if (!$prod) {
                continue;
            }

            $quantity = (int)$product['quantity'];
            $moqValue = (int)$prod->minimum_order_quantity;

            // Always get latest batch
            $latestBatch = ProductOrderBatch::where('product_id', $prod->id)
                ->orderBy('batch_number', 'desc')
                ->first();

            // Build order ID list
            $orderIds = $latestBatch?->order_ids ?? [];

            /**
             * ----------------------------------------------------------
             * SCENARIO 1: NO BATCH EXISTS — CREATE FIRST BATCH
             * ----------------------------------------------------------
             */
            if (!$latestBatch) {

                $moqStatus = ($quantity >= $moqValue) ? 'REACHED' : 'PENDING';

                ProductOrderBatch::create([
                    'product_id' => $prod->id,
                    'batch_number' => 1,
                    'shipping_fee' => 0,
                    'shipping_fee_status' => 'PENDING',
                    'moq_status' => $moqStatus,
                    'orders_collected' => $quantity,
                    'moq_value' => $moqValue,
                    'order_ids' => [$order->id],
                ]);

                continue;
            }


            /**
             * ----------------------------------------------------------
             * SCENARIO 2: BATCH EXISTS — ADD TO EXISTING IF NOT FULL
             * ----------------------------------------------------------
             */
            if ($latestBatch->orders_collected < $latestBatch->moq_value) {

                $newCollected = $latestBatch->orders_collected + $quantity;

                // Determine new batch status
                $batchMoqStatus = ($newCollected >= $latestBatch->moq_value)
                    ? 'REACHED'
                    : 'PENDING';

                // Merge order ids
                $latestBatch->order_ids = array_unique(array_merge($orderIds, [$order->id]));

                $latestBatch->update([
                    'orders_collected' => $newCollected,
                    'order_ids' => $latestBatch->order_ids,
                    'moq_status' => $batchMoqStatus,
                ]);

                continue;
            }


            /**
             * ----------------------------------------------------------
             * SCENARIO 3: LATEST BATCH IS FULL — CREATE NEW ONE
             * ----------------------------------------------------------
             */

            // Mark old batch as reached (if not already)
            if ($latestBatch->moq_status !== 'REACHED') {
                $latestBatch->update(['moq_status' => 'REACHED']);
            }

            $newBatchNumber = $latestBatch->batch_number + 1;

            $newBatchMoqStatus = ($quantity >= $moqValue) ? 'REACHED' : 'PENDING';

            ProductOrderBatch::create([
                'product_id' => $prod->id,
                'batch_number' => $newBatchNumber,
                'shipping_fee' => 0,
                'shipping_fee_status' => 'PENDING',
                'moq_status' => $newBatchMoqStatus,
                'orders_collected' => $quantity,
                'moq_value' => $moqValue,
                'order_ids' => [$order->id],
            ]);

        }

    }

    public function updateOrderStatus(Request $request): mixed
    {
        $request->validate([
            'order_status' => 'required',
            'uuid' => 'required',
            'customer_name' => 'required',
            'customer_email' => 'required',
            'customer_phone' => 'required',
            'payment_reference' => 'required',
            'product_payment_status' => 'required',
            'shipping_payment_status' => 'required',
            'message' => 'required',
        ]);

        $order = Order::where('uuid', $request->input('uuid'))->first();
        if (!$order) {
            return ResponseHelper::error([], 'Order not found.', 404);
        }

        $newStatus = $request->input('order_status');

        // 🚨 Prevent updating with the same status
        if ($order->status === $newStatus) {
            return ResponseHelper::error([], 'Order already has this status.', 422);
        }

        // Build new history entry
        $status_history_entry = [
            'status' => $newStatus,
            'date' => Carbon::now()->toDateTimeString(),
            'message' => $request->input('message'),
        ];

        // Get existing history (already cast to array)
        $existing_history = $order->status_history ?? [];

        // Append new entry
        $existing_history[] = $status_history_entry;

        // Update order with full history
        $order->update([
            'status' => $newStatus,
            'customer_name' => $request->input('customer_name'),
            'customer_email' => $request->input('customer_email'),
            'customer_phone' => $request->input('customer_phone'),
            'product_payment_status' => $request->input('product_payment_status'),
            'shipping_payment_status' => $request->input('shipping_payment_status'),
            'payment_receipt' => $request->input('payment_reference'),
            'status_history' => $existing_history, //
        ]);

        return ResponseHelper::success(['data' => $order], 'Order status updated.', 200);
    }


}
