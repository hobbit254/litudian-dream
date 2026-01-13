<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    public $table = 'payments';

    protected $fillable = [
        'order_id',
        'payment_method',
        'phone_number',
        'payment_amount',
        'payment_status',
        'payment_unique_ref',
        'payment_history',
        'merchant_ref'
    ];

    protected $casts = [
        'payment_history' => 'array',
    ];

}
