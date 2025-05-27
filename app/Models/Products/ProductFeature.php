<?php

namespace App\Models\Products;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductFeature extends Model
{
    protected $fillable = ['product_id', 'feature'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
