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

    public array $insights = [];

    public function __construct(
        public User $user,
        public Website $website,
        string $startDate,
        string $endDate,
        string $reportType = 'daily',
    ) {
        // Callers must pre-resolve the report window via
        // ReportDataService::lastSafeReportDate(). The Mailable
        // does not retry that lookup here: throwing from a queued
        // mailable's constructor aborts the surrounding chunkById
        // batch on the dispatcher, and silently substituting a
        // fallback date would mask sync failures.
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->reportType = $reportType;

        $service = app(ReportDataService::class);
        $this->report = $service->generate(
            $this->website->id,
            $this->startDate,
            $this->endDate,
        );

        $this->insights = [
            'cannibalization' => array_slice($service->cannibalizationReport($this->website->id), 0, 5),
            'striking_distance' => array_slice($service->strikingDistance($this->website->id), 0, 5),
            'indexing_fails_with_traffic' => array_slice($service->indexingFailsWithTraffic($this->website->id), 0, 5),
        ];
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
