<?php

namespace App\Models\Products;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'url'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
