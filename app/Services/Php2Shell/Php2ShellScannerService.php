<?php

namespace App\Services\Php2Shell;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

/**
 * Service utama PHP2Shell Scanner (native PHP/Laravel, tanpa Python).
 *
 * Memetakan perilaku protokol scanner referensi Assetnote php2shell-scanner:
 * permintaan kontrol multipart minimal diikuti hingga tiga probe multipart
 * dengan kunci array PHP bersarang 62 level, verediksi berdasarkan sinyal
 * crash (kelelahan memori / HTTP 500 kosong diferensial / kegagalan worker
 * PHP-FPM) dan versi PHP yang dikabarkan melalui header X-Powered-By.
 */
class Php2ShellScannerService
{
    public const SCANNER_VERSION = '1.0.0';

    private Php2ShellRequestBuilder $builder;
    private Php2ShellResponseAnalyzer $analyzer;
    private Php2ShellResultParser $parser;
    private Php2ShellTargetValidator $validator;

    private int $connectTimeout;
    private int $requestTimeout;
    private int $maxScanDuration;
    private int $maxResponseBytes;
    private bool $verifySsl;
    private string $sapi;
    private string $defaultPath;
    private string $phpmyadminPath;
    private string $phpmyadminMarker;

    public function __construct(
        Php2ShellRequestBuilder $builder,
        Php2ShellResponseAnalyzer $analyzer,
        Php2ShellResultParser $parser,
        Php2ShellTargetValidator $validator,
        array $config = []
    ) {
        $this->builder = $builder;
        $this->analyzer = $analyzer;
        $this->parser = $parser;
        $this->validator = $validator;

        $network = Arr::get($config, 'network', []);
        $this->connectTimeout = (int) Arr::get($network, 'connect_timeout', 10);
        $this->requestTimeout = (int) Arr::get($network, 'request_timeout', 60);
        $this->maxScanDuration = (int) Arr::get($network, 'max_scan_duration', 600);
        $this->maxResponseBytes = (int) Arr::get($network, 'max_response_bytes', 1048576);
        $this->verifySsl = (bool) Arr::get($network, 'verify_ssl', true);
        $this->sapi = (string) Arr::get($config, 'sapi', 'auto');

        $paths = Arr::get($config, 'paths', []);
        $this->defaultPath = (string) Arr::get($paths, 'default', '/index.php');
        $this->phpmyadminPath = (string) Arr::get($paths, 'phpmyadmin', '/show_config_errors.php');
        $this->phpmyadminMarker = (string) Arr::get($paths, 'phpmyadmin_marker', 'phpMyAdmin');
    }

    public function scanSingle(string $url, array $options = []): array
    {
        $results = $this->scanTargets([$url], $options);

        return $results[0] ?? $this->errorResult($url, 'Tidak dapat menghasilkan hasil scan.');
    }

