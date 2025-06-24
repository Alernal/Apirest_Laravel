<?php

namespace App\Models\Products;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Traits\HasSearch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasSearch;

    protected $guarded = [];

    protected $with = ['images', 'reviews'];

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function carts(): BelongsToMany
    {
        return $this->belongsToMany(Cart::class, 'cart_product')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class)
            ->withPivot('product_name', 'price', 'quantity', 'total')
            ->withTimestamps();
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    public function searchFields()
    {
        return ['name', 'price', 'description'];
    }
}
