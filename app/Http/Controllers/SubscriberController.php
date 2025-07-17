<?php

namespace App\Http\Controllers;

use App\Mail\NewsletterSubscriptionConfirmed;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

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

        // Enviar el correo de confirmación
        Mail::to($subscriber->email)->send(new NewsletterSubscriptionConfirmed($subscriber->email));

        return $this->sendResponse($subscriber, 'Suscripción realizada', 201);
    }
}
