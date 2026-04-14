<?php

namespace App\Enums;

enum BacklinkType: string
{
    case Profile = 'profile';
    case GuestPost = 'guest_post';
    case Bookmark = 'bookmark';
    case Forum = 'forum';
    case ForumSignature = 'forum_signature';
    case Comment = 'comment';
    case ResourcePage = 'resource_page';
    case PressRelease = 'press_release';
    case Infographic = 'infographic';
    case Directory = 'directory';
    case Web2 = 'web2';
    case Sidebar = 'sidebar';
    case Footer = 'footer';
    case NewsEditorial = 'news_editorial';
    case PodcastInterview = 'podcast_interview';
    case BusinessListing = 'business_listing';
    case ScholarshipEdu = 'scholarship_edu';
    case BrokenLink = 'broken_link';
    case CompetitorMention = 'competitor_mention';
    case ImageLink = 'image_link';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Profile => 'Profile',
            self::GuestPost => 'Guest post',
            self::Bookmark => 'Bookmark / social',
            self::Forum => 'Forum',
            self::ForumSignature => 'Forum signature',
            self::Comment => 'Comment',
            self::ResourcePage => 'Resource / links page',
            self::PressRelease => 'Press release',
            self::Infographic => 'Infographic',
            self::Directory => 'Directory',
            self::Web2 => 'Web 2.0',
            self::Sidebar => 'Sidebar',
            self::Footer => 'Footer',
            self::NewsEditorial => 'News / editorial',
            self::PodcastInterview => 'Podcast / interview',
            self::BusinessListing => 'Business listing',
            self::ScholarshipEdu => 'Scholarship / edu',
            self::BrokenLink => 'Broken link building',
            self::CompetitorMention => 'Competitor mention',
            self::ImageLink => 'Image link',
            self::Other => 'Other',
        };
    }
}
