<?php

namespace App\Mail;

use App\Models\AnalyticsData;
use App\Models\SearchConsoleData;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class GrowthReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $stats = [];

    public function __construct(public User $user)
    {
        $websiteIds = $user->websites()->pluck('id');

        $this->stats = [
            'clicks' => (int) SearchConsoleData::whereIn('website_id', $websiteIds)->sum('clicks'),
            'impressions' => (int) SearchConsoleData::whereIn('website_id', $websiteIds)->sum('impressions'),
            'users' => (int) AnalyticsData::whereIn('website_id', $websiteIds)->sum('users'),
            'sessions' => (int) AnalyticsData::whereIn('website_id', $websiteIds)->sum('sessions'),
            'websites' => $user->websites()->pluck('domain')->toArray(),
        ];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your GrowthHub Daily Report',
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-GrowthHub-Growth-Report-User-Id' => (string) $this->user->id,
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
