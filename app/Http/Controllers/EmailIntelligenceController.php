<?php

namespace App\Http\Controllers;

use App\Models\EmailScan;
use App\Models\EmailScanResult;
use App\Services\EmailIntelligence\EmailIntelService;
use App\Services\EmailIntelligence\EmailNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailIntelligenceController extends Controller
{
    public function __construct(
        private EmailIntelService $service,
        private EmailNormalizer $normalizer,
    ) {}

    public function index()
    {
        return view('email-intelligence.index', [
            'hibpConfigured' => (bool) config('email_intelligence.hibp.api_key'),
        ]);
    }

    public function scan(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'min:3', 'max:254'],
        ]);

        $raw = trim($validated['email']);
        $parsed = $this->normalizer->parse($raw);

        $scan = EmailScan::create([
            'email' => mb_substr($raw, 0, 254),
            'normalized_email' => mb_substr($parsed['normalized'], 0, 254),
            'local_part' => mb_substr($parsed['local_part'], 0, 64),
            'domain' => mb_substr($parsed['domain'], 0, 253),
            'status' => 'PENDING',
        ]);

        try {
            $report = $this->service->scan($raw);
        } catch (\Throwable) {
            Log::warning('EmailIntel: scan crashed', ['domain' => $parsed['domain']]);
            $scan->update(['status' => 'ERROR']);

            return redirect()
                ->route('email-intelligence.index')
                ->with('error', 'Scan failed unexpectedly. Please try again.');
        }

        foreach ($report['results'] as $result) {
            EmailScanResult::create([
                'email_scan_id' => $scan->id,
                'platform' => mb_substr($result->platform, 0, 40),
                'identifier' => $result->identifier !== null ? mb_substr($result->identifier, 0, 255) : null,
                'profile_url' => $result->profileUrl,
                'status' => mb_substr($result->status, 0, 30),
                'confidence' => $result->confidence,
                'evidence' => $result->evidence,
                'source_url' => $result->sourceUrl,
                'response_time' => $result->responseTime,
                'error_message' => $result->errorMessage,
            ]);
        }

        $scan->update([
            'status' => mb_substr($report['summary']['status'], 0, 20),
            'total_sources_checked' => $report['summary']['total_sources_checked'],
            'total_potential_matches' => $report['summary']['potential'],
            'total_unverified' => $report['summary']['unverified'],
            'total_unable_to_verify' => $report['summary']['unable_to_verify'],
            'provider' => $report['domain_report']['email_provider'] ?? null,
            'disposable' => $parsed['is_disposable'],
            'technical_report' => $report['domain_report'],
            'breach_report' => $report['breach_report'],
            'scan_time_seconds' => $report['summary']['took_seconds'],
        ]);

        return redirect()->route('email-intelligence.show', ['scan' => $scan->public_id]);
    }

    public function show(EmailScan $scan)
    {
        $scan->load('results');

        $results = $scan->results->map(fn ($r) => (object) [
            'platform' => $r->platform,
            'identifier' => $r->identifier,
            'profile_url' => $r->profile_url,
            'status' => $r->status,
            'confidence' => $r->confidence,
            'evidence' => $r->evidence,
            'source_url' => $r->source_url,
            'response_time' => $r->response_time,
            'error_message' => $r->error_message,
        ]);

        $summary = [
            'status' => $scan->status,
            'total_sources_checked' => $scan->total_sources_checked,
            'potential' => $scan->total_potential_matches,
            'unverified' => $scan->total_unverified,
            'unable_to_verify' => $scan->total_unable_to_verify,
            'not_found' => $results->where('status', 'NOT FOUND')->count(),
            'errors' => $results->where('status', 'ERROR')->count(),
            'took_seconds' => $scan->scan_time_seconds,
        ];

        return view('email-intelligence.result', [
            'scan' => $scan,
            'results' => $results,
            'summary' => $summary,
            'domainReport' => $scan->technical_report,
            'breachReport' => $scan->breach_report,
            'hibpConfigured' => (bool) config('email_intelligence.hibp.api_key'),
        ]);
    }

    public function export(EmailScan $scan)
    {
        $scan->load('results');

        $payload = [
            'tool' => 'Email Intelligence (Nate-OSINT)',
            'generated_at' => now()->toIso8601String(),
            'disclaimer' => 'Results are indicative only, based on publicly observable information, and do not prove account ownership.',
            'scan' => [
                'submitted_email' => $scan->email,
                'normalized_email' => $scan->normalized_email,
                'domain' => $scan->domain,
                'status' => $scan->status,
                'provider' => $scan->provider,
                'disposable' => $scan->disposable,
                'total_sources_checked' => $scan->total_sources_checked,
                'total_potential_matches' => $scan->total_potential_matches,
                'scan_time_seconds' => $scan->scan_time_seconds,
                'created_at' => $scan->created_at?->toIso8601String(),
            ],
            'domain_intelligence' => $scan->technical_report,
            'exposure_check' => $scan->breach_report,
            'results' => $scan->results->map(fn ($r) => [
                'platform' => $r->platform,
                'identifier' => $r->identifier,
                'profile_url' => $r->profile_url,
                'status' => $r->status,
                'confidence' => $r->confidence,
                'evidence' => $r->evidence,
                'source_url' => $r->source_url,
                'error_message' => $r->error_message,
            ])->all(),
        ];

        $filename = 'email-intel-'.$scan->public_id.'-'.now()->format('Ymd-His').'.json';

        return response()->json($payload, 200, [
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
