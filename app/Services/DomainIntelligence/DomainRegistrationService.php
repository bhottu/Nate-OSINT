<?php

namespace App\Services\DomainIntelligence;

use App\Services\DomainIntelligence\Contracts\RdapProviderInterface;
use Throwable;

/**
 * Registration intelligence (RDAP / WHOIS) using public data only.
 * Privacy-protected registrant data is respected, never bypassed.
 */
class DomainRegistrationService
{
    public function __construct(private RdapProviderInterface $rdap) {}

    /**
     * @return array<string, mixed>
     */
    public function lookup(string $domain): array
    {
        $raw = $this->rdap->domain($domain);

        if ($raw === null) {
            return [
                'available' => false,
                'source' => 'RDAP',
                'registrar' => null,
                'registrar_url' => null,
                'status' => [],
                'created_date' => null,
                'expiration_date' => null,
                'updated_date' => null,
                'nameservers' => [],
                'registry' => null,
                'dnssec' => false,
                'abuse_contact' => null,
                'privacy_protected' => false,
                'raw' => null,
            ];
        }

        $registrar = $this->entityName($raw, 'registrar');
        $events = $this->events($raw);

        return [
            'available' => true,
            'source' => 'RDAP',
            'registrar' => $registrar,
            'registrar_url' => $this->registrarUrl($raw),
            'status' => collect($raw['status'] ?? [])->values()->all(),
            'created_date' => $events['registration'] ?? null,
            'expiration_date' => $events['expiration'] ?? null,
            'updated_date' => $events['last changed'] ?? ($events['last update of RDAP database'] ?? null),
            'nameservers' => $this->nameservers($raw),
            'registry' => $raw['ldhName'] ?? null,
            'dnssec' => isset($raw['secureDNS']['delegationSigned']) ? (bool) $raw['secureDNS']['delegationSigned'] : false,
            'abuse_contact' => $this->abuseContact($raw),
            'privacy_protected' => $this->privacyProtected($raw),
            'raw' => $raw,
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, string>
     */
    private function events(array $raw): array
    {
        $events = [];
        foreach ($raw['events'] ?? [] as $event) {
            if (! is_array($event) || ! isset($event['eventAction'], $event['eventDate'])) {
                continue;
            }
            try {
                $events[strtolower((string) $event['eventAction'])] = date('c', strtotime((string) $event['eventDate']));
            } catch (Throwable) {
                continue;
            }
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<int, string>
     */
    private function nameservers(array $raw): array
    {
        $nameservers = [];
        foreach ($raw['nameservers'] ?? [] as $nameserver) {
            if (is_array($nameserver) && isset($nameserver['ldhName'])) {
                $nameservers[] = strtolower((string) $nameserver['ldhName']);
            }
        }

        return array_values(array_unique($nameservers));
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function entityName(array $raw, string $role): ?string
    {
        foreach ($raw['entities'] ?? [] as $entity) {
            if (! is_array($entity)) {
                continue;
            }
            if (in_array($role, $entity['roles'] ?? [], true)) {
                $vcardArray = $entity['vcardArray'][1] ?? [];
                foreach ($vcardArray as $property) {
                    if (is_array($property) && ($property[0] ?? '') === 'fn') {
                        return (string) ($property[3] ?? null) ?: null;
                    }
                }

                return $entity['handle'] ?? null;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function registrarUrl(array $raw): ?string
    {
        foreach ($raw['entities'] ?? [] as $entity) {
            if (! is_array($entity) || ! in_array('registrar', $entity['roles'] ?? [], true)) {
                continue;
            }
            foreach ($entity['links'] ?? [] as $link) {
                if (is_array($link) && isset($link['href'])) {
                    return (string) $link['href'];
                }
            }
            foreach ($entity['publicIds'] ?? [] as $publicId) {
                if (is_array($publicId) && isset($publicId['identifier'])) {
                    return (string) $publicId['identifier'];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function abuseContact(array $raw): ?string
    {
        foreach ($raw['entities'] ?? [] as $entity) {
            if (! is_array($entity) || ! in_array('registrar', $entity['roles'] ?? [], true)) {
                continue;
            }
            $vcardArray = $entity['vcardArray'][1] ?? [];
            foreach ($vcardArray as $property) {
                if (is_array($property) && ($property[0] ?? '') === 'email') {
                    return (string) ($property[3] ?? '') ?: null;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function privacyProtected(array $raw): bool
    {
        foreach ($raw['entities'] ?? [] as $entity) {
            if (! is_array($entity)) {
                continue;
            }
            $remarks = $entity['remarks'] ?? [];
            foreach ($remarks as $remark) {
                $text = is_array($remark) ? json_encode($remark) : (string) $remark;
                if (is_string($text) && preg_match('/privacy|redact/i', $text)) {
                    return true;
                }
            }
        }

        return false;
    }
}