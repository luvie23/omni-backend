<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContractorImportSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $createdUsers,
        public array $errors,
        public string $excelPath,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'OMNI Contractor Import Summary',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contractor-import-summary',
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->excelPath)
                ->as('contractor-import-results.xlsx')
                ->withMime(
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                ),
        ];
    }
}
