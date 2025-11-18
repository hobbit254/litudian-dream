<?php

namespace App\Http\Controllers;

use App\Http\helpers\ResponseHelper;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrdersController extends Controller
{
    public function allOrders(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $startDate = $request->input('start_date', Carbon::today());
        $endDate = $request->input('end_date', Carbon::today());

        $query = Order::query();
        $query->select(['orders.*']);
        $query->when($startDate, function ($q) use ($startDate) {

            $q->whereDate('orders.created_at', '>=', $startDate);
        });
        $query->when($endDate, function ($q) use ($endDate) {

            $q->whereDate('orders.created_at', '<=', $endDate);
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
            'nextPageUrl' => $nextPageUrl, // Null if on the last page
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
            'customer_name' => ['required', 'string'],
            'customer_email' => ['required', 'email'],
            'customer_phone' => ['required', 'string'],
            'products' => ['required', 'json'],
            'total' => ['required', 'numeric'],
            'is_anonymous' => ['required', 'boolean'],
            'payment_reference' => ['required', 'string'],
            'shipping_fee' => ['required', 'numeric'],
            'service_fee' => ['required', 'numeric'],
            'balance_due' => ['required', 'numeric'],
            'amount_paid' => ['required', 'numeric'],
        ]);

        $order = $this->saveOrder($request);

        $this->savePaymentSchedule($order, $request);

        $this->savePayment($order, $request);

        return ResponseHelper::success(['data' => $order], 'Order created successfully.', 201);
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
        $payment_history = [
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
            'payment_history' => json_encode($payment_history),
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
        $status_history = [
            'status' => 'PENDING',
            'date' => Carbon::now(),
            'message' => $message
        ];

        $order = Order::create([
            'customer_name' => $request->input('customer_name'),
            'customer_email' => $request->input('customer_email'),
            'customer_phone' => $request->input('customer_phone'),
            'products' => $request->input('products'),
            'total' => $request->input('total'),
            'is_anonymous' => $request->input('is_anonymous'),
            'status' => 'AWAITING_BALANCE',
            'status_history' => json_encode($status_history),
            'payment_receipt' => $request->input('payment_reference'),
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
}
