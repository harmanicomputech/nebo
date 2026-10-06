<?php

namespace App\Mail;

use App\Models\EventRequest;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Confirmation to the customer. Contains only what they submitted, never internal data. */
class RequestReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public EventRequest $request) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'We received your production request '.$this->request->reference);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.request-received', with: [
            'request' => $this->request,
            'company' => Settings::string('company.name'),
            'trackUrl' => route('requests.track', $this->request->public_token),
        ]);
    }
}
