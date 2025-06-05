<?php

namespace App\Models;

use App\Models\Products\Product;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public $guarded = [];

    public function products()
    {
        return $this->belongsToMany(Product::class)
            ->withPivot('product_name', 'price', 'quantity', 'total')
            ->withTimestamps();
    }
}
