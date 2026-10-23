<?php

namespace App\Services\Php2Shell;

/**
 * Menganalisis respons HTTP untuk menentukan sinyal crash dan verediksi versi
 * PHP sesuai kriteria scanner referensi.
 */
class Php2ShellResponseAnalyzer
{
    public const REJECTED_STATUSES = [400, 403, 411, 413, 414, 417, 422, 429, 431];

    // Patokan versi PHP yang sudah dipatch: [major, minor, patch_minimum].
    public const FIXED_VERSIONS = [
        [8, 2, 35],
        [8, 3, 36],
        [8, 4, 27],
        [8, 5, 12],
    ];

    public const MEMORY_SIGNATURES = [
        '/Allowed memory size/i',
        '/Fatal error: Allowed memory/i',
        '/out of memory/i',
        '/memory exhausted/i',
    ];

    /**
     * Bandingkan versi yang diiklanan di header X-Powered-By dengan daftar
     * rilis yang sudah dipatch.
     *
     * @return 'VULNERABLE'|'FIXED'|null (null = versi tidak diketahui/tidak dapat diiklasifikasikan)
     */
    public function versionVerdict(?string $poweredBy): ?string
    {
        if (! is_string($poweredBy) || $poweredBy === '') {
            return null;
        }

        if (! preg_match('#PHP/(\d+)\.(\d+)\.(\d+)#', $poweredBy, $match)) {
            return null;
        }

        $major = (int) $match[1];
        $minor = (int) $match[2];
        $patch = (int) $match[3];

        foreach (self::FIXED_VERSIONS as $fixed) {
            if ($major === $fixed[0] && $minor === $fixed[1]) {
                return $patch < $fixed[2] ? 'VULNERABLE' : 'FIXED';
            }
        }

        // Versi mayor.minor tidak ada dalam daftar -> tidak dapat diklasifikasikan.
        return null;
    }

    /**
     * Deteksi sinyal crash dari pasangan respons kontrol dan probe.
     *
     * @return ['sapi' => string, 'signal' => string]|null
     */
    public function crashSignal(array $control, array $probe, string $sapi): ?array
    {
        $modes = $sapi === 'auto' ? ['mod_php', 'fpm'] : [$sapi];

        $probeStatus = $probe['status_code'] ?? null;
        $probeBody = $probe['body'] ?? '';
        $controlStatus = $control['status_code'] ?? null;

        if (in_array('mod_php', $modes, true)) {
            foreach (self::MEMORY_SIGNATURES as $pattern) {
                if (is_string($probeBody) && preg_match($pattern, $probeBody) === 1) {
                    return [
                        'sapi' => 'mod_php',
                        'signal' => 'PHP memory-exhaustion / fatal error dalam respons probe.',
                    ];
                }
            }
        }

        if (in_array('fpm', $modes, true)) {
            if ($probeStatus === 500 && $probeBody === '' && $controlStatus !== 500) {
                return [
                    'sapi' => 'fpm',
                    'signal' => 'Diferensial HTTP 500 kosong (gagal PHP-FPM worker).',
                ];
            }
        }

        return null;
    }

    public function isRejected(int $status): bool
    {
        return in_array($status, self::REJECTED_STATUSES, true);
    }
}
