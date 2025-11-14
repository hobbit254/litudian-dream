<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentSchedule extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'payment_schedule';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'deposit_amount',
        'deposit_paid',
        'deposit_due_date',
        'deposit_receipt',
        'balance_amount',
        'balance_paid',
        'balance_due_date',
        'balance_receipt',
        'shipping_amount',
        'shipping_paid',
        'shipping_due_date',
        'shipping_receipt',
        'service_fee',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // Boolean fields are automatically cast to boolean
        'deposit_paid' => 'boolean',
        'balance_paid' => 'boolean',
        'shipping_paid' => 'boolean',

        // Date fields are automatically cast to Carbon instances
        'deposit_due_date' => 'date',
        'balance_due_date' => 'date',
        'shipping_due_date' => 'date',

        // Numeric fields saved as decimals/floats
        'deposit_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'service_fee' => 'decimal:2',
    ];
}
