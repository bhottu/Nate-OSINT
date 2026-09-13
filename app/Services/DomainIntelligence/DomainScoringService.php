<?php

namespace App\Services\DomainIntelligence;

/**
 * Heuristic security posture scoring.
 *
 * The score is an explainable heuristic, NOT a statement that a domain is
 * "secure". Every point change is traceable to a category breakdown.
 */
class DomainScoringService
{
    /**
     * @param  array<int, array<string, mixed>>  $findings
     * @param  array<string, mixed>  $context
     * @return array{value: int, grade: string, posture: string, breakdown: array<int, array{name: string, score: int, max: int}>, reasons: array<int, string>}
     */
    public function calculate(array $findings, array $context): array
    {
        $breakdown = [
            ['name' => 'DNS Security', 'score' => 0, 'max' => 20],
            ['name' => 'Email Security', 'score' => 0, 'max' => 20],
            ['name' => 'TLS Security', 'score' => 0, 'max' => 20],
            ['name' => 'HTTP Security', 'score' => 0, 'max' => 20],
            ['name' => 'Infrastructure', 'score' => 0, 'max' => 10],
            ['name' => 'Certificate Health', 'score' => 0, 'max' => 5],
            ['name' => 'Configuration', 'score' => 0, 'max' => 5],
        ];

        $reasons = [];
        $dns = $context['dns']['security'] ?? [];
        $email = $context['email'] ?? [];
        $tls = $context['tls'] ?? [];
        $http = $context['http'] ?? [];

        // DNS Security (20)
        if (($dns['caa']['status'] ?? '') === 'PRESENT') {
            $breakdown[0]['score'] += 6;
        } else {
            $reasons[] = 'CAA missing (-6 DNS Security)';
        }
        if (($dns['nameservers']['status'] ?? '') === 'REDUNDANT') {
            $breakdown[0]['score'] += 8;
        } elseif (($dns['nameservers']['status'] ?? '') === 'SINGLE') {
            $reasons[] = 'Single nameserver (-8 DNS Security)';
        } else {
            $reasons[] = 'Nameservers not observed (-8 DNS Security)';
        }
        if (($dns['soa']['status'] ?? '') === 'PRESENT') {
            $breakdown[0]['score'] += 3;
        }
        if (($dns['dnssec']['status'] ?? '') === 'ENABLED') {
            $breakdown[0]['score'] += 3;
        }

        // Email Security (20)
        if (($email['spf']['status'] ?? '') === 'PASS') {
            $breakdown[1]['score'] += ($email['spf']['weak'] ?? false) ? 4 : 8;
            if (($email['spf']['weak'] ?? false)) {
                $reasons[] = 'SPF uses +all (-4 Email Security)';
            }
        } else {
            $reasons[] = 'SPF missing (-8 Email Security)';
        }
        $dmarc = $email['dmarc']['status'] ?? 'MISSING';
        $breakdown[1]['score'] += match ($dmarc) {
            'REJECT' => 8,
            'QUARANTINE' => 6,
            'NONE', 'PRESENT' => 2,
            default => 0,
        };
        if ($dmarc === 'MISSING') {
            $reasons[] = 'DMARC missing (-8 Email Security)';
        } elseif ($dmarc === 'NONE') {
            $reasons[] = 'DMARC p=none (-6 Email Security)';
        }
        if (($email['mx']['status'] ?? '') === 'CONFIGURED') {
            $breakdown[1]['score'] += 4;
        }

        // TLS Security (20)
        if (($tls['valid'] ?? false) === true) {
            $breakdown[2]['score'] += 12;
            if (($tls['hostname_match'] ?? false) === true) {
                $breakdown[2]['score'] += 4;
            } else {
                $reasons[] = 'TLS hostname mismatch (-4 TLS Security)';
            }
            $days = $tls['days_remaining'] ?? null;
            $threshold = (int) config('domain_intelligence.thresholds.certificate_expiring_days', 30);
            if (is_int($days) && $days > $threshold) {
                $breakdown[2]['score'] += 4;
            } else {
                $reasons[] = 'Certificate expiring soon (-4 TLS Security)';
            }
        } else {
            $reasons[] = 'TLS not verified (-20 TLS Security)';
        }

        // HTTP Security (20)
        $headers = $http['security_headers'] ?? [];
        $present = count(array_filter($headers, fn ($h) => ($h['status'] ?? '') === 'PRESENT'));
        $total = max(1, count($headers));
        $breakdown[3]['score'] = (int) round(($present / $total) * 20);
        if ($present < $total) {
            $reasons[] = ($total - $present).' security header(s) missing (-'.($total - $present).' HTTP Security)';
        }
        if (($http['https'] ?? false) === false) {
            $reasons[] = 'HTTPS not observed (-0 additional, reflected in TLS)';
        }

        // Infrastructure (10): resolvable + IP intelligence available
        $ips = $context['ips'] ?? [];
        if ($ips !== []) {
            $breakdown[4]['score'] += 5;
        }
        $enriched = count(array_filter($ips, fn ($ip) => ($ip['provider_available'] ?? false) === true));
        if ($enriched > 0) {
            $breakdown[4]['score'] += 5;
        } else {
            $reasons[] = 'IP intelligence provider unavailable (-5 Infrastructure)';
        }

        // Certificate Health (5): CT presence
        if (($context['certificates'] ?? []) !== []) {
            $breakdown[5]['score'] += 5;
        }

        // Configuration (5): registration data available
        if (($context['whois']['available'] ?? false) === true) {
            $breakdown[6]['score'] += 5;
        }

        $value = (int) max(0, min(100, array_sum(array_column($breakdown, 'score'))));
        $grade = $this->grade($value);

        return [
            'value' => $value,
            'grade' => $grade,
            'posture' => $this->posture($findings, $value),
            'breakdown' => $breakdown,
            'reasons' => $reasons,
        ];
    }

    private function grade(int $value): string
    {
        return match (true) {
            $value >= 90 => 'A',
            $value >= 80 => 'B',
            $value >= 70 => 'C',
            $value >= 60 => 'D',
            default => 'F',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $findings
     */
    private function posture(array $findings, int $value): string
    {
        $high = count(array_filter($findings, fn ($f) => ($f['severity'] ?? '') === 'HIGH'));

        if ($high >= 3 || $value < 50) {
            return 'HIGH-RISK INDICATORS';
        }

        if ($high >= 1 || $value < 75) {
            return 'NEEDS ATTENTION';
        }

        return 'GOOD POSTURE';
    }
}