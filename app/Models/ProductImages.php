<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImages extends Model
{
    use HasFactory;
    public $table = 'product_images';
    protected $appends = ['product_image_url'];

    protected $fillable = [
        'product_id',
        'product_image',
    ];

    protected $hidden = [
        'product_id',
        'id'
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(function ($productImage) {
            if (empty($productImage->uuid)) {
                $productImage->uuid = (string)Str::uuid();
            }
        });
    }

    protected function productImageUrl(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                // Check if heroImage path exists in the database
                if ($attributes['product_image']) {
                    // Use Storage::url() which is perfect for absolute paths
                    return Storage::disk('public')->url($attributes['product_image']);
                }
                return null; // or return a default placeholder URL
            },
        );
    }
}
