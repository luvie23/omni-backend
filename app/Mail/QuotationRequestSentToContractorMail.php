<?php

namespace App\Mail;

use App\Models\QuotationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class QuotationRequestSentToContractorMail extends Mailable
{
    use Queueable, SerializesModels;

    public QuotationRequest $quote;
    public object $contractor;

    public function __construct(QuotationRequest $quote, object $contractor)
    {
        $this->quote = $quote;
        $this->contractor = $contractor;
    }

    public function build()
    {
        return $this->subject('New Quotation Request')
            ->view('emails.quotation-request-sent-to-contractor');
    }
}
