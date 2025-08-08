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

    public function __construct(Order $order, string $status, ?string $message = null, ?string $trackingUrl = null)
    {
        $order->loadMissing('orderItems', 'user', 'address');
        $this->order        = $order;
        $this->status       = strtolower($status);
        $this->adminMessage = $message;
        $this->trackingUrl  = $trackingUrl;
    }

    public function build()
    {
        // Texto amigable según status
        $map = [
            'pending'            => 'Pago pendiente',
            'processing'         => 'En preparación',
            'shipped'            => 'Enviado',
            'delivered'          => 'Entregado',
            'cancelled'          => 'Cancelado',
            'failed'             => 'Pago fallido',
            'pending_validation' => 'En validación',
        ];
        $nice = $map[$this->status] ?? ucfirst($this->status);

        // Emoji opcional
        $emojiMap = [
            'pending'            => '⏳',
            'processing'         => '🛠️',
            'shipped'            => '📦',
            'delivered'          => '✅',
            'cancelled'          => '❌',
            'failed'             => '❌',
            'pending_validation' => '🕒',
        ];
        $emoji = $emojiMap[$this->status] ?? 'ℹ️';

        return $this->subject("{$emoji} Tu orden #{$this->order->id}: {$nice}")
            ->view('emails.orders.status-updated')
            ->with([
                'order'        => $this->order,
                'status'       => $this->status,
                'adminMessage' => $this->adminMessage,
                'trackingUrl'  => $this->trackingUrl,
            ]);
    }
}
