<?php

namespace App\Services\DomainIntelligence;

use App\Services\DomainIntelligence\Contracts\DnsProviderInterface;

/**
 * Email security intelligence derived exclusively from public DNS records.
 * Never sends email or attempts authentication.
 */
class EmailSecurityService
{
    private const DKIM_SELECTORS = ['default', 'google', 'selector1', 'selector2', 'k1', 's1', 'dkim', 'mail'];

    public function __construct(private DnsProviderInterface $dns) {}

    /**
     * @return array{spf: array{status: string, value: ?string}, dmarc: array{status: string, value: ?string},
     *               mx: array{status: string, values: array<int, string>}, dkim: array{status: string, selectors: array<int, string>}}
     */
    public function analyze(string $hostname, array $dnsRecords = []): array
    {
        $records = $dnsRecords !== [] ? $dnsRecords : $this->dns->resolve($hostname, ['TXT', 'MX']);

        $spf = collect($records['TXT'] ?? [])->pluck('value')
            ->first(fn ($v) => is_string($v) && str_starts_with(strtolower($v), 'v=spf1'));
        $dmarc = collect($this->dns->records('_dmarc.'.$hostname, 'TXT'))->pluck('value')
            ->first(fn ($v) => is_string($v) && str_starts_with(strtolower($v), 'v=DMARC1'));
        $mx = collect($records['MX'] ?? [])->pluck('value')->values()->all();

        $dkimSelectors = [];
        foreach (self::DKIM_SELECTORS as $selector) {
            $found = $this->dns->records($selector.'._domainkey.'.$hostname, 'TXT');
            if ($found !== []) {
                $dkimSelectors[] = $selector;
            }
        }

        return [
            'spf' => [
                'status' => $spf !== null ? 'PASS' : 'MISSING',
                'value' => $spf,
                'weak' => $spf !== null && str_contains($spf, '+all'),
            ],
            'dmarc' => [
                'status' => $dmarc !== null ? $this->policy($dmarc) : 'MISSING',
                'value' => $dmarc,
            ],
            'mx' => [
                'status' => $mx !== [] ? 'CONFIGURED' : 'MISSING',
                'values' => $mx,
            ],
            'dkim' => [
                'status' => $dkimSelectors !== [] ? 'PRESENT' : 'UNKNOWN',
                'selectors' => $dkimSelectors,
            ],
        ];
    }

    private function policy(string $dmarc): string
    {
        if (preg_match('/p=reject/i', $dmarc)) {
            return 'REJECT';
        }
        if (preg_match('/p=quarantine/i', $dmarc)) {
            return 'QUARANTINE';
        }
        if (preg_match('/p=none/i', $dmarc)) {
            return 'NONE';
        }

        return 'PRESENT';
    }
}