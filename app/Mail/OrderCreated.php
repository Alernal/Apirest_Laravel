<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderCreated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $order;

    public function __construct(Order $order)
    {
        // Carga relaciones necesarias para el correo
        $order->loadMissing('user', 'orderItems');
        $this->order = $order;
    }

    public function build()
    {
        $metodo = $this->order->payment_method === 'contraentrega'
            ? '🛒 (Contraentrega)'
            : '💳 (Pago en línea)';

        return $this->subject('🎉 ¡Tu orden #' . $this->order->id . " ha sido recibida! $metodo")
            ->view('emails.orders.created')
            ->with([
                'order' => $this->order,
            ]);
    }
}
