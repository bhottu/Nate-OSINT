<?php

namespace Tests\Feature;

use App\Models\Scan;
use App\Services\SecurityInspector\SecurityScanner;
use App\Services\SecurityInspector\SecurityScoreCalculator;
use App\Services\SecurityInspector\UrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class SecurityInspectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_url_is_rejected_before_scan(): void
    {
        $this->post('/security-inspector/scan', ['url' => 'not-a-url'])->assertSessionHasErrors('url');
    }

    public function test_localhost_private_ip_and_metadata_endpoints_are_rejected(): void
    {
        $guard = app(UrlGuard::class);
        foreach (['http://localhost', 'http://127.0.0.1', 'http://10.0.0.1', 'http://169.254.169.254/latest/meta-data'] as $url) {
            try {
                $guard->validate($url);
                $this->fail($url.' should be rejected.');
            } catch (RuntimeException $exception) {
                $this->assertTrue(str_contains(strtolower($exception->getMessage()), 'not allowed') || str_contains(strtolower($exception->getMessage()), 'internal'));
            }
        }
    }

    public function test_security_score_uses_requested_grade_boundaries(): void
    {
        $calculator = app(SecurityScoreCalculator::class);
        $this->assertSame('A', $calculator->calculate([['score' => 90, 'max' => 100]])['grade']);
        $this->assertSame('B', $calculator->calculate([['score' => 80, 'max' => 100]])['grade']);
        $this->assertSame('F', $calculator->calculate([['score' => 59, 'max' => 100]])['grade']);
    }

    public function test_security_headers_are_marked_ok_or_warn(): void
    {
        $scanner = app(SecurityScanner::class);
        $method = new \ReflectionMethod($scanner, 'headerChecks');
        $headers = $method->invoke($scanner, ['content-security-policy' => 'default-src \'self\'']);
        $this->assertSame('OK', collect($headers)->firstWhere('name', 'content-security-policy')['status']);
        $this->assertSame('WARN', collect($headers)->firstWhere('name', 'x-frame-options')['status']);
    }

    public function test_redirect_to_private_address_is_blocked_before_follow_up_request(): void
    {
        Http::fake(['https://example.test' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin'])]);
        $guard = $this->mock(UrlGuard::class);
        $guard->shouldReceive('validate')->once()->with('https://example.test')->andReturn('https://example.test');
        $guard->shouldReceive('assertSafeRedirect')->once()->with('http://127.0.0.1/admin')->andThrow(new RuntimeException('internal target blocked'));

        try {
            app(SecurityScanner::class)->scan('https://example.test');
            $this->fail('The private redirect should be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('internal target blocked', $exception->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_cookie_attributes_are_detected(): void
    {
        $scanner = app(SecurityScanner::class);
        $method = new \ReflectionMethod($scanner, 'cookies');
        $cookies = $method->invoke($scanner, ['sid=abc; Secure; HttpOnly; SameSite=Lax; Domain=example.com; Path=/; Max-Age=3600']);

        $this->assertTrue($cookies[0]['secure']);
        $this->assertTrue($cookies[0]['http_only']);
        $this->assertSame('lax', $cookies[0]['same_site']);
        $this->assertSame('example.com', $cookies[0]['domain']);
        $this->assertSame('3600', $cookies[0]['max_age']);
    }

    public function test_scan_success_persists_completed_report(): void
    {
        $this->mock(SecurityScanner::class, fn ($mock) => $mock->shouldReceive('scan')->once()->andReturn([
            'target' => ['domain' => 'example.com', 'final_url' => 'https://example.com'],
            'score' => ['value' => 84, 'grade' => 'B', 'breakdown' => []],
            'http' => [], 'headers' => [], 'cookies' => [], 'ssl' => [], 'dns' => [], 'technology' => [], 'findings' => [],
        ]));

        $response = $this->post('/security-inspector/scan', ['url' => 'https://example.com']);
        $scan = Scan::first();
        $response->assertRedirect(route('security.show', $scan));
        $this->assertSame('COMPLETED', $scan->status);
        $this->assertSame(84, $scan->score);
    }

    public function test_scan_failure_is_persisted(): void
    {
        $this->mock(SecurityScanner::class, fn ($mock) => $mock->shouldReceive('scan')->once()->andThrow(new RuntimeException('target unavailable')));

        $this->post('/security-inspector/scan', ['url' => 'https://example.com'])->assertSessionHasErrors('url');
        $this->assertDatabaseHas('scans', ['status' => 'FAILED']);
    }
}
