<?php

namespace Tests\Feature;

use App\Models\Php2ShellScan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DebugPhp2ShellTest extends TestCase
{
    public function test_debug_post_scan(): void
    {
        $this->artisan('migrate');
        config(['php2shell.execute_sync' => true]);
        Http::fake(function ($request) {
            $ct = (string) ($request->header('Content-Type') ?? '');
            if (str_contains($ct, 'boundary=x')) {
                return Http::response('ok', 200, ['X-Powered-By' => 'PHP/8.2.34']);
            }
            return Http::response('', 200);
        });
        $res = $this->post('/php2shell/scan', [
            'mode' => 'single',
            'urls' => 'https://example.com',
        ]);
        $status = $res->getStatusCode();
$content = (string) $res->getContent();
$this->fail('STATUS: '.$status.' :: COUNT: '.Php2ShellScan::count().' :: CONTENT: '.($content?:'(empty)'));
    }
}
