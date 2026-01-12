<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductImages extends Model
{
    use HasFactory;
    public $table = 'product_images';

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
}
