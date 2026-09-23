<?php

namespace App\Mail;

use App\Models\QuotationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewQuotationRequest extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public QuotationRequest $quotationRequest
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'OMNI RGB Inquiry - ' . $this->quotationRequest->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-quotation-request',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
