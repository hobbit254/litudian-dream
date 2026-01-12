<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Reviews extends Model
{
    use HasFactory;

    public $table = 'reviews';
    protected $fillable = [
        'product_id',
        'review_name',
        'review_text',
        'review_image',
        'rating',
        'status',
    ];

    protected $hidden = [
        'id',
        'product_id',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(function ($review) {
            if (empty($review->uuid)) {
                $review->uuid = (string)Str::uuid();
            }
        });
    }

}