    public function scanTargets(array $urls, array $options = []): array
    {
        $results = [];
        $started = microtime(true);
        $sapi = (string) (Arr::get($options, 'sapi', $this->sapi));

        foreach ($urls as $index => $url) {
            if ((microtime(true) - $started) > $this->maxScanDuration) {
                $results[] = $this->timeoutResult($url, 'Durasi scan maksimum tercapai.');
                continue;
            }

            $normalized = null;
            try {
                $normalized = $this->validator->validate($url);
            } catch (RuntimeException $e) {
                $results[] = $this->errorResult($url, $e->getMessage());
                continue;
            }

            $hostname = parse_url($normalized, PHP_URL_HOST) ?? '';
            $paths = $this->targetPaths($normalized);

            $targetResult = null;
            foreach ($paths as $path) {
                $targetResult = $this->checkUrl($path, $sapi);
                $targetResult['target'] = $url;
                $targetResult['normalized_url'] = $normalized;
                $targetResult['hostname'] = $hostname;
                $targetResult['path'] = $path;
                $targetResult['checked_at'] = now();

                if ($targetResult['verdict'] === 'VULNERABLE') {
                    break;
                }
            }

            $results[] = $targetResult ?? $this->errorResult($url, 'Tidak ada path yang dapat diuji.');
        }

        return $results;
    }
    private function checkUrl(string $url, string $sapi): array
    {
        $options = ['timeout' => $this->requestTimeout];

        try {
            $control = $this->sendRequest($url, $this->builder->controlBody(), $this->builder->controlBoundary(), $options);
        } catch (ScanTimeoutException $e) {
            return $this->verdictResult('TIMEOUT', 'Koneksi kontrol melebihi batas waktu.', null, null, null, $e->getMessage());
        } catch (ScanUnreachableException $e) {
            return $this->verdictResult('UNREACHABLE', 'Target tidak dapat dicapai.', null, null, null, $e->getMessage());
        } catch (\Throwable $e) {
            return $this->verdictResult('ERROR', 'Gagal mengirim permintaan kontrol.', null, null, null, $e->getMessage());
        }

        $poweredBy = $this->parser->poweredBy($control['headers'] ?? []);
        $controlStatus = $control['status_code'] ?? null;
        $controlBody = $control['body'] ?? '';

        $versionVerdict = $this->analyzer->versionVerdict($poweredBy);
        $rejected = false;
        $rejectedReason = null;

        foreach ($this->builder::PROBES as $probe) {
            [$sizeMib, $parts] = $probe;

            try {
                $probeBody = $this->builder->buildProbeBody($sizeMib, $parts);
                $probe = $this->sendRequest($url, $probeBody, $this->builder->probeBoundary(), $options);
            } catch (ScanTimeoutException $e) {
                return $this->verdictResult('TIMEOUT', 'Probe melebihi batas waktu.', $poweredBy, $control, $sapi, $e->getMessage());
            } catch (ScanUnreachableException $e) {
                return $this->verdictResult('UNREACHABLE', 'Target tidak dapat dicapai selama probe.', $poweredBy, $control, $sapi, $e->getMessage());
            } catch (\Throwable $e) {
                return $this->verdictResult('ERROR', 'Gagal mengirim permintaan probe.', $poweredBy, $control, $sapi, $e->getMessage());
            }

            $probeStatus = $probe['status_code'] ?? null;

            if ($this->analyzer->isRejected($probeStatus)) {
                $rejected = true;
                $rejectedReason = "probe {$sizeMib} MiB/{$parts} bagian ditolak dengan HTTP {$probeStatus} sebelum PHP memprosesnya.";
                break;
            }

            $signal = $this->analyzer->crashSignal(
                ['status_code' => $controlStatus, 'body' => $controlBody],
                ['status_code' => $probeStatus, 'body' => $probe['body'] ?? ''],
                $sapi
            );

            if ($signal !== null) {
                return $this->verdictResult(
                    'VULNERABLE',
                    "probe {$sizeMib} MiB/{$parts} bagian crash handler: {$signal['signal']}",
                    $poweredBy, $control, $sapi, null,
                    ['sapi' => $signal['sapi'], 'excerpt' => $this->parser->excerpt($probe['body'] ?? null)]
                );
            }

            if ($versionVerdict === 'FIXED') {
                return $this->verdictResult(
                    'NOT VULNERABLE',
                    "tidak ada crash dan X-Powered-By melaporkan rilis yang sudah dipatch: {$poweredBy}",
                    $poweredBy, $control, $sapi, null,
                    ['excerpt' => $this->parser->excerpt($probe['body'] ?? null)]
                );
            }
        }

        if ($versionVerdict === 'VULNERABLE') {
            return $this->verdictResult(
                'VULNERABLE',
                "tidak ada crash; X-Powered-By melaporkan rilis yang belum dipatch: {$poweredBy}",
                $poweredBy, $control, $sapi, null,
                ['excerpt' => $this->parser->excerpt($controlBody)]
            );
        }

        if ($versionVerdict === 'FIXED') {
            return $this->verdictResult(
                'NOT VULNERABLE',
                "tidak ada crash dan X-Powered-By melaporkan rilis yang sudah dipatch: {$poweredBy}",
                $poweredBy, $control, $sapi, null,
                ['excerpt' => $this->parser->excerpt($controlBody)]
            );
        }

        if ($rejected) {
            return $this->verdictResult(
                'INCONCLUSIVE',
                $rejectedReason ?? 'Probe ditolak oleh server.',
                $poweredBy, $control, $sapi, null,
                ['excerpt' => $this->parser->excerpt($controlBody)]
            );
        }

        return $this->verdictResult(
            'NOT VULNERABLE',
            'tidak ada crash dan tidak ada versi PHP yang diiklanan.',
            $poweredBy, $control, $sapi, null,
            ['excerpt' => $this->parser->excerpt($controlBody)]
        );
    }

    private function targetPaths(string $url): array
    {
        return [$this->defaultPath];
    }

    private function sendRequest(string $url, string $body, string $boundary, array $options): array
    {
        $timeout = (int) (Arr::get($options, 'timeout', $this->requestTimeout));

        try {
            $request = Http::withHeaders([
                'User-Agent' => $this->builder::USER_AGENT,
                'Connection' => 'close',
                'Accept' => '*/*',
            ])
                ->withBody($body, 'multipart/form-data; boundary=' . $boundary)
                ->timeout($timeout)
                ->connectTimeout($this->connectTimeout)
                ->withoutRedirecting()
                ->withOptions([
                    'verify' => $this->verifySsl,
                    'on_headers' => function ($response) {
                        $contentLength = (int) ($response->getHeaderLine('content-length') ?? 0);
                        if ($contentLength > 0 && $contentLength > $this->maxResponseBytes) {
                            throw new \RuntimeException('Respons terlalu besar.');
                        }
                    },
                ])
                ->post($url);
        } catch (ConnectionException $e) {
            $message = $e->getMessage();
            if (stripos($message, 'timed out') !== false || stripos($message, 'timeout') !== false) {
                throw new ScanTimeoutException($message);
            }

            throw new ScanUnreachableException($message);
        } catch (RequestException $e) {
            throw new ScanUnreachableException($e->getMessage());
        }

        return [
            'status_code' => $request->status(),
            'headers' => $request->headers(),
            'body' => $request->body(),
        ];
    }

    private function verdictResult(
        string $verdict,
        string $reason,
        ?string $poweredBy,
        array $control = [],
        string $sapi = 'auto',
        ?string $error = null,
        array $extra = []
    ): array {
        return [
            'verdict' => $verdict,
            'vulnerable' => $verdict === 'VULNERABLE',
            'reason' => $reason,
            'error' => $error,
            'x_powered_by' => $poweredBy,
            'sapi' => $extra['sapi'] ?? null,
            'evidence' => $extra['excerpt'] ?? null,
            'headers' => $extra['headers'] ?? null,
            'control_status' => $control['status_code'] ?? null,
        ];
    }

    private function errorResult(string $target, string $message): array
    {
        return [
            'target' => $target,
            'verdict' => 'ERROR',
            'vulnerable' => null,
            'reason' => $message,
            'error' => $message,
            'x_powered_by' => null,
            'sapi' => null,
            'evidence' => null,
            'checked_at' => now(),
        ];
    }

    private function timeoutResult(string $target, string $message): array
    {
        return [
            'target' => $target,
            'verdict' => 'TIMEOUT',
            'vulnerable' => null,
            'reason' => $message,
            'error' => $message,
            'x_powered_by' => null,
            'sapi' => null,
            'evidence' => null,
            'checked_at' => now(),
        ];
    }
}
