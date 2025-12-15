<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ProductOrderBatch extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_order_batches';

    protected $hidden = [
        'id',
        'product_id',
    ];

    protected $fillable = [
        'uuid',
        'product_id',
        'batch_number',
        'shipping_fee',
        'shipping_fee_status',
        'moq_status',
        'orders_collected',
        'moq_value',
        'order_ids'
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted()
    {
        static::creating(function ($productOrderBatch) {
            if (empty($productOrderBatch->uuid)) {
                $productOrderBatch->uuid = (string)Str::uuid();
            }
        });
    }
    protected $casts = [
        'order_ids' => 'array',
    ];

}
