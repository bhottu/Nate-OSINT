<?php

namespace Tests\Unit;

use App\Services\Php2Shell\Php2ShellTargetValidator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class Php2ShellTargetValidatorTest extends TestCase
{
    private Php2ShellTargetValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new Php2ShellTargetValidator([80, 443]);
    }

    public function test_valid_https_url_is_normalized(): void
    {
        $this->assertSame('https://example.com/', $this->validator->validate('https://example.com'));
    }

    public function test_url_without_scheme_is_prefixed_with_https(): void
    {
        $this->assertSame('https://example.com/', $this->validator->validate('example.com'));
    }

    public function test_http_url_is_kept(): void
    {
        $this->assertSame('http://example.com/', $this->validator->validate('http://example.com'));
    }

    public function test_custom_path_is_preserved(): void
    {
        $this->assertSame('https://example.com/index.php', $this->validator->validate('https://example.com/index.php'));
    }

    public function test_only_http_and_https_schemes_are_allowed(): void
    {
        foreach (['ftp://example.com', 'file:///etc/passwd', 'gopher://example.com'] as $url) {
            $this->expectException(RuntimeException::class);
            $this->validator->validate($url);
        }
    }

    public function test_credentials_in_url_are_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->validator->validate('http://user:pass@example.com/');
    }

    public function test_loopback_hostnames_are_rejected(): void
    {
        foreach (['http://localhost/', 'http://127.0.0.1/', 'http://::1/'] as $url) {
            $this->expectException(RuntimeException::class);
            $this->validator->validate($url);
        }
    }

    public function test_private_ipv4_ranges_are_rejected(): void
    {
        foreach (['http://10.0.0.1/', 'http://172.16.0.1/', 'http://192.168.1.1/'] as $url) {
            $this->expectException(RuntimeException::class);
            $this->validator->validate($url);
        }
    }

    public function test_link_local_and_metadata_are_rejected(): void
    {
        foreach (['http://169.254.169.254/', 'http://169.254.0.1/'] as $url) {
            $this->expectException(RuntimeException::class);
            $this->validator->validate($url);
        }
    }

    public function test_multicast_address_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->validator->validate('http://224.0.0.1/');
    }

    public function test_private_ipv6_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->validator->validate('http://fc00::1/');
    }

    public function test_non_standard_ports_are_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->validator->validate('https://example.com:8080/');
    }

    public function test_reserved_ipv4_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->validator->validate('http://240.0.0.1/');
    }

    public function test_redirect_to_internal_address_is_blocked(): void
    {
        $this->expectException(RuntimeException::class);
        $this->validator->assertRedirectSafe('http://127.0.0.1/admin', 'https://example.com/');
    }

    public function test_public_ipv4_is_allowed(): void
    {
        $this->assertSame('https://93.184.216.34/', $this->validator->validate('https://93.184.216.34/'));
    }
}
