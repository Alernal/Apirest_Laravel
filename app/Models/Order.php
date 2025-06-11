<?php

namespace App\Models;

use App\Models\Products\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    public $guarded = [];

    protected $with = ['address', 'user', 'statusLogs'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function statusLogs()
    {
        return $this->hasMany(OrderStatusHistory::class, 'order_id')->with('user');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class)
            ->withPivot('product_name', 'price', 'quantity', 'total')
            ->withTimestamps();
    }
}
