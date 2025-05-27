<?php

namespace App\Models\Products;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $guarded = [];

    protected $with = ['images', 'features', 'discount'];

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function features(): HasMany
    {
        return $this->hasMany(ProductFeature::class);
    }

    public function discount()
    {
        return $this->hasOne(ProductDiscount::class);
    }
}
