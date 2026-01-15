<?php

namespace App\Http\Controllers;

use App\Http\helpers\ResponseHelper;
use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function allPayments(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $startDate = $request->input('start_date', Carbon::today());
        $endDate = $request->input('end_date', Carbon::today());
        $order_number = $request->input('order_number');
        $payment_status = $request->input('payment_status');
        $payment_method = $request->input('payment_method');

        $query = Payment::query();
        $query->select([
            'payments.*',
            'orders.order_number',
            'orders.customer_name',
            'orders.customer_phone',
            'orders.total as orders_total_price',
            'orders.payment_receipt',
            'orders.shipping_fee',
            'orders.shipping_payment_receipt',
            'orders.total_with_shipping',
            // 👇 Add total_paid column: sum of verified payments for this order
            DB::raw('(SELECT SUM(p.payment_amount)
                  FROM payments p
                  WHERE p.order_id = orders.id
                    AND p.payment_status = "VERIFIED") as total_paid')
        ])
            ->join('orders', 'orders.id', '=', 'payments.order_id');

        $query->when($startDate, function ($q) use ($startDate) {
            $q->whereDate('payments.created_at', '>=', $startDate);
        });

        $query->when($endDate, function ($q) use ($endDate) {
            $q->whereDate('payments.created_at', '<=', $endDate);
        });

        $query->when($order_number, function ($q) use ($order_number) {
            $q->where('orders.order_number', $order_number);
        });

        $query->when($payment_status, function ($q) use ($payment_status) {
            $q->where('payments.payment_status', $payment_status);
        });

        $query->when($payment_method, function ($q) use ($payment_method) {
            $q->where('payments.payment_method', $payment_method);
        });

        $query->orderBy('payments.created_at', 'desc');

        $payments = $query->paginate($perPage);
        $nextPageUrl = $payments->nextPageUrl();
        $data = $payments->items();
        $meta = [
            'total' => $payments->total(),
            'perPage' => $payments->perPage(),
            'currentPage' => $payments->currentPage(),
            'lastPage' => $payments->lastPage(),
            'from' => $payments->firstItem(),
            'to' => $payments->lastItem(),
            'nextPageUrl' => $nextPageUrl,
            'hasMorePages' => $payments->hasMorePages(),
        ];
        return ResponseHelper::success(['data' => $data, 'meta' => $meta], 'Payments retrieved successfully.', 200);
    }

    public function createPayment(Request $request): JsonResponse
    {
        $request->validate([
            'order_number' => 'required',
            'customer_phone' => 'required',
            'amount_paid' => 'required|numeric',
            'payment_reference' => 'required|unique:payments,merchant_ref',
            'payment_type' => 'required',
            'payment_method' => 'required',
        ]);

        $order = Order::where(['order_number' => $request->input('order_number')])->first();

        if (!$order) {
            return ResponseHelper::error([], 'Order not found.', 400);
        }
        $payment_history[] = [
            'status' => 'UNVERIFIED',
            'date' => Carbon::now(),
            'message' => 'Payment received successfully awaiting confirmation by the administrator based on the payment ref passed.',
        ];

        $payment = Payment::create([
            'order_id' => $order->id,
            'payment_method' => $request->input('payment_method'),
            'phone_number' => $request->input('customer_phone'),
            'payment_amount' => $request->input('amount_paid'),
            'payment_status' => 'UNVERIFIED',
            'payment_unique_ref' => Str::uuid(),
            'payment_history' => $payment_history,
            'merchant_ref' => $request->input('payment_reference'),
            'payment_type' => $request->input('payment_type'),
        ]);
        $payment->save();

        $newReceipt = $request->input('payment_reference');
        if ($request->input('payment_type') === 'SHIPPING_FEE') {
            if (!empty($order->shipping_payment_receipt)) {
                $order->shipping_payment_receipt .= ',' . $newReceipt;
            } else {
                $order->shipping_payment_receipt = $newReceipt;
            }
        } else {
            if (!empty($order->payment_receipt)) {
                $order->payment_receipt .= ',' . $newReceipt;
            } else {
                $order->payment_receipt = $newReceipt;
            }
        }
        $order->save();

        return ResponseHelper::success(['data' => $payment], 'Payment created successfully.', 201);
    }

    public function updatePaymentStatus(Request $request): JsonResponse
    {
        $request->validate([
            'id'             => 'required',
            'payment_status' => 'required',
            'payment_method' => 'required',
        ]);

        try {
            $payment = Payment::find($request->input('id'));


            if (!$payment) {
                return ResponseHelper::error([], 'Payment not found.', 400);
            } elseif ($payment->payment_status === 'VERIFIED') {
                return ResponseHelper::error([], 'Payment already verified.', 400);
            }elseif ($payment->payment_status === 'REJECTED') {
                return ResponseHelper::error([], 'Payment already rejected.', 400);
            }

            $order = Payment::select('orders.*')
                ->join('orders', 'orders.id', '=', 'payments.order_id')
                ->where('payments.id', $request->input('id'))
                ->first();

            if (!$order) {
                return ResponseHelper::error([], 'Order not found.', 400);
            }

            // Get existing history (already cast to array)
            $existing_history = $payment->payment_history ?? [];

            // Append new entry
            $existing_history[] = [
                'status'  => request()->input('payment_status'),
                'date'    => Carbon::now()->toDateTimeString(),
                'message' => 'Payment has been '. $request->input('payment_status') . ' by the administrator based on the payment ref passed.',
            ];

            // Update payment
            $payment->payment_history = $existing_history; // ✅ no encoding
            $payment->payment_status  = request()->input('payment_status');

            if ($request->filled('payment_reference')) {
                $payment->merchant_ref = $request->input('payment_reference');
            }
            if ($request->filled('payment_method')) {
                $payment->payment_method = $request->input('payment_method');
            }

            $payment->save();

            $ord = Order::where(['id' => $order->id])->first();

            // Check if order is fully paid
            $total = Payment::where('order_id', $order->id)
                ->where('payment_status', 'VERIFIED')
                ->sum('payment_amount');

            if ($payment->payment_type === 'SHIPPING_FEE') {
                if ($total >= $ord->shipping_fee) {
                    $existing_history = $ord->status_history ?? [];
                    $existing_history[] = [
                        'status'  => 'SHIPPING_FEE_PAID',
                        'date'    => Carbon::now()->toDateTimeString(),
                        'message' => 'Shipping fee for the order has been paid and approved by the administrator based on the payment ref passed.',
                    ];
                    $ord->status_history = $existing_history;
                    $ord->shipping_payment_status = 'PAID';
                    $ord->status = 'SHIPPING_FEE_PAID';
                    $ord->save();
                }
            }else{
                if ($total >= $ord->total) {
                    $existing_history = $ord->status_history ?? [];
                    $existing_history[] = [
                        'status'  => 'BALANCE_PAID',
                        'date'    => Carbon::now()->toDateTimeString(),
                        'message' => 'Payment for the order has been paid and approved by the administrator based on the payment ref passed.',
                    ];
                    $ord->status_history = $existing_history;
                    $ord->product_payment_status = 'PAID';
                    $ord->status = 'AWAITING_SHIPPING_FEE';
                    $ord->save();
                }
            }


            return ResponseHelper::success(['data' => $payment], 'Payment status updated successfully.', 200);
        }catch (\Exception $exception){
            return ResponseHelper::error([], 'Something went wrong when trying to save the data.', 500);
        }
    }

}
