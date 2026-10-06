<?php

namespace App\Mail;

use App\Models\Quotation;
use App\Support\Format;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The customer's quotation link. Totals only; the lines are on the page. */
class QuotationReady extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Quotation $quotation) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your quotation '.$this->quotation->reference.' from '.Settings::string('company.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.quotation-ready', with: [
            'quotation' => $this->quotation->loadMissing('customer'),
            'company' => Settings::string('company.name'),
            'total' => Format::naira($this->quotation->total_kobo),
            'url' => route('quotations.public', $this->quotation->public_token),
        ]);
    }
}
