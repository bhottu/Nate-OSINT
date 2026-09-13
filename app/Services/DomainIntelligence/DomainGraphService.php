<?php

namespace App\Services\DomainIntelligence;

/**
 * Builds the relationship graph (nodes + edges) for a scan so the UI can
 * render an evidence-linked infrastructure map.
 */
class DomainGraphService
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    public function build(array $context): array
    {
        $nodes = [];
        $edges = [];
        $domain = (string) ($context['hostname'] ?? '');

        if ($domain === '') {
            return ['nodes' => [], 'edges' => []];
        }

        $nodes[] = $this->node('domain:'.$domain, 'DOMAIN', $domain, 'HIGH');

        foreach ($context['ips'] ?? [] as $ip) {
            $ipAddress = (string) ($ip['ip'] ?? '');
            if ($ipAddress === '') {
                continue;
            }
            $nodes[] = $this->node('ip:'.$ipAddress, 'IP', $ipAddress, ($ip['provider_available'] ?? false) ? 'HIGH' : 'MEDIUM');
            $edges[] = $this->edge('domain:'.$domain, 'ip:'.$ipAddress, 'RESOLVES_TO', 'DNS A/AAAA');
        }

        foreach ($context['subdomains'] ?? [] as $subdomain) {
            $hostname = (string) ($subdomain['hostname'] ?? '');
            if ($hostname === '') {
                continue;
            }
            $nodes[] = $this->node('sub:'.$hostname, 'SUBDOMAIN', $hostname, 'HIGH');
            $edges[] = $this->edge('domain:'.$domain, 'sub:'.$hostname, 'SUBDOMAIN_OF', 'Certificate Transparency');

            if (! empty($subdomain['ip'])) {
                $edges[] = $this->edge('sub:'.$hostname, 'ip:'.$subdomain['ip'], 'RESOLVES_TO', 'DNS A');
            }
        }

        foreach ($context['certificates'] ?? [] as $certificate) {
            $id = 'cert:'.md5((string) ($certificate['id'] ?? uniqid('', true)));
            $nodes[] = $this->node($id, 'CERTIFICATE', (string) ($certificate['issuer'] ?? 'Certificate'), 'HIGH');
            $edges[] = $this->edge($id, 'domain:'.$domain, 'ISSUED_FOR', 'CT log');
        }

        foreach ($context['whois']['nameservers'] ?? [] as $nameserver) {
            $nodes[] = $this->node('ns:'.$nameserver, 'NAMESERVER', $nameserver, 'HIGH');
            $edges[] = $this->edge('domain:'.$domain, 'ns:'.$nameserver, 'DELEGATED_TO', 'RDAP');
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * @return array{id: string, type: string, label: string, confidence: string}
     */
    private function node(string $id, string $type, string $label, string $confidence): array
    {
        return ['id' => $id, 'type' => $type, 'label' => $label, 'confidence' => $confidence];
    }

    /**
     * @return array{from: string, to: string, relation: string, source: string}
     */
    private function edge(string $from, string $to, string $relation, string $source): array
    {
        return ['from' => $from, 'to' => $to, 'relation' => $relation, 'source' => $source];
    }
}