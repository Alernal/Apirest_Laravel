<?php

namespace App\Models\Products;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $guarded = [];

    protected $with = ['images'];

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }
}
