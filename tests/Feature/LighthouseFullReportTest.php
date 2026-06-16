<?php

namespace Tests\Feature;

use App\Services\LighthouseClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LighthouseFullReportTest extends TestCase
{
    private function fakeLhr(): array
    {
        return [
            'lighthouseVersion' => '12.3.0',
            'categories' => [
                'performance' => ['score' => 0.75, 'auditRefs' => [
                    ['id' => 'largest-contentful-paint', 'group' => 'metrics'],
                    ['id' => 'render-blocking-resources', 'group' => 'diagnostics'],
                    ['id' => 'uses-long-cache-ttl', 'group' => 'diagnostics'],
                ]],
                'accessibility' => ['score' => 0.9, 'auditRefs' => [['id' => 'image-alt'], ['id' => 'document-title']]],
                'best-practices' => ['score' => 1.0, 'auditRefs' => [['id' => 'is-on-https']]],
                'seo' => ['score' => 0.8, 'auditRefs' => [['id' => 'meta-description']]],
            ],
            'audits' => [
                'first-contentful-paint' => ['title' => 'FCP', 'score' => 0.9, 'displayValue' => '1.2 s'],
                'largest-contentful-paint' => ['title' => 'LCP', 'score' => 0.5, 'displayValue' => '3.1 s'],
                'total-blocking-time' => ['title' => 'TBT', 'score' => 0.3, 'displayValue' => '600 ms'],
                'cumulative-layout-shift' => ['title' => 'CLS', 'score' => 1, 'displayValue' => '0.02'],
                'speed-index' => ['title' => 'SI', 'score' => 0.7, 'displayValue' => '2.5 s'],
                'interactive' => ['title' => 'TTI', 'score' => 0.6, 'displayValue' => '3.8 s'],
                'render-blocking-resources' => [
                    'title' => 'Eliminate render-blocking resources', 'score' => 0.4,
                    'displayValue' => 'Potential savings of 500 ms',
                    'description' => 'Resources are blocking. [Learn more](https://web.dev/x)',
                    'details' => [
                        'type' => 'opportunity',
                        'overallSavingsMs' => 500,
                        'headings' => [
                            ['key' => 'url', 'valueType' => 'url', 'label' => 'URL'],
                            ['key' => 'totalBytes', 'valueType' => 'bytes', 'label' => 'Size'],
                            ['key' => 'wastedMs', 'valueType' => 'timespanMs', 'label' => 'Savings'],
                        ],
                        'items' => [
                            ['url' => 'https://example.com/app.css', 'totalBytes' => 20480, 'wastedMs' => 300],
                            ['url' => 'https://example.com/vendor.js', 'totalBytes' => 51200, 'wastedMs' => 200],
                        ],
                    ],
                ],
                'uses-long-cache-ttl' => [
                    'title' => 'Serve static assets with an efficient cache policy', 'score' => 0.5,
                    'displayValue' => '10 resources found', 'details' => ['type' => 'table'],
                ],
                'image-alt' => ['title' => 'Image elements have [alt] attributes', 'score' => 0, 'description' => 'Add alt text.'],
                'document-title' => ['title' => 'Document has a title', 'score' => 1],
                'is-on-https' => ['title' => 'Uses HTTPS', 'score' => 1],
                'meta-description' => ['title' => 'Document has a meta description', 'score' => 0, 'description' => 'Add one.'],
                'final-screenshot' => ['details' => ['type' => 'screenshot', 'data' => 'data:image/jpeg;base64,QUJD']],
            ],
        ];
    }

    public function test_full_report_parses_scores_metrics_opportunities_and_screenshot(): void
    {
        config(['services.lighthouse.url' => 'http://lh.test', 'services.lighthouse.key' => 'k', 'services.lighthouse.timeout' => 90]);

        Http::fake(['lh.test/*' => Http::response(['raw' => $this->fakeLhr()], 200)]);

        $report = app(LighthouseClient::class)->fetchFullReport('https://example.com');

        $this->assertIsArray($report);
        $m = $report['mobile'];

        // Category gauges
        $this->assertSame(75, $m['scores']['performance']);
        $this->assertSame(90, $m['scores']['accessibility']);
        $this->assertSame(100, $m['scores']['best_practices']);
        $this->assertSame(80, $m['scores']['seo']);

        // Metrics with PSI severity buckets
        $this->assertCount(6, $m['metrics']);
        $lcp = collect($m['metrics'])->firstWhere('key', 'lcp');
        $this->assertSame('average', $lcp['rating']);
        $this->assertSame('3.1 s', $lcp['display']);
        $this->assertSame('poor', collect($m['metrics'])->firstWhere('key', 'tbt')['rating']);
        $this->assertSame('good', collect($m['metrics'])->firstWhere('key', 'cls')['rating']);

        // Opportunities: the render-blocking audit, markdown stripped
        $this->assertCount(1, $m['opportunities']);
        $op = $m['opportunities'][0];
        $this->assertSame(500, $op['savings_ms']);
        $this->assertSame('Resources are blocking. Learn more', $op['description']);

        // …and the actual offending resources are extracted as a table.
        $res = $op['resources'];
        $this->assertSame(['URL', 'Size', 'Savings'], array_column($res['columns'], 'label'));
        $this->assertCount(2, $res['rows']);
        $this->assertSame('https://example.com/app.css', $res['rows'][0][0]['text']);
        $this->assertTrue($res['rows'][0][0]['is_url']);
        $this->assertSame('20.0 KB', $res['rows'][0][1]['text']);
        $this->assertSame('300 ms', $res['rows'][0][2]['text']);
        $this->assertFalse($res['truncated']);

        // Diagnostics: the non-opportunity perf diag audit only
        $this->assertCount(1, $m['diagnostics']);
        $this->assertSame('Serve static assets with an efficient cache policy', $m['diagnostics'][0]['title']);

        // Failed audits per category
        $this->assertCount(1, $m['failed_audits']['accessibility']); // image-alt failed, document-title passed
        $this->assertCount(0, $m['failed_audits']['best_practices']);
        $this->assertCount(1, $m['failed_audits']['seo']);

        // Screenshot passed through
        $this->assertStringStartsWith('data:image', $m['screenshot']);

        // Both strategies parsed
        $this->assertIsArray($report['desktop']);
        $this->assertSame('12.3.0', $report['lighthouse_version']);
    }

    public function test_returns_null_when_service_unconfigured(): void
    {
        config(['services.lighthouse.url' => '', 'services.lighthouse.key' => '']);

        $this->assertNull(app(LighthouseClient::class)->fetchFullReport('https://example.com'));
    }
}
