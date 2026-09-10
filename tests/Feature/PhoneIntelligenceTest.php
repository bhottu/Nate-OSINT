<?php

namespace Tests\Feature;

use App\Models\PhoneScan;
use App\Services\PhoneIntelligence\PhoneNormalizer;
use App\Services\PhoneIntelligence\PhoneParser;
use App\Services\PhoneIntelligence\PhoneScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_is_normalized_to_e164(): void
    {
        $phone = app(PhoneNormalizer::class)->normalize('0812-3456-7890', 'ID');
        $this->assertSame('+6281234567890', $phone['number']);
        $this->assertSame('ID', $phone['country']);
        $this->assertSame('+62', $phone['country_code']);
    }

    public function test_parser_detects_tel_links_and_visible_numbers_without_duplicates(): void
    {
        $html = '<a href="tel:+6281234567890">Call</a><footer>+62 812-3456-7890</footer>';
        $phones = app(PhoneParser::class)->parse($html);
        $this->assertCount(1, $phones);
        $this->assertSame('+6281234567890', $phones[0]['number']);
    }

    public function test_phone_scan_success_persists_public_contact_report(): void
    {
        $this->mock(PhoneScanner::class, fn ($mock) => $mock->shouldReceive('scan')->once()->andReturn([
            'target' => ['url' => 'https://example.com', 'domain' => 'example.com'],
            'phones' => [['number' => '+6281234567890', 'country' => 'ID', 'type' => 'Mobile', 'confidence' => 'HIGH', 'source' => 'https://example.com/contact']],
            'status' => 'SCAN COMPLETE', 'scope' => 'Public business contact information only.',
        ]));
        $response = $this->post('/phone-intelligence/scan', ['business_name' => 'Acme', 'url' => 'https://example.com']);
        $scan = PhoneScan::first();
        $response->assertRedirect(route('phone.show', $scan));
        $this->assertSame('COMPLETED', $scan->status);
        $this->assertSame('+6281234567890', $scan->result['phones'][0]['number']);
    }

    public function test_phone_scan_requires_a_domain_or_url(): void
    {
        $this->post('/phone-intelligence/scan', ['business_name' => 'Acme'])->assertSessionHasErrors('url');
    }
}
