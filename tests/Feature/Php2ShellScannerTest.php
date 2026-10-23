<?php

namespace Tests\Feature;

use App\Models\Php2ShellScan;
use App\Models\Php2ShellScanTarget;
use App\Models\User;
use App\Services\Php2Shell\Php2ShellScannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Php2ShellScannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['php2shell.execute_sync' => true]);
        // Debug: dump registered routes
        $routes = app()->make(\Illuminate\Routing\Router::class)->getRoutes();
        $php2shellRoutes = [];
        foreach ($routes as $r) {
            $uri = (string) $r->uri();
            if (false !== strpos($uri, 'php2shell')) {
                $php2shellRoutes[] = $r->getName() . ' -> ' . $r->action['uses'];
            }
        }
        \Log::info('DEBUG PHP2SHELL ROUTES:', $php2shellRoutes);
    }

    private function fakeScanner(?string $xPoweredBy = null, int $probeStatus = 200, string $probeBody = ''): void
    {
        Http::fake(function ($request) use ($xPoweredBy, $probeStatus, $probeBody) {
            $contentType = (string) ($request->header('Content-Type') ?? '');

            if (str_contains($contentType, 'boundary=x')) {
                $headers = [];
                if ($xPoweredBy !== null) {
                    $headers['X-Powered-By'] = $xPoweredBy;
                }

                return Http::response('ok', 200, $headers);
            }

            return Http::response($probeBody, $probeStatus);
        });
    }

    public function test_index_page_loads(): void
    {
        $this->get('/php2shell')->assertOk()->assertSee('PHP2Shell')->assertSee('START SCAN');
    }

    public function test_single_scan_stores_history_and_verdict(): void
    {
        $this->fakeScanner('PHP/8.2.34');

        $response = $this->post('/php2shell/scan', [
            'mode' => 'single',
            'urls' => 'https://example.com',
        ]);

        $response->assertRedirect(route('php2shell.show', Php2ShellScan::first()->id));

        $scan = Php2ShellScan::first();
        $this->assertNotNull($scan);
        $this->assertSame('single', $scan->mode);
        $this->assertSame(1, $scan->total_targets);
        $this->assertSame('VULNERABLE', $scan->targets->first()->verdict);
        $this->assertSame('COMPLETED', $scan->status);
    }

    public function test_patched_version_is_not_vulnerable(): void
    {
        $this->fakeScanner('PHP/8.2.35');

        $this->post('/php2shell/scan', [
            'mode' => 'single',
            'urls' => 'https://example.com',
        ]);

        $this->assertSame('NOT VULNERABLE', Php2ShellScan::first()->targets->first()->verdict);
    }

    public function test_empty_500_probe_is_treated_as_vulnerable(): void
    {
        $this->fakeScanner(null, 500, '');

        $this->post('/php2shell/scan', [
            'mode' => 'single',
            'urls' => 'https://example.com',
        ]);

        $this->assertSame('VULNERABLE', Php2ShellScan::first()->targets->first()->verdict);
    }

    public function test_rejected_probe_is_inconclusive(): void
    {
        $this->fakeScanner(null, 403, '');

        $this->post('/php2shell/scan', [
            'mode' => 'single',
            'urls' => 'https://example.com',
        ]);

        $this->assertSame('INCONCLUSIVE', Php2ShellScan::first()->targets->first()->verdict);
    }

    public function test_multiple_targets_are_deduplicated_and_scanned_independently(): void
    {
        $this->fakeScanner('PHP/8.2.35');

        $this->post('/php2shell/scan', [
            'mode' => 'multiple',
            'urls' => "https://example.com\nhttps://example.com\nhttps://example.org\n",
        ]);

        $scan = Php2ShellScan::first();
        $this->assertSame('multiple', $scan->mode);
        $this->assertSame(2, $scan->total_targets);
        $this->assertSame(2, $scan->completed_targets);
        foreach ($scan->targets as $target) {
            $this->assertSame('NOT VULNERABLE', $target->verdict);
        }
    }

    public function test_one_failed_target_does_not_fail_the_batch(): void
    {
        $this->fakeScanner('PHP/8.2.34');

        $this->post('/php2shell/scan', [
            'mode' => 'multiple',
            'urls' => "https://example.com\nhttp://127.0.0.1/",
        ]);

        $scan = Php2ShellScan::first();
        $this->assertSame(2, $scan->total_targets);
        $verdicts = $scan->targets->pluck('verdict')->all();
        $this->assertContains('ERROR', $verdicts);
        $this->assertContains('VULNERABLE', $verdicts);
        $this->assertSame('PARTIAL', $scan->status);
    }

    public function test_invalid_scheme_becomes_error_target(): void
    {
        $this->post('/php2shell/scan', [
            'mode' => 'single',
            'urls' => 'ftp://example.com/',
        ]);

        $scan = Php2ShellScan::first();
        $this->assertNotNull($scan);
        $this->assertSame('ERROR', $scan->targets->first()->verdict);
        $this->assertStringContainsString('skema', $scan->targets->first()->reason);
    }

    public function test_progress_endpoint_returns_json(): void
    {
        $this->fakeScanner('PHP/8.2.35');

        $this->post('/php2shell/scan', [
            'mode' => 'single',
            'urls' => 'https://example.com',
        ]);

        $scan = Php2ShellScan::first();
        $this->get('/php2shell/' . $scan->id . '/progress')
            ->assertOk()
            ->assertJson([
                'id' => $scan->id,
                'status' => 'COMPLETED',
                'percent' => 100,
            ]);
    }

    public function test_authenticated_user_can_only_access_own_scan(): void
    {
        $this->fakeScanner('PHP/8.2.35');

        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/php2shell/scan', [
            'mode' => 'single',
            'urls' => 'https://example.com',
        ]);

        $scan = Php2ShellScan::first();
        $this->assertSame($owner->id, $scan->user_id);

        $other = User::factory()->create();
        $this->actingAs($other)->get('/php2shell/' . $scan->id)->assertStatus(403);
        $this->actingAs($other)->get('/php2shell/' . $scan->id . '/progress')->assertStatus(403);
    }

    public function test_rate_limiting_is_applied(): void
    {
        $this->fakeScanner('PHP/8.2.35');

        for ($i = 0; $i < 10; $i++) {
            $this->post('/php2shell/scan', [
                'mode' => 'single',
                'urls' => 'https://example.com',
            ]);
        }

        $this->post('/php2shell/scan', [
            'mode' => 'single',
            'urls' => 'https://example.com',
        ])->assertStatus(429);
    }

    public function test_evidence_does_not_contain_bearer_token(): void
    {
        $parser = new \App\Services\Php2Shell\Php2ShellResultParser();
        $clean = $parser->excerpt('Authorization: Bearer secret-token-123 and more body');

        $this->assertStringNotContainsString('secret-token-123', $clean);
        $this->assertStringContainsString('[redacted]', $clean);
    }

    public function test_no_secret_is_stored_in_evidence(): void
    {
        $this->fakeScanner('PHP/8.2.35');

        $this->post('/php2shell/scan', [
            'mode' => 'single',
            'urls' => 'https://example.com',
        ]);

        $target = Php2ShellScanTarget::first();
        $this->assertFalse((bool) preg_match('/(Bearer|secret|api[_-]?key|password)/i', $target->evidence ?? ''));
    }
}
