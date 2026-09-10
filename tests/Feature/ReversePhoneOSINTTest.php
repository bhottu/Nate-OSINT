<?php

namespace Tests\Feature;

use App\Services\ReversePhoneOSINT\ReversePhoneScanner;
use App\Services\ReversePhoneOSINT\SearchQueryBuilder;
use Tests\TestCase;

class ReversePhoneOSINTTest extends TestCase
{
    public function test_reverse_phone_page_is_available(): void
    {
        $this->get('/reverse-phone-osint')->assertOk()->assertViewIs('reverse-phone.index');
    }

    public function test_invalid_phone_is_rejected_without_public_search(): void
    {
        $this->post('/reverse-phone-osint/scan', ['phone' => 'not a phone'])->assertSessionHasErrors('phone');
    }

    public function test_query_builder_generates_unique_search_variants(): void
    {
        $variants = app(SearchQueryBuilder::class)->build('+6281234567890', 'ID');
        $this->assertContains('+6281234567890', $variants);
        $this->assertContains('0812-3456-7890', $variants);
        $this->assertCount(count(array_unique($variants)), $variants);
    }

    public function test_scan_result_uses_public_footprint_structure(): void
    {
        $this->mock(ReversePhoneScanner::class, fn ($mock) => $mock->shouldReceive('scan')->once()->andReturn([
            'target' => ['phone' => '+6281234567890', 'country' => 'ID', 'country_code' => '+62', 'type' => 'Mobile'],
            'search_variants' => ['+6281234567890'],
            'public_footprint' => [['category' => 'business', 'platform' => 'example.com', 'name' => 'Example Business', 'phone_number' => '+6281234567890', 'confidence' => 'HIGH', 'source_url' => 'https://example.com/contact', 'evidence' => 'Public contact page']],
            'status' => 'COMPLETE', 'scope' => 'Publicly indexed sources only.',
        ]));
        $this->post('/reverse-phone-osint/scan', ['phone' => '+6281234567890'])->assertOk()->assertViewIs('reverse-phone.result')->assertViewHas('report.public_footprint.0.confidence', 'HIGH');
    }
}
