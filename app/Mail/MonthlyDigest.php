<?php
namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class MonthlyDigest extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public array $digest) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'The Red Star — '.now()->format('F Y').' digest');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.digest', with: [
            'user' => $this->user, 'd' => $this->digest,
            'unsubscribeUrl' => URL::signedRoute('digest.unsubscribe', ['user' => $this->user->id]),
        ]);
    }

    public function headers(): \Illuminate\Mail\Mailables\Headers
    {
        return new \Illuminate\Mail\Mailables\Headers(text: [
            'List-Unsubscribe' => '<'.URL::signedRoute('digest.unsubscribe', ['user' => $this->user->id]).'>',
        ]);
    }
}
