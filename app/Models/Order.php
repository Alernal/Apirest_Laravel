<?php

namespace App\Models;

use App\Models\Products\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    public $guarded = [];

    protected $with = ['address'];

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function statusLogs()
    {
        return $this->hasMany(DB::raw('order_status_histories'), 'order_id');
    }


    public function products()
    {
        return $this->belongsToMany(Product::class)
            ->withPivot('product_name', 'price', 'quantity', 'total')
            ->withTimestamps();
    }
}
