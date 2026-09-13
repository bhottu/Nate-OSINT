<?php

namespace Tests\Feature;

use App\Models\DomainIntelligence\DomainScan;
use App\Services\DomainIntelligence\Support\DomainNormalizationService;
use App\Services\DomainIntelligence\Support\SsrfGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DomainIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_page_loads(): void
    {
        $this->get('/domain-intelligence')->assertOk()->assertSee('DOMAIN INTELLIGENCE');
    }

    public function test_scan_requires_hostname(): void
    {
        $this->post('/domain-intelligence/scan', [])->assertSessionHasErrors('hostname');
    }

    public function test_normalization_accepts_urls_and_domains(): void
    {
        $normalizer = app(DomainNormalizationService::class);

        foreach (['https://example.com', 'example.com', 'www.example.com/', 'EXAMPLE.COM'] as $input) {
            $result = $normalizer->normalize($input);
            $this->assertSame('example.com', $result['domain'], "Failed normalizing: {$input}");
        }
    }

    public function test_normalization_rejects_private_and_invalid_targets(): void
    {
        $normalizer = app(DomainNormalizationService::class);

        foreach (['localhost', '127.0.0.1', '10.0.0.1', '169.254.169.254', 'not a domain', ''] as $input) {
            try {
                $normalizer->normalize($input);
                $this->fail("Expected rejection for: {$input}");
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_ssrf_guard_blocks_private_and_metadata_targets(): void
    {
        $guard = app(SsrfGuard::class);

        foreach (['127.0.0.1', '10.0.0.1', '192.168.1.1', '169.254.169.254', '::1', 'localhost'] as $target) {
            try {
                $guard->assertHostSafe($target);
                $this->fail("Expected rejection for: {$target}");
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_scan_persists_completed_report(): void
    {
        $this->mock(\App\Services\DomainIntelligence\DomainScanOrchestrator::class, function ($mock) {
            $scan = DomainScan::create([
                'domain' => 'example.com',
                'hostname' => 'example.com',
                'input' => 'example.com',
                'status' => 'COMPLETED',
                'score' => 78,
                'grade' => 'B',
                'posture' => 'NEEDS ATTENTION',
            ]);
            $mock->shouldReceive('scan')->once()->andReturn($scan);
        });

        $response = $this->post('/domain-intelligence/scan', ['hostname' => 'example.com']);
        $scan = DomainScan::first();
        $response->assertRedirect(route('domain-intelligence.show', $scan));
        $this->assertSame('COMPLETED', $scan->status);
        $this->assertSame(78, $scan->score);
    }

    public function test_scan_failure_is_reported_to_user(): void
    {
        $this->mock(\App\Services\DomainIntelligence\DomainScanOrchestrator::class, function ($mock) {
            $mock->shouldReceive('scan')->once()->andThrow(new RuntimeException('Target is not resolvable.'));
        });

        $this->post('/domain-intelligence/scan', ['hostname' => 'example.com'])
            ->assertSessionHasErrors('hostname');
    }

    public function test_show_page_renders_report(): void
    {
        $scan = DomainScan::create([
            'domain' => 'example.com',
            'hostname' => 'example.com',
            'input' => 'example.com',
            'status' => 'COMPLETED',
            'score' => 78,
            'grade' => 'B',
            'posture' => 'NEEDS ATTENTION',
            'summary' => ['subdomains' => 0, 'certificates' => 0, 'ips' => 0, 'findings' => 0, 'errors' => []],
        ]);

        $this->get(route('domain-intelligence.show', $scan))
            ->assertOk()
            ->assertSee('example.com');
    }
}