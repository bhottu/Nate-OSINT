<?php

namespace Tests\Unit;

use App\Services\Php2Shell\Php2ShellResponseAnalyzer;
use PHPUnit\Framework\TestCase;

class Php2ShellDetectionTest extends TestCase
{
    private Php2ShellResponseAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new Php2ShellResponseAnalyzer();
    }

    public function test_unpatched_php_version_is_vulnerable(): void
    {
        $this->assertSame('VULNERABLE', $this->analyzer->versionVerdict('PHP/8.2.34'));
        $this->assertSame('VULNERABLE', $this->analyzer->versionVerdict('PHP/8.2.3'));
        $this->assertSame('VULNERABLE', $this->analyzer->versionVerdict('PHP/8.3.35'));
    }

    public function test_patched_php_version_is_fixed(): void
    {
        $this->assertSame('FIXED', $this->analyzer->versionVerdict('PHP/8.2.35'));
        $this->assertSame('FIXED', $this->analyzer->versionVerdict('PHP/8.3.36'));
        $this->assertSame('FIXED', $this->analyzer->versionVerdict('PHP/8.4.27'));
        $this->assertSame('FIXED', $this->analyzer->versionVerdict('PHP/8.5.12'));
    }

    public function test_unknown_php_version_is_null(): void
    {
        $this->assertNull($this->analyzer->versionVerdict('PHP/8.1.0'));
        $this->assertNull($this->analyzer->versionVerdict('PHP/9.9.99'));
        $this->assertNull($this->analyzer->versionVerdict('Apache/2.4'));
        $this->assertNull($this->analyzer->versionVerdict(''));
        $this->assertNull($this->analyzer->versionVerdict(null));
    }

    public function test_below_minimum_patch_is_vulnerable(): void
    {
        $this->assertSame('VULNERABLE', $this->analyzer->versionVerdict('PHP/8.4.26')); // below 8.4.27
        $this->assertSame('FIXED', $this->analyzer->versionVerdict('PHP/8.4.27')); // exactly patched
    }

    public function test_fpm_empty_500_is_crash_signal(): void
    {
        $signal = $this->analyzer->crashSignal(
            ['status_code' => 200, 'body' => 'ok'],
            ['status_code' => 500, 'body' => ''],
            'fpm'
        );
        $this->assertSame('fpm', $signal['sapi']);
        $this->assertStringContainsString('500', $signal['signal']);
    }

    public function test_mod_php_memory_exhaustion_is_crash_signal(): void
    {
        $signal = $this->analyzer->crashSignal(
            ['status_code' => 200, 'body' => 'ok'],
            ['status_code' => 200, 'body' => 'Fatal error: Allowed memory size of 134217728 bytes exhausted'],
            'mod_php'
        );
        $this->assertSame('mod_php', $signal['sapi']);
    }

    public function test_nonempty_500_without_fatal_signature_is_not_crash(): void
    {
        $this->assertNull($this->analyzer->crashSignal(
            ['status_code' => 200, 'body' => 'ok'],
            ['status_code' => 500, 'body' => 'Internal Server Error'],
            'fpm'
        ));
    }

    public function test_auto_mode_applies_both_sapi_rules(): void
    {
        $signal = $this->analyzer->crashSignal(
            ['status_code' => 200, 'body' => 'ok'],
            ['status_code' => 500, 'body' => ''],
            'auto'
        );
        $this->assertNotNull($signal);
    }

    public function test_rejected_statuses(): void
    {
        $this->assertTrue($this->analyzer->isRejected(403));
        $this->assertTrue($this->analyzer->isRejected(413));
        $this->assertTrue($this->analyzer->isRejected(429));
        $this->assertFalse($this->analyzer->isRejected(200));
        $this->assertFalse($this->analyzer->isRejected(500));
    }

    public function test_control_5xx_is_not_automatically_a_crash(): void
    {
        $this->assertNull($this->analyzer->crashSignal(
            ['status_code' => 500, 'body' => 'error'],
            ['status_code' => 200, 'body' => 'ok'],
            'fpm'
        ));
    }
}
