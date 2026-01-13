<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    public $table = 'products';

    protected $appends = ['product_image_url'];
    protected $fillable = [
        'category_id',
        'product_name',
        'description',
        'price',
        'original_price',
        'minimum_order_quantity',
        'estimated_shipping_cost',
        'campaign_product',
        'recent_product',
        'image',
        'in_stock',
        'specifications'
    ];

    protected $casts = [
        'recent_product' => 'boolean',
        'campaign_product' => 'boolean',
        'in_stock' => 'boolean',
        'specifications' => 'array',
    ];

    protected $hidden = [
        'id',
        'category_id',
        'image'
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(function ($product) {
            if (empty($product->uuid)) {
                $product->uuid = (string)Str::uuid();
            }
        });
    }

    protected function productImageUrl(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                // Check if heroImage path exists in the database
                if ($attributes['image']) {
                    // Use Storage::url() which is perfect for absolute paths
                    return Storage::disk('public')->url($attributes['image']);
                }
                return null; // or return a default placeholder URL
            },
        );
    }

    public function images()
    {
        return $this->hasMany(ProductImages::class, 'product_id');
    }

}
