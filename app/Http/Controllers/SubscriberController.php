<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use Illuminate\Http\Request;

class SubscriberController extends BaseController
{
    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255|unique:subscribers,email',
        ]);

        $subscriber = Subscriber::create([
            'email' => $request->input('email'),
        ]);

        return $this->sendResponse($subscriber, 'Suscripción realizada', 201);
    }
}
