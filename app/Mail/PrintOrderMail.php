<?php
namespace App\Mail;

use App\Models\PrintOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PrintOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PrintOrder $order, public string $headline) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "The Red Star — {$this->headline} (order #{$this->order->id})");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.print-order', with: ['o' => $this->order, 'headline' => $this->headline]);
    }
}
