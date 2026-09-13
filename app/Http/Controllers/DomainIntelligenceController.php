<?php

namespace App\Http\Controllers;

use App\Models\DomainIntelligence\DomainScan;
use App\Services\DomainIntelligence\DomainScanOrchestrator;
use Illuminate\Http\Request;
use Throwable;

/**
 * Domain Intelligence controller.
 *
 * Passive OSINT only: DNS, subdomains, certificates, TLS and HTTP headers.
 * No exploitation, no credential attacks, no intrusive scanning.
 */
class DomainIntelligenceController extends Controller
{
    public function __construct(private DomainScanOrchestrator $orchestrator) {}

    public function index(Request $request)
    {
        return view('domain-intelligence.index', [
            'hostname' => old('hostname', $request->query('hostname')),
        ]);
    }

    public function scan(Request $request)
    {
        $validated = $request->validate([
            'hostname' => ['required', 'string', 'max:253'],
        ]);

        try {
            $scan = $this->orchestrator->scan($validated['hostname']);
        } catch (Throwable $exception) {
            return back()
                ->withInput()
                ->withErrors(['hostname' => $exception->getMessage()]);
        }

        return redirect()->route('domain-intelligence.show', $scan);
    }

    public function show(DomainScan $scan)
    {
        $scan->load(['dnsRecords', 'subdomains', 'certificates', 'ips', 'findings', 'emailSecurity']);

        return view('domain-intelligence.show', ['scan' => $scan]);
    }
}