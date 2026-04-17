<?php

namespace Tests\Unit;

use App\Models\Website;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WebsiteAuditUrlTest extends TestCase
{
    public static function allowedAuditUrlProvider(): array
    {
        return [
            'exact host' => ['example.com', 'https://example.com/path'],
            'www on url' => ['example.com', 'https://www.example.com/'],
            'www on domain field' => ['www.example.com', 'https://example.com/x'],
            'subdomain' => ['example.com', 'https://blog.example.com/post'],
        ];
    }

    #[DataProvider('allowedAuditUrlProvider')]
    public function test_is_audit_url_accepts_same_site_hosts(string $storedDomain, string $url): void
    {
        $site = new Website(['domain' => $storedDomain]);

        $this->assertTrue($site->isAuditUrlForThisSite($url));
    }

    public static function rejectedAuditUrlProvider(): array
    {
        return [
            'other tld' => ['example.com', 'https://example.org/'],
            'suffix trap' => ['ample.com', 'https://example.com/'],
            'empty domain' => ['', 'https://example.com/'],
            'invalid url' => ['example.com', 'not a url'],
        ];
    }

    #[DataProvider('rejectedAuditUrlProvider')]
    public function test_is_audit_url_rejects_foreign_or_invalid(string $storedDomain, string $url): void
    {
        $site = new Website(['domain' => $storedDomain]);

        $this->assertFalse($site->isAuditUrlForThisSite($url));
    }
}
