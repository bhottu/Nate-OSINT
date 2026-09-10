<?php

namespace Tests\Feature;

use App\Services\ReversePhoneOSINT\SearchProviderInterface;
use App\Services\SecurityInspector\UrlGuard;
use App\Services\SocialAccountCorrelation\CrossLinkDetector;
use App\Services\SocialAccountCorrelation\EvidenceScorer;
use App\Services\SocialAccountCorrelation\IdentityGraphBuilder;
use App\Services\SocialAccountCorrelation\SocialAccountCorrelationService;
use App\Services\SocialAccountCorrelation\SocialIdentifierValidator;
use App\Services\SocialAccountCorrelation\UsernameVariantBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialAccountCorrelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_correlation_page_is_available(): void
    {
        $this->get('/social-account-correlation')->assertOk()->assertViewIs('social-correlation.index');
    }

    public function test_username_variants_are_unique_and_safe(): void
    {
        $variants = app(UsernameVariantBuilder::class)->build('@example');
        $this->assertContains('example_official', $variants);
        $this->assertCount(count(array_unique($variants)), $variants);
        $this->assertNotContains('@example', $variants);
    }

    public function test_direct_cross_link_has_confirmed_link_confidence(): void
    {
        $result = app(EvidenceScorer::class)->score([['type' => 'DIRECT CROSS-LINK', 'source_platform' => 'Instagram', 'target_platform' => 'Facebook', 'source_url' => 'https://instagram.com/example', 'target_url' => 'https://facebook.com/example']]);
        $this->assertSame('CONFIRMED LINK', $result['confidence']);
        $this->assertSame(50, $result['score']);
    }

    public function test_same_username_alone_is_possible_match_not_identity_proof(): void
    {
        $result = app(EvidenceScorer::class)->score([['type' => 'EXACT USERNAME', 'source_platform' => 'Instagram', 'target_platform' => 'Facebook', 'source_url' => 'https://instagram.com/example', 'target_url' => 'https://facebook.com/example']]);
        $this->assertSame('POSSIBLE MATCH', $result['confidence']);
        $this->assertSame(20, $result['score']);
    }

    public function test_technical_css_and_base64_identifiers_are_rejected(): void
    {
        $validator = app(SocialIdentifierValidator::class);
        $this->assertNull($validator->username('@css; charset=utf-8;base64,abc', 'https://instagram.com/example'));
        $this->assertNull($validator->username('@media'));
        $this->assertSame('example', $validator->username('@example'));
    }

    public function test_non_profile_social_urls_are_rejected(): void
    {
        $validator = app(SocialIdentifierValidator::class);
        $this->assertNull($validator->profileUrl('https://facebook.com/css'));
        $this->assertNull($validator->profileUrl('https://facebook.com/posts/123'));
        $this->assertNull($validator->profileUrl('data:text/css,body{}'));
        $this->assertSame('PUBLIC_PAGE', $validator->profileUrl('https://facebook.com/pages/example/123')['profile_type']);
        $this->assertSame('numeric_id', $validator->profileUrl('https://facebook.com/261704639352628')['identifier_type']);
        $this->assertNull($validator->profileUrl('https://x.com/user/status/123456'));
    }

    public function test_cross_link_detector_discards_css_base64_and_non_profile_links(): void
    {
        Http::fake(['https://instagram.com/example' => Http::response('<link href="https://facebook.com/css; charset=utf-8;base64,abc"><a href="https://facebook.com/example">real</a><a href="data:text/css,body{}">css</a>')]);
        $this->mock(UrlGuard::class, fn ($mock) => $mock->shouldReceive('validate')->once()->andReturn('https://instagram.com/example'));
        $links = app(CrossLinkDetector::class)->inspect('https://instagram.com/example');
        $this->assertCount(1, $links);
        $this->assertSame('https://facebook.com/example', $links[0]['url']);
    }

    public function test_graph_contains_seed_and_correlated_profile(): void
    {
        $graph = app(IdentityGraphBuilder::class)->build(['platform' => 'Instagram', 'username' => 'example', 'url' => null], [[
            'platform' => 'TikTok', 'username' => '@example.id', 'source_url' => 'https://tiktok.com/@example.id', 'confidence' => 'POSSIBLE MATCH',
        ]]);
        $this->assertCount(2, $graph['nodes']);
        $this->assertCount(1, $graph['edges']);
    }

    public function test_invalid_seed_url_is_rejected_by_controller(): void
    {
        $this->post('/social-account-correlation/scan', ['input' => 'http://127.0.0.1/admin', 'platform' => 'Instagram'])->assertSessionHasErrors('input');
    }

    public function test_scan_result_is_rendered_without_search_provider_results(): void
    {
        $this->mock(SearchProviderInterface::class, fn ($mock) => $mock->shouldReceive('search')->andReturn([]));
        $response = $this->post('/social-account-correlation/scan', ['input' => '@example', 'platform' => 'Instagram']);
        $response->assertOk()->assertViewIs('social-correlation.result')->assertViewHas('report.profiles', []);
    }

    public function test_all_supported_platform_candidates_are_retained(): void
    {
        $this->mock(SearchProviderInterface::class, function ($mock) {
            $mock->shouldReceive('search')->andReturn([
                ['title' => 'GitHub example', 'url' => 'https://github.com/example', 'snippet' => 'example'],
                ['title' => 'Facebook example', 'url' => 'https://facebook.com/example', 'snippet' => 'example'],
                ['title' => 'TikTok example', 'url' => 'https://tiktok.com/@example', 'snippet' => 'example'],
                ['title' => 'X example', 'url' => 'https://x.com/example', 'snippet' => 'example'],
                ['title' => 'YouTube example', 'url' => 'https://youtube.com/@example', 'snippet' => 'example'],
                ['title' => 'LinkedIn example', 'url' => 'https://linkedin.com/in/example', 'snippet' => 'example'],
            ]);
        });
        $report = app(SocialAccountCorrelationService::class)->analyze('@example', 'Instagram');
        $this->assertSame(['Facebook', 'GitHub', 'LinkedIn', 'TikTok', 'X', 'YouTube'], collect($report['profiles'])->pluck('platform')->sort()->values()->all());
        $this->assertSame('FOUND', $report['platforms']['github']['status']);
    }
}
