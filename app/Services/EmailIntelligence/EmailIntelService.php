<?php

namespace App\Services\EmailIntelligence;

use App\Services\EmailIntelligence\Sources\GitHubSource;
use App\Services\EmailIntelligence\Sources\GravatarSource;
use App\Services\EmailIntelligence\Sources\IdentifierSource;
use App\Services\EmailIntelligence\Sources\WebEvidenceSource;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestrates a full passive Email Intelligence scan.
 *
 * Pipeline: normalize -> technical domain analysis -> keyless direct sources
 * (Gravatar, web evidence, GitHub) -> identifier probes (single bounded HTTP
 * pool) -> optional breach check -> summary. Every failure is isolated; the
 * remaining sources always continue.
 */
class EmailIntelService
{
    public function __construct(
        private EmailNormalizer $normalizer,
        private EmailDomainAnalyzer $domainAnalyzer,
        private GravatarSource $gravatar,
        private GitHubSource $github,
        private WebEvidenceSource $webEvidence,
        private IdentifierSource $identifierSource,
        private BreachCheckService $breach,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function scan(string $rawEmail): array
    {
        $started = microtime(true);
        $parsed = $this->normalizer->parse($rawEmail);

        if (! $parsed['valid']) {
            return [
                'parsed' => $parsed,
                'domain_report' => null,
                'breach_report' => null,
                'results' => [],
                'summary' => [
                    'status' => 'ERROR',
                    'total_sources_checked' => 0,
                    'potential' => 0,
                    'unverified' => 0,
                    'unable_to_verify' => 0,
                    'not_found' => 0,
                    'errors' => 1,
                    'took_seconds' => round(microtime(true) - $started, 3),
                ],
                'fatal_error' => 'The submitted email address failed format validation. Nothing was scanned.',
            ];
        }

        $normalized = $parsed['normalized'];
        $results = [];

        // 1) Technical analysis of the domain (public DNS/RDAP only, no SMTP).
        try {
            $domainReport = $this->domainAnalyzer->analyze($parsed['domain']);
        } catch (Throwable) {
            Log::warning('EmailIntel: domain analysis failed', ['domain' => $parsed['domain']]);
            $domainReport = [
                'domain' => $parsed['domain'],
                'domain_status' => 'UNKNOWN',
                'error' => 'Domain analysis failed for this request.',
            ];
        }

        // 2) Keyless direct sources. One failure never stops the others.
        foreach ([$this->gravatar, $this->webEvidence, $this->github] as $source) {
            try {
                $results[] = $source->check($normalized);
            } catch (Throwable) {
                $results[] = SourceResult::error(
                    $source->platform(),
                    $source->kind(),
                    'Source failed unexpectedly; other sources were not affected.',
                    null
                );
            }
        }

        // 3) Identifier probes (bounded concurrent pool inside the source).
        try {
            foreach ($this->identifierSource->checkAll($normalized) as $result) {
                $results[] = $result;
            }
        } catch (Throwable) {
            $results[] = SourceResult::error('Public profiles', 'identifier_probe', 'Identifier probing failed unexpectedly.', null);
        }

        // 4) Optional breach exposure (only when HIBP_API_KEY is configured).
        try {
            $breachReport = $this->breach->check($normalized);
        } catch (Throwable) {
            $breachReport = [
                'status' => 'ERROR',
                'configured' => $this->breach->configured(),
                'total' => 0,
                'breaches' => [],
                'error' => 'Breach check failed unexpectedly.',
            ];
        }

        // 5) De-duplicate and summarize.
        $results = $this->dedupe($results);
        $summary = $this->summarize($results, $started);

        Log::info('EmailIntel scan completed', [
            'domain' => $parsed['domain'],
            'sources_checked' => $summary['total_sources_checked'],
            'potential' => $summary['potential'],
            'took_seconds' => $summary['took_seconds'],
        ]);

        return [
            'parsed' => $parsed,
            'domain_report' => $domainReport,
            'breach_report' => $breachReport,
            'results' => $results,
            'summary' => $summary,
        ];
    }

    /**
     * @param  array<int, SourceResult>  $results
     * @return array<int, SourceResult>
     */
    private function dedupe(array $results): array
    {
        $seen = [];
        $unique = [];

        foreach ($results as $result) {
            $key = hash('sha256', implode('|', [
                $result->platform,
                $result->kind,
                (string) $result->identifier,
                (string) $result->profileUrl,
                $result->status,
            ]));

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $result;
        }

        return $unique;
    }

    /**
     * @param  array<int, SourceResult>  $results
     * @return array<string, mixed>
     */
    private function summarize(array $results, float $started): array
    {
        $potential = count(array_filter($results, fn ($r) => $r->status === 'FOUND'));
        $notFound = count(array_filter($results, fn ($r) => $r->status === 'NOT FOUND'));
        $unverified = count(array_filter($results, fn ($r) => $r->status === 'UNVERIFIED'));
        $unable = count(array_filter($results, fn ($r) => $r->status === 'UNABLE TO VERIFY'));
        $errors = count(array_filter($results, fn ($r) => $r->status === 'ERROR'));

        $status = 'NOT FOUND';
        if ($potential > 0) {
            $status = 'POTENTIAL MATCH';
        } elseif ($errors > 0 && $notFound === 0 && $unverified === 0 && $unable === 0) {
            $status = 'ERROR';
        } elseif ($unable > 0 && $notFound === 0) {
            $status = 'UNABLE TO VERIFY';
        }

        return [
            'status' => $status,
            'total_sources_checked' => count($results),
            'potential' => $potential,
            'unverified' => $unverified,
            'unable_to_verify' => $unable,
            'not_found' => $notFound,
            'errors' => $errors,
            'took_seconds' => round(microtime(true) - $started, 3),
        ];
    }
}
