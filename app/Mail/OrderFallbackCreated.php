<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class OrderFallbackCreated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $order;

    public function __construct(Order $order)
    {
        $order->loadMissing('user');
        $this->order = $order;
    }

    public function build()
    {
        return $this->subject('Tu pago fue recibido – Estamos revisando tu orden')
            ->view('emails.orders.fallback-created')
            ->with([
                'order' => $this->order,
            ]);
    }
}
