<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email Intelligence Module
    |--------------------------------------------------------------------------
    | Passive OSINT for email addresses: public account discovery, technical
    | email/domain analysis, and optional breach exposure checks.
    |
    | - No login attempts, password-reset probes, or enumeration via auth flows.
    | - Only public, documented, keyless-or-user-supplied-key sources are used.
    | - All optional credentials come from the environment, never the repo.
    */

    'enabled' => env('EMAIL_INTEL_ENABLED', true),

    // Hard ceilings to keep the engine passive and well behaved.
    'limits' => [
        'max_identifier_candidates' => (int) env('EMAIL_INTEL_MAX_IDENTIFIER_CANDIDATES', 2),
        'max_identifier_platforms' => (int) env('EMAIL_INTEL_MAX_IDENTIFIER_PLATFORMS', 12),
        'max_web_evidence' => (int) env('EMAIL_INTEL_MAX_WEB_EVIDENCE', 10),
        'max_mail_server_ips' => (int) env('EMAIL_INTEL_MAX_MAIL_IPS', 6),
        'max_breaches_listed' => (int) env('EMAIL_INTEL_MAX_BREACHES', 20),
    ],

    // Per-source network timeouts (seconds).
    'timeouts' => [
        'connect' => (int) env('EMAIL_INTEL_CONNECT_TIMEOUT', 5),
        'request' => (int) env('EMAIL_INTEL_REQUEST_TIMEOUT', 10),
    ],

    // Cache TTLs (seconds) for repeated lookups of the same target.
    'cache_ttl' => [
        'dns' => (int) env('EMAIL_INTEL_CACHE_DNS', 300),
        'gravatar' => (int) env('EMAIL_INTEL_CACHE_GRAVATAR', 1800),
        'github' => (int) env('EMAIL_INTEL_CACHE_GITHUB', 600),
        'hibp' => (int) env('EMAIL_INTEL_CACHE_HIBP', 3600),
    ],

    // Request rate limiting applied at the route layer.
    'rate_limits' => [
        'scan_per_minute' => (int) env('EMAIL_INTEL_SCAN_LIMIT', 6),
    ],

    // Breach exposure check (Have I Been Pwned, official v3 API).
    // Only enabled when the operator supplies an API key via env.
    'hibp' => [
        'api_key' => env('HIBP_API_KEY'),
        'base_url' => env('HIBP_BASE_URL', 'https://haveibeenpwned.com/api/v3'),
        'timeout' => (int) env('HIBP_TIMEOUT', 10),
    ],

    // Google DNS-over-HTTPS JSON API (public, documented, keyless) used only
    // for DNSSEC DS-record checks that native resolvers cannot answer.
    'doh' => [
        'base_url' => env('EMAIL_INTEL_DOH_URL', 'https://dns.google/resolve'),
        'timeout' => (int) env('EMAIL_INTEL_DOH_TIMEOUT', 5),
    ],
];
