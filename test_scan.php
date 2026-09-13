<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TESTING CT PROVIDER ===\n";
$ct = new \App\Services\DomainIntelligence\Providers\CrtShProvider();
$start = microtime(true);
$certs = $ct->certificates('google.com');
echo "Certificates found: " . count($certs) . " (took " . round(microtime(true) - $start, 2) . "s)\n";
if (count($certs) > 0) {
    echo "First: " . ($certs[0]['issuer'] ?? 'N/A') . "\n";
    echo "Names: " . implode(', ', array_slice($certs[0]['names'] ?? [], 0, 5)) . "\n";
}

echo "\n=== TESTING HTTP PROVIDER ===\n";
$http = new \App\Services\DomainIntelligence\HttpIntelligenceService(
    new \App\Services\DomainIntelligence\Support\SsrfGuard()
);
$start = microtime(true);
$result = $http->observe('https://google.com');
echo "HTTP status: " . ($result['status_code'] ?? 'N/A') . " (took " . round(microtime(true) - $start, 2) . "s)\n";
echo "Error: " . ($result['error'] ?? 'none') . "\n";
echo "Final URL: " . ($result['final_url'] ?? 'N/A') . "\n";

echo "\n=== TESTING TLS PROVIDER ===\n";
$tls = new \App\Services\DomainIntelligence\TlsIntelligenceService();
$start = microtime(true);
$result = $tls->inspect('google.com');
echo "TLS valid: " . (($result['valid'] ?? false) ? 'YES' : 'NO') . " (took " . round(microtime(true) - $start, 2) . "s)\n";
echo "Error: " . ($result['error'] ?? 'none') . "\n";

echo "\n=== TESTING IP PROVIDER ===\n";
$ip = new \App\Services\DomainIntelligence\Providers\IpWhoisProvider();
$start = microtime(true);
$result = $ip->lookup('8.8.8.8');
echo "IP result: " . ($result ? 'found' : 'null') . " (took " . round(microtime(true) - $start, 2) . "s)\n";
if ($result) {
    echo "  ASN: " . ($result['asn'] ?? 'N/A') . "\n";
    echo "  Org: " . ($result['asn_org'] ?? 'N/A') . "\n";
}