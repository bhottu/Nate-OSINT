<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Domain Intelligence Module
    |--------------------------------------------------------------------------
    | Configuration for the passive OSINT domain reconnaissance engine.
    | No API keys are ever hardcoded: everything optional is read from the
    | environment and gracefully degrades to UNKNOWN / PROVIDER UNAVAILABLE.
    */

    'enabled' => env('DOMAIN_INTEL_ENABLED', true),

    // Hard ceilings to keep the engine passive and well behaved.
    'limits' => [
        'max_subdomains' => (int) env('DOMAIN_INTEL_MAX_SUBDOMAINS', 200),
        'max_redirects' => (int) env('DOMAIN_INTEL_MAX_REDIRECTS', 5),
        'max_ips' => (int) env('DOMAIN_INTEL_MAX_IPS', 50),
        'max_certificates' => (int) env('DOMAIN_INTEL_MAX_CERTIFICATES', 100),
        'http_response_bytes' => (int) env('DOMAIN_INTEL_MAX_RESPONSE_BYTES', 2_000_000),
        'max_concurrency' => (int) env('DOMAIN_INTEL_MAX_CONCURRENCY', 5),
    ],

    // Per-provider network timeouts (seconds).
    'timeouts' => [
        'connect' => (int) env('DOMAIN_INTEL_CONNECT_TIMEOUT', 5),
        'request' => (int) env('DOMAIN_INTEL_REQUEST_TIMEOUT', 12),
        'dns' => (int) env('DOMAIN_INTEL_DNS_TIMEOUT', 5),
    ],

    // Cache TTLs (seconds) for expensive lookups.
    'cache_ttl' => [
        'dns' => (int) env('DOMAIN_INTEL_CACHE_DNS', 300),
        'rdap' => (int) env('DOMAIN_INTEL_CACHE_RDAP', 3600),
        'whois' => (int) env('DOMAIN_INTEL_CACHE_WHOIS', 3600),
        'ct' => (int) env('DOMAIN_INTEL_CACHE_CT', 1800),
        'ip' => (int) env('DOMAIN_INTEL_CACHE_IP', 3600),
        'asn' => (int) env('DOMAIN_INTEL_CACHE_ASN', 86400),
        'technology' => (int) env('DOMAIN_INTEL_CACHE_TECH', 1800),
        'tls' => (int) env('DOMAIN_INTEL_CACHE_TLS', 3600),
    ],

    // Feature toggles.
    'features' => [
        'dns' => env('DOMAIN_INTEL_ENABLE_DNS', true),
        'rdap' => env('DOMAIN_INTEL_ENABLE_RDAP', true),
        'whois' => env('DOMAIN_INTEL_ENABLE_WHOIS', false),
        'ct' => env('DOMAIN_INTEL_ENABLE_CT', true),
        'ip_enrichment' => env('DOMAIN_INTEL_ENABLE_IP', true),
        'search' => env('DOMAIN_INTEL_ENABLE_SEARCH', false),
        'active_checks' => env('DOMAIN_INTEL_ENABLE_ACTIVE', false),
        'queue' => env('DOMAIN_INTEL_ENABLE_QUEUE', false),
    ],

    // External provider endpoints and credentials (all optional).
    'providers' => [
        'rdap' => [
            'driver' => env('RDAP_PROVIDER', 'rdap.org'),
            'base_url' => env('RDAP_BASE_URL', 'https://rdap.org'),
            'api_key' => env('RDAP_API_KEY'),
        ],
        'dns' => [
            'driver' => env('DNS_PROVIDER', 'native'),
        ],
        'ct' => [
            'driver' => env('CT_PROVIDER', 'crt.sh'),
            'base_url' => env('CT_BASE_URL', 'https://crt.sh'),
        ],
        'ip' => [
            'driver' => env('IP_INTEL_PROVIDER', 'ipwho.is'),
            'base_url' => env('IP_INTEL_BASE_URL', 'https://ipwho.is'),
            'api_key' => env('IP_INTEL_API_KEY'),
        ],
        'search' => [
            'driver' => env('SEARCH_PROVIDER', 'null'),
            'base_url' => env('SEARCH_BASE_URL'),
            'api_key' => env('SEARCH_API_KEY'),
        ],
    ],

    // Generic provider abstraction credentials surfaced to the CLI/env audit.
    'keys' => [
        'domain_intel_api_key' => env('DOMAIN_INTEL_API_KEY'),
    ],

    // Request rate limiting applied at the route/job layer.
    'rate_limits' => [
        'scan_per_minute' => (int) env('DOMAIN_INTEL_SCAN_LIMIT', 5),
        'domain_cooldown_seconds' => (int) env('DOMAIN_INTEL_COOLDOWN', 30),
    ],

    // Tunable thresholds used by scoring and findings.
    'thresholds' => [
        'certificate_expiring_days' => (int) env('DOMAIN_INTEL_CERT_EXPIRY_DAYS', 30),
        'domain_expiry_warning_days' => (int) env('DOMAIN_INTEL_DOMAIN_EXPIRY_DAYS', 30),
    ],
];