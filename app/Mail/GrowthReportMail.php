<?php

namespace App\Mail;

use App\Models\AnalyticsData;
use App\Models\Backlink;
use App\Models\SearchConsoleData;
use App\Models\User;
use App\Models\Website;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GrowthReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public Carbon $reportDate;

    /** @var array{clicks: int, impressions: int, users: int, sessions: int} */
    public array $stats = [];

    /** @var Collection<int, Backlink> */
    public Collection $backlinks;

    public function __construct(
        public User $user,
        public Website $website,
        ?CarbonInterface $reportDate = null,
    ) {
        $this->reportDate = Carbon::parse(
            $reportDate ?? Carbon::yesterday(config('app.timezone'))
        )->startOfDay();

        $day = $this->reportDate->toDateString();
        $websiteId = $this->website->id;

        $this->stats = [
            'clicks' => (int) SearchConsoleData::where('website_id', $websiteId)->whereDate('date', $day)->sum('clicks'),
            'impressions' => (int) SearchConsoleData::where('website_id', $websiteId)->whereDate('date', $day)->sum('impressions'),
            'users' => (int) AnalyticsData::where('website_id', $websiteId)->whereDate('date', $day)->sum('users'),
            'sessions' => (int) AnalyticsData::where('website_id', $websiteId)->whereDate('date', $day)->sum('sessions'),
        ];

        $this->backlinks = Backlink::query()
            ->where('website_id', $websiteId)
            ->whereDate('tracked_date', $day)
            ->orderByDesc('domain_authority')
            ->orderBy('referring_page_url')
            ->limit(100)
            ->get();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'GrowthHub report — '.$this->website->domain.' ('.$this->reportDate->format('M j, Y').')',
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-GrowthHub-Growth-Report-User-Id' => (string) $this->user->id,
                'X-GrowthHub-Growth-Report-Website-Id' => (string) $this->website->id,
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
