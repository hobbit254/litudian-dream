<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Category extends Model
{
    use SoftDeletes, HasFactory;

    public $table = 'categories';
    protected $appends = ['hero_image_url'];

    protected $fillable = [
        'category_name',
        'slug',
        'description',
        'heroImage',
        'tagline',
        'uuid'
    ];

    protected $hidden = [
        'id',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted()
    {
        static::creating(function ($category) {
            if (empty($category->uuid)) {
                $category->uuid = (string)Str::uuid();
            }
        });
    }

    /**
     * Define the accessor for the full hero image URL.
     * Use the url() method to get the absolute path.
     */
    protected function heroImageUrl(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                // Check if heroImage path exists in the database
                if ($attributes['heroImage']) {
                    // Use Storage::url() which is perfect for absolute paths
                    return Storage::disk('public')->url($attributes['heroImage']);
                }
                return null; // or return a default placeholder URL
            },
        );
    }
}
