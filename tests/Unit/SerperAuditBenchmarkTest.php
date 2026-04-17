<?php

namespace Tests\Unit;

use App\Services\PageAuditService;
use App\Services\SerperSearchClient;
use App\Support\Audit\RecommendationEngine;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class SerperAuditBenchmarkTest extends TestCase
{
    protected function tearDown(): void
    {
        Http::fake();
        Mockery::close();

        parent::tearDown();
    }

    public function test_page_audit_benchmark_includes_competitor_flesch_scores(): void
    {
        Config::set('services.serper.key', 'test-serper-key');
        Config::set('services.serper.search_url', 'https://google.serper.dev/search');

        $html = '<!DOCTYPE html><html><head><title>Comp</title></head><body><p>'
            .str_repeat('The quick brown fox jumps over the lazy dog. ', 50)
            .'</p></body></html>';

        Http::fake(function (Request $request) use ($html) {
            $url = $request->url();
            if (str_contains($url, 'serper.dev/search')) {
                return Http::response([
                    'organic' => [
                        ['link' => 'https://alpha-comp.test/p1', 'title' => 'Alpha', 'position' => 1],
                        ['link' => 'https://beta-comp.test/p2', 'title' => 'Beta', 'position' => 2],
                        ['link' => 'https://gamma-comp.test/p3', 'title' => 'Gamma', 'position' => 3],
                    ],
                ], 200);
            }
            if (in_array($url, [
                'https://alpha-comp.test/p1',
                'https://beta-comp.test/p2',
                'https://gamma-comp.test/p3',
            ], true)) {
                return Http::response($html, 200, ['Content-Type' => 'text/html']);
            }

            return Http::response('unexpected URL: '.$url, 500);
        });

        $svc = new PageAuditService;
        $method = new ReflectionMethod(PageAuditService::class, 'buildSerperReadabilityBenchmark');
        $method->setAccessible(true);

        $keywords = [
            'available' => true,
            'primary' => ['query' => 'sample keyword'],
        ];

        $bench = $method->invoke($svc, 'https://audited-site.test/article', $keywords, 55.0);

        $this->assertIsArray($bench);
        $this->assertSame('sample keyword', $bench['keyword']);
        $this->assertSame('serper', $bench['source']);
        $this->assertSame(55.0, $bench['your_flesch']);
        $this->assertCount(3, $bench['competitors']);
        foreach ($bench['competitors'] as $row) {
            $this->assertArrayHasKey('url', $row);
            $this->assertArrayHasKey('flesch', $row);
            $this->assertIsNumeric($row['flesch']);
        }
        $this->assertArrayHasKey('skipped_reason', $bench);
        $this->assertNull($bench['skipped_reason']);
    }

    public function test_readability_benchmark_recommendation_when_flesch_lags_median(): void
    {
        $engine = new RecommendationEngine;
        $method = new ReflectionMethod(RecommendationEngine::class, 'readabilityBenchmark');
        $method->setAccessible(true);

        $below = $method->invoke($engine, [
            'benchmark' => [
                'keyword' => 'widgets',
                'your_flesch' => 40.0,
                'competitors' => [
                    ['flesch' => 70.0],
                    ['flesch' => 90.0],
                ],
            ],
        ]);
        $ids = array_column($below, 'id');
        $this->assertContains('bench.readability.below_median', $ids);
        $this->assertSame(RecommendationEngine::SEV_INFO, $below[0]['severity'] ?? null);

        $ok = $method->invoke($engine, [
            'benchmark' => [
                'keyword' => 'widgets',
                'your_flesch' => 72.0,
                'competitors' => [
                    ['flesch' => 70.0],
                    ['flesch' => 90.0],
                ],
            ],
        ]);
        $this->assertSame([], $ok);
    }

    public function test_benchmark_returns_error_payload_when_serper_client_throws(): void
    {
        Config::set('services.serper.key', 'test-key');

        $mock = Mockery::mock(SerperSearchClient::class);
        $mock->shouldReceive('search')->once()->andThrow(new RuntimeException('simulated Serper failure'));
        $this->app->instance(SerperSearchClient::class, $mock);

        $svc = new PageAuditService;
        $method = new ReflectionMethod(PageAuditService::class, 'buildSerperReadabilityBenchmark');
        $method->setAccessible(true);

        $bench = $method->invoke($svc, 'https://audited-site.test/page', [
            'available' => true,
            'primary' => ['query' => 'primary query'],
        ], 62.5);

        $this->assertIsArray($bench);
        $this->assertSame('benchmark_error', $bench['skipped_reason']);
        $this->assertSame('primary query', $bench['keyword']);
        $this->assertSame([], $bench['competitors']);
        $this->assertSame(62.5, $bench['your_flesch']);
    }
}
