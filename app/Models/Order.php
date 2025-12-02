<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';
    protected $fillable = [
        'order_number',
        'customer_name',
        'customer_email',
        'customer_phone',
        'products',
        'status',
        'total',
        'is_anonymous',
        'status_history',
        'uuid',
        'payment_receipt',
        'shipping_fee',
        'product_payment_status',
        'shipping_payment_status',
        'shipping_payment_receipt',
        'shipping_verification_status',
        'total_with_shipping',
        'moq_status'
    ];

    protected $hidden = [
        'id',
    ];

    protected $casts = [
        'products' => 'array',
        'status_history' => 'array',
        'is_anonymous' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted()
    {
        static::creating(function ($order) {
            if (empty($order->uuid)) {
                $order->uuid = (string)Str::uuid();
            }
        });
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = 'ORD-' . strtoupper(Str::random(10));
            }
        });
    }
}
