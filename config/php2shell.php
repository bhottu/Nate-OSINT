<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PHP2Shell Scanner Module
    |--------------------------------------------------------------------------
    |
    | Deteksi CVE-2026-109618 / CVE-2026-109619 (php2shell) secara native PHP
    | tanpa memerlukan runtime Python (tidak ada scanner.py, h11, atau tqdm).
    | Implementasi memetakan perilaku protokol scanner referensi Assetnote.
    */

    'enabled' => env('PHP2SHELL_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Identitas scanner
    |--------------------------------------------------------------------------
    */
    'scanner' => [
        'name' => 'php2shell-scanner',
        'version' => '1.0.0',
        'cves' => 'CVE-2026-109618 / CVE-2026-109619',
    ],

    /*
    |--------------------------------------------------------------------------
    | Path target
    |--------------------------------------------------------------------------
    */
    'paths' => [
        'default' => '/index.php',
        'phpmyadmin' => '/show_config_errors.php',
        'phpmyadmin_marker' => 'phpMyAdmin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Batas maksimal target per batch scan.
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'max_targets_per_batch' => (int) env('PHP2SHELL_MAX_TARGETS', 25),
    ],

    /*
    |--------------------------------------------------------------------------
    | Eksekusi
    |--------------------------------------------------------------------------
    | Jika true, pekerjaan antrian dijalankan secara synchronous (dispatchSync)
    | sehingga hasil scan tersedia segera tanpa harus menjalankan queue worker.
    | Jika false, gunakan database queue dengan `php artisan queue:work`.
    |
    */
    'execute_sync' => (bool) filter_var(env('PHP2SHELL_EXECUTE_SYNC', '1'), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Jaringan, timeout, dan batas respons
    |--------------------------------------------------------------------------
    */
    'network' => [
        'connect_timeout' => (int) env('PHP2SHELL_CONNECT_TIMEOUT', 10),
        'request_timeout' => (int) env('PHP2SHELL_REQUEST_TIMEOUT', 60),
        'max_scan_duration' => (int) env('PHP2SHELL_MAX_SCAN_DURATION', 600),
        'max_response_bytes' => (int) env('PHP2SHELL_MAX_RESPONSE_BYTES', 1048576),
        'verify_ssl' => (bool) filter_var(env('PHP2SHELL_VERIFY_SSL', '1'), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    |--------------------------------------------------------------------------
    | Port yang diizinkan (hanya standar HTTP/HTTPS secara default).
    |--------------------------------------------------------------------------
    */
    'allowed_ports' => [80, 443],

    /*
    |--------------------------------------------------------------------------
    | Mode SAPI untuk aturan verediksi: auto, mod_php, atau fpm.
    |--------------------------------------------------------------------------
    */
    'sapi' => env('PHP2SHELL_SAPI', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Rate limiting per endpoint scan (format: "jumlah,menit").
    |--------------------------------------------------------------------------
    */
    'rate_limit' => env('PHP2SHELL_RATE_LIMIT', '10,1'),

];
