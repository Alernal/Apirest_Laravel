<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class OrderStatusUpdated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $order;
    public $status;
    public $adminMessage;
    public $trackingUrl;

    /**
     * Create a new message instance.
     *
     * @param  \App\Models\Order  $order
     * @param  string  $status
     * @param  string|null  $message
     * @param  string|null  $trackingUrl
     */
    public function __construct(Order $order, string $status, ?string $message = null, ?string $trackingUrl = null)
    {
        // Aseguramos que la relación esté disponible
        $order->loadMissing('orderItems', 'user');

        $this->order = $order;
        $this->status = $status;
        $this->adminMessage  = $message;
        $this->trackingUrl = $trackingUrl;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Actualización del estado de tu pedido')
            ->view('emails.orders.status-updated')
            ->with([
                'order' => $this->order,
                'status' => $this->status,
                'adminMessage' => $this->adminMessage,
                'trackingUrl' => $this->trackingUrl,
            ]);
    }
}
