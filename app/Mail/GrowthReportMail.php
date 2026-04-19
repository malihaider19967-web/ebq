<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Website;
use App\Services\ReportDataService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class GrowthReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $startDate;

    public string $endDate;

    public string $reportType;

    public array $report = [];

    public function __construct(
        public User $user,
        public Website $website,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $reportType = null,
    ) {
        $tz = $user->timezoneForDisplay();

        $this->endDate = $endDate ?? Carbon::yesterday($tz)->toDateString();
        $this->startDate = $startDate ?? $this->endDate;
        $this->reportType = $reportType ?? 'daily';

        $this->report = app(ReportDataService::class)->generate(
            $this->website->id,
            $this->startDate,
            $this->endDate,
        );
    }

    public function envelope(): Envelope
    {
        $typeLabel = ucfirst($this->reportType);
        $tz = $this->user->timezoneForDisplay();
        $start = Carbon::parse($this->startDate, $tz);
        $end = Carbon::parse($this->endDate, $tz);

        $dateStr = $start->eq($end)
            ? $start->format('M j, Y')
            : $start->format('M j').' - '.$end->format('M j, Y');

        return new Envelope(
            subject: "EBQ {$typeLabel} Report — {$this->website->domain} ({$dateStr})",
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-EBQ-Growth-Report-User-Id' => (string) $this->user->id,
                'X-EBQ-Growth-Report-Website-Id' => (string) $this->website->id,
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.growth-report',
        );
    }
}
