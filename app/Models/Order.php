<?php

namespace App\Models;

use App\Models\Products\Product;
use App\Models\Traits\HasSearch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use HasSearch;

    public $guarded = [];

    protected $with = ['address', 'user', 'statusLogs', 'orderItems'];

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

    public function orderItems()
    {
        return $this->hasMany(OrderProduct::class, 'order_id');
    }


    public function searchFields()
    {
        return [
            'transaction_id',
            'reference',
            'total',
            'user.first_name',
            'user.last_name',
            'user.email',
            'address.street_address',
            'address.city',
            'address.state',
            'address.postal_code',
        ];
    }
}
