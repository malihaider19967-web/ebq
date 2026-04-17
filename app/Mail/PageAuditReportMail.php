<?php

namespace App\Mail;

use App\Models\PageAuditReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PageAuditReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PageAuditReport $auditReport) {}

    public function envelope(): Envelope
    {
        $host = parse_url($this->auditReport->page, PHP_URL_HOST) ?? $this->auditReport->page;

        return new Envelope(
            subject: "EBQ Page Audit — {$host}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'pages.partials.audit-report-export',
            with: ['auditReport' => $this->auditReport],
        );
    }
}
