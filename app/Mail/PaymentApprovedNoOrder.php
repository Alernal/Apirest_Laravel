<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentApprovedNoOrder extends Mailable
{
    use Queueable, SerializesModels;

    public string $name;
    public string $reference;
    public string $transactionId;

    public function __construct(string $name, string $reference, string $transactionId)
    {
        $this->name = $name;
        $this->reference = $reference;
        $this->transactionId = $transactionId;
    }

    public function build()
    {
        return $this->subject('¡Recibimos tu pago, pero hubo un inconveniente!')
            ->markdown('emails.payment.approved_no_order');
    }
}
