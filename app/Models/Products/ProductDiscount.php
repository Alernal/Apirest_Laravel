<?php

namespace App\Models\Products;

use Illuminate\Database\Eloquent\Model;

class ProductDiscount extends Model
{
    protected $fillable = ['product_id', 'discount_value', 'discount_type', 'start_date', 'end_date'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
