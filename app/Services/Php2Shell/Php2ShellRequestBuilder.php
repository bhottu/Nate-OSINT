<?php

namespace App\Services\Php2Shell;

/**
 * Pembangun permintaan HTTP mentah yang memetakan struktur protokol scanner
 * referensi (Assetnote php2shell-scanner) tanpa library Python.
 */
class Php2ShellRequestBuilder
{
    public const CVES = 'CVE-2026-109618 / CVE-2026-109619';
    public const DEFAULT_PATH = '/index.php';
    public const PHPMYADMIN_PATH = '/show_config_errors.php';
    public const PHPMYADMIN_MARKER = 'phpMyAdmin';

    public const CONTROL_BOUNDARY = 'x';
    public const PROBE_BOUNDARY = '';
    public const CONTROL_BODY = "--x\nContent-Disposition:name=a;filename=\n\n";
    public const NESTED_KEYS = 62;

    // [ukuran MiB, jumlah bagian] – total sekitar 29 MiB per target.
    public const PROBES = [[1, 4000], [8, 1020], [20, 1020]];

    public const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.113 Safari/537.36 PHP2Shell/1.0.0';

    public function controlBody(): string
    {
        return self::CONTROL_BODY;
    }

    public function controlBoundary(): string
    {
        return self::CONTROL_BOUNDARY;
    }

    public function probeBoundary(): string
    {
        return self::PROBE_BOUNDARY;
    }

    /**
     * Bangun tubuh multipart probe dengan kunci array PHP bersarang 62 level
     * dan total tubuh sekitar $sizeMib MiB terbagi menjadi $parts bagian.
     */
    public function buildProbeBody(int $sizeMib, int $parts): string
    {
        $boundary = self::PROBE_BOUNDARY;
        $nestedKey = $this->nestedKey(self::NESTED_KEYS);

        $partHeader = "--{$boundary}\nContent-Disposition: form-data; name=\"{$nestedKey}\"; filename=\"probe\"\n\n";
        $partClose = "\n";

        $targetBytes = (int) max($sizeMib, 1) * 1024 * 1024;
        $parts = (int) max($parts, 1);
        $perPart = (int) floor($targetBytes / $parts);

        $body = '';
        for ($i = 0; $i < $parts; $i++) {
            $body .= $partHeader;
            $fill = max($perPart - strlen($partHeader) - strlen($partClose), 0);
            $body .= str_repeat('A', $fill);
            $body .= $partClose;

            if (strlen($body) >= $targetBytes) {
                break;
            }
        }

        // Penutup multipart dengan boundary kosong: "--" + "" + "--" = "----".
        $body .= "--{$boundary}--\n";

        return $body;
    }

    private function nestedKey(int $levels): string
    {
        $key = 'a';
        for ($i = 0; $i < $levels; $i++) {
            $key .= '[0]';
        }

        return $key;
    }

    /**
     * Header permintaan yang digunakan untuk kontrol dan probe.
     */
    public function requestHeaders(string $boundary, int $contentLength): array
    {
        return [
            'User-Agent' => self::USER_AGENT,
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            'Content-Length' => (string) $contentLength,
            'Connection' => 'close',
            'Accept' => '*/*',
        ];
    }
}
