@extends('layouts.app')
@section('content')
@php $statusColor = match ($summary['status']) {
    'POTENTIAL MATCH', 'FOUND' => 'text-emerald-400 border-emerald-400/60',
    'NOT FOUND' => 'text-ink/70 dark:text-[#b8ffcf]/70 border-coral/40',
    'ERROR' => 'text-red-400 border-red-400/60',
    default => 'text-amber-300 border-amber-400/60',
}; @endphp
<section class="mx-auto max-w-6xl px-5 py-16 lg:px-8 lg:py-24">
    <p class="font-display text-xs font-bold uppercase tracking-[.24em] text-coral">[ email / intelligence / report ]</p>
    <h1 class="mt-4 font-display text-4xl font-bold leading-[.95] lg:text-6xl">Scan result for <span class="text-coral">{{ $scan->email }}</span></h1>
    <div class="mt-6 flex flex-wrap items-center gap-3 font-display text-xs text-ink/50 dark:text-[#b8ffcf]/50">
        <span class="border px-3 py-1 {{ $statusColor }}">{{ $summary['status'] }}</span>
        <span>checked: {{ $summary['total_sources_checked'] }}</span><span class="text-coral">//</span>
        <span>potential: {{ $summary['potential'] }}</span><span class="text-coral">//</span>
        <span>unverified: {{ $summary['unverified'] }}</span><span class="text-coral">//</span>
        <span>unable to verify: {{ $summary['unable_to_verify'] }}</span><span class="text-coral">//</span>
        <span>{{ $scan->scan_time_seconds ? $scan->scan_time_seconds.'s' : '—' }}</span><span class="text-coral">//</span>
        <span>{{ $scan->created_at?->format('Y-m-d H:i:s T') }}</span>
    </div>
    <div class="mt-6 flex flex-wrap gap-3 font-display text-xs">
        <a href="{{ route('email-intelligence.scan') }}" onclick="event.preventDefault();document.getElementById('rescan-form').submit();" class="border border-coral/40 px-4 py-2 uppercase tracking-wider text-ink/70 hover:border-coral hover:text-coral dark:text-[#b8ffcf]/70">Scan again ?</a>
        <a href="{{ route('email-intelligence.index') }}" class="border border-coral/40 px-4 py-2 uppercase tracking-wider text-ink/70 hover:border-coral hover:text-coral dark:text-[#b8ffcf]/70">Clear / new scan</a>
        <a href="{{ route('email-intelligence.export', $scan) }}" class="border border-coral/40 px-4 py-2 uppercase tracking-wider text-ink/70 hover:border-coral hover:text-coral dark:text-[#b8ffcf]/70">Export results ?</a>
    </div>
    <form id="rescan-form" action="{{ route('email-intelligence.scan') }}" method="POST" class="hidden">@csrf <input type="hidden" name="email" value="{{ $scan->email }}"></form>

    {{-- Email Summary --}}
    <div class="mt-12 border border-coral/40 bg-sand/20 p-6 dark:bg-[#0d2117]/20">
        <p class="font-display text-xs uppercase tracking-[.2em] text-coral">[ email / summary ]</p>
        <dl class="mt-4 grid gap-x-8 gap-y-3 font-display text-sm sm:grid-cols-2">
            <div class="flex justify-between gap-4 border-b border-coral/10 pb-2"><dt class="text-ink/50 dark:text-[#b8ffcf]/50">Submitted</dt><dd class="text-right">{{ $scan->email }}</dd></div>
            <div class="flex justify-between gap-4 border-b border-coral/10 pb-2"><dt class="text-ink/50 dark:text-[#b8ffcf]/50">Normalized</dt><dd class="text-right">{{ $scan->normalized_email }}</dd></div>
            <div class="flex justify-between gap-4 border-b border-coral/10 pb-2"><dt class="text-ink/50 dark:text-[#b8ffcf]/50">Format</dt><dd class="text-right">{{ $scan->local_part !== '' ? 'VALID' : 'INVALID' }}</dd></div>
            <div class="flex justify-between gap-4 border-b border-coral/10 pb-2"><dt class="text-ink/50 dark:text-[#b8ffcf]/50">Domain</dt><dd class="text-right">{{ $scan->domain }}</dd></div>
            <div class="flex justify-between gap-4 border-b border-coral/10 pb-2"><dt class="text-ink/50 dark:text-[#b8ffcf]/50">Provider</dt><dd class="text-right">{{ $scan->provider ?? 'Unknown' }}</dd></div>
            <div class="flex justify-between gap-4 border-b border-coral/10 pb-2"><dt class="text-ink/50 dark:text-[#b8ffcf]/50">Disposable</dt><dd class="text-right">{{ $scan->disposable }}</dd></div>
            <div class="flex justify-between gap-4 border-b border-coral/10 pb-2 sm:col-span-2"><dt class="text-ink/50 dark:text-[#b8ffcf]/50">Domain status</dt><dd class="text-right">{{ $domainReport['domain_status'] ?? 'UNKNOWN' }}</dd></div>
        </dl>
        @if(($domainReport['risk_notes'] ?? []) !== [])
        <ul class="mt-4 space-y-1 text-xs text-amber-300">@foreach($domainReport['risk_notes'] as $note)<li>⚠ {{ $note }}</li>@endforeach</ul>
        @endif
    </div>

    {{-- Public Account Discovery --}}
    <div class="mt-8 border border-coral/40 bg-sand/20 p-6 dark:bg-[#0d2117]/20">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="font-display text-xs uppercase tracking-[.2em] text-coral">[ public / account / discovery ]</p>
            <p class="font-display text-xs text-ink/50 dark:text-[#b8ffcf]/50">found {{ $summary['potential'] }} · not found {{ $summary['not_found'] }} · unverified {{ $summary['unverified'] }} · unable {{ $summary['unable_to_verify'] }} · errors {{ $summary['errors'] }}</p>
        </div>
        @if($results->where('status', 'FOUND')->isNotEmpty())
        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            @foreach($results->where('status', 'FOUND') as $r)
            <div class="border border-coral/30 bg-paper p-4 dark:bg-[#07110c]">
                <div class="flex items-center justify-between font-display text-xs"><span class="font-bold uppercase tracking-wider text-coral">{{ $r->platform }}</span><span class="border border-emerald-400/60 px-2 py-0.5 text-emerald-400">POTENTIAL MATCH</span></div>
                <p class="mt-3 break-all font-display text-sm"><span class="text-ink/50 dark:text-[#b8ffcf]/50">identifier:</span> {{ $r->identifier ?? '—' }}</p>
                @if($r->profile_url)<p class="mt-1 break-all font-display text-sm"><span class="text-ink/50 dark:text-[#b8ffcf]/50">url:</span> {{ $r->profile_url }}</p>@endif
                <p class="mt-3 text-xs leading-5 text-ink/60 dark:text-[#b8ffcf]/60">{{ $r->evidence }}</p>
                <div class="mt-3 flex flex-wrap items-center gap-3 font-display text-xs">
                    <span class="border border-amber-400/60 px-2 py-0.5 text-amber-300">confidence: {{ $r->confidence ?? 'Unverified' }}</span>
                    @if($r->profile_url)<a href="{{ $r->profile_url }}" target="_blank" rel="noopener noreferrer" class="border border-coral/40 px-2 py-0.5 text-coral hover:bg-coral hover:text-[#07110c]">Open profile ↗</a>@endif
                    @if($r->identifier && $r->identifier !== $scan->local_part)<a href="{{ route('username-hunter.index', ['username' => $r->identifier]) }}" class="border border-coral/40 px-2 py-0.5 text-coral hover:bg-coral hover:text-[#07110c]">Search with Username Hunter ↗</a>@endif
                </div>
                <p class="mt-2 text-[11px] leading-4 text-ink/40 dark:text-[#b8ffcf]/40">{{ $r->error_message }}</p>
            </div>
            @endforeach
        </div>
        @else
        <p class="mt-4 font-display text-sm text-ink/60 dark:text-[#b8ffcf]/60">No public account indicators found across the checked sources.</p>
        @endif

        {{-- Per-source details --}}
        <div class="mt-6 overflow-x-auto border border-coral/30 bg-paper dark:bg-[#07110c]">
            <table class="w-full min-w-[720px] font-display text-xs">
                <thead class="border-b border-coral/30 bg-sand text-left uppercase tracking-wider text-coral dark:bg-[#0d2117]"><tr><th class="px-3 py-2">Platform</th><th class="px-3 py-2">Identifier</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Confidence</th><th class="px-3 py-2">Checked</th><th class="px-3 py-2">Note / Error</th></tr></thead>
                <tbody class="divide-y divide-coral/20 text-ink/85 dark:text-[#b8ffcf]/90">
                    @foreach($results as $r)
                    <tr class="even:bg-sand/60 dark:even:bg-[#0d2117]/70">
                        <td class="px-3 py-2 font-bold">{{ $r->platform }}</td>
                        <td class="px-3 py-2 break-all">{{ $r->identifier ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $r->status }}</td>
                        <td class="px-3 py-2">{{ $r->confidence ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $r->response_time ? $r->response_time.' ms' : '—' }}</td>
                        <td class="px-3 py-2 break-all">{{ $r->error_message ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($summary['errors'] > 0)<p class="mt-3 text-xs text-amber-300">Some sources failed — all other sources completed independently. Individual errors are listed above.</p>@endif
    </div>

    {{-- Domain Intelligence --}}
    <div class="mt-8 border border-coral/40 bg-sand/20 p-6 dark:bg-[#0d2117]/20">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="font-display text-xs uppercase tracking-[.2em] text-coral">[ domain / intelligence ]</p>
            @if($scan->domain !== '')<a href="{{ route('domain-intelligence.index', ['domain' => $scan->domain]) }}" class="border border-coral/40 px-3 py-1.5 font-display text-xs uppercase tracking-wider text-coral hover:bg-coral hover:text-[#07110c]">Analyze domain ↗</a>@endif
        </div>
        @if($domainReport === null)
        <p class="mt-4 font-display text-sm text-ink/60 dark:text-[#b8ffcf]/60">Domain analysis unavailable (invalid email).</p>
        @else
        <dl class="mt-4 grid gap-x-8 gap-y-3 font-display text-sm sm:grid-cols-2">
            <div class="flex justify-between gap-4 border-b border-coral/10 pb-2"><dt class="text-ink/50 dark:text-[#b8ffcf]/50">MX</dt><dd class="text-right">{{ $domainReport['mx']['status'] ?? 'UNKNOWN' }}</dd></div>
            <div class="flex justify-between gap-4 border-b border-coral/10 pb-2"><dt class="text-ink/50 dark:text-[#b8ffcf]/50">SPF</dt><dd class="text-right">{{ $domainReport['spf']['status'] ?? 'UNKNOWN' }}</dd></div>
            <div class="flex justify-between gap-4 border-b border-coral/10 pb-2"><dt class="text-ink/50 dark:text-[#b8ffcf]/50">DKIM</dt><dd class="text-right">{{ $domainReport['dkim']['status'] ?? 'UNKNOWN' }}</dd></div>
            <div class="flex justify-between gap-4 border-b border-coral/10 pb-2"><dt class="text-ink/50 dark:text-[#b8ffcf]/50">DMARC</dt><dd class="text-right">{{ $domainReport['dmarc']['status'] ?? 'UNKNOWN' }}</dd></div>
            <div class="flex justify-between gap-4 border-b border-coral/10 pb-2"><dt class="text-ink/50 dark:text-[#b8ffcf]/50">DNSSEC</dt><dd class="text-right">{{ $domainReport['dnssec']['status'] ?? 'UNKNOWN' }}</dd></div>
            <div class="flex justify-between gap-4 border-b border-coral/10 pb-2"><dt class="text-ink/50 dark:text-[#b8ffcf]/50">Email provider</dt><dd class="text-right">{{ $domainReport['email_provider'] ?? 'Unknown' }}</dd></div>
        </dl>
        @if(($domainReport['mx']['values'] ?? []) !== [])<p class="mt-3 break-all text-xs text-ink/60 dark:text-[#b8ffcf]/60"><span class="text-ink/50 dark:text-[#b8ffcf]/50">MX records:</span> {{ implode(' · ', $domainReport['mx']['values']) }}</p>@endif
        @if(($domainReport['spf']['value'] ?? null) !== null)<p class="mt-1 break-all text-xs text-ink/60 dark:text-[#b8ffcf]/60"><span class="text-ink/50 dark:text-[#b8ffcf]/50">SPF record:</span> {{ $domainReport['spf']['value'] }}</p>@endif
        @if(($domainReport['dmarc']['value'] ?? null) !== null)<p class="mt-1 break-all text-xs text-ink/60 dark:text-[#b8ffcf]/60"><span class="text-ink/50 dark:text-[#b8ffcf]/50">DMARC record:</span> {{ $domainReport['dmarc']['value'] }}</p>@endif
        <p class="mt-1 text-xs text-ink/50 dark:text-[#b8ffcf]/40">{{ $domainReport['dnssec']['note'] ?? '' }}</p>
        @if(($domainReport['mail_servers'] ?? []) !== [])
        <p class="mt-3 font-display text-xs uppercase tracking-wider text-coral">mail servers</p>
        <div class="mt-2 space-y-1 font-display text-xs text-ink/60 dark:text-[#b8ffcf]/60">
            @foreach($domainReport['mail_servers'] as $mx)
            <p>{{ $mx['host'] }} <span class="text-ink/40 dark:text-[#b8ffcf]/40">(prio {{ $mx['priority'] ?? '—' }})</span> — A: {{ $mx['ipv4'] === [] ? '—' : implode(', ', $mx['ipv4']) }} @if($mx['ipv6'] !== []) — AAAA: {{ implode(', ', $mx['ipv6']) }}@endif</p>
            @endforeach
        </div>
        @endif
        @if(($domainReport['organization']['status'] ?? '') === 'DISCLOSED')
        <p class="mt-3 text-xs text-ink/60 dark:text-[#b8ffcf]/60"><span class="text-ink/50 dark:text-[#b8ffcf]/50">Organization (RDAP):</span> {{ $domainReport['organization']['name'] ?? '—' }} @if(($domainReport['organization']['registrar'] ?? null) !== null) · registrar: {{ $domainReport['organization']['registrar'] }}@endif</p>
        @endif
        @if(($domainReport['hosting']['status'] ?? '') === 'RESOLVED')
        <p class="mt-1 text-xs text-ink/60 dark:text-[#b8ffcf]/60"><span class="text-ink/50 dark:text-[#b8ffcf]/50">Hosting:</span> {{ $domainReport['hosting']['asn'] ?? '—' }} · {{ $domainReport['hosting']['org'] ?? '—' }} · {{ $domainReport['hosting']['country'] ?? '—' }} (approximate)</p>
        @endif
        @endif
    </div>

    {{-- Exposure Check --}}
    <div class="mt-8 border border-coral/40 bg-sand/20 p-6 dark:bg-[#0d2117]/20">
        <p class="font-display text-xs uppercase tracking-[.2em] text-coral">[ exposure / check ]</p>
        @if($breachReport === null)
        <p class="mt-4 font-display text-sm text-ink/60 dark:text-[#b8ffcf]/60">Breach check is not configured</p>
        @elseif(!($breachReport['configured'] ?? false))
        <p class="mt-4 font-display text-sm text-ink/60 dark:text-[#b8ffcf]/60">Breach check is not configured</p>
        <p class="mt-1 text-xs text-ink/50 dark:text-[#b8ffcf]/40">Operators can enable the official Have I Been Pwned API via the HIBP_API_KEY environment variable. No fake data is shown in its absence.</p>
        @elseif(($breachReport['status'] ?? '') === 'ERROR')
        <p class="mt-4 font-display text-sm text-amber-300">ERROR — {{ $breachReport['error'] ?? 'Breach service unavailable' }}</p>
        @elseif(($breachReport['total'] ?? 0) === 0)
        <p class="mt-4 font-display text-sm text-emerald-400">{{ $breachReport['status'] ?? 'NO PUBLIC BREACH RECORDS' }}</p>
        <p class="mt-1 text-xs text-ink/50 dark:text-[#b8ffcf]/40">No breach listings were returned for this address by the configured service.</p>
        @else
        <p class="mt-4 font-display text-sm text-amber-300">{{ $breachReport['total'] }} breach listing(s) found (showing {{ min($breachReport['total'], count($breachReport['breaches'])) }})</p>
        <div class="mt-3 space-y-2">
            @foreach($breachReport['breaches'] as $breach)
            <div class="border border-coral/20 px-3 py-2 font-display text-xs">
                <span class="font-bold text-coral">{{ $breach['name'] }}</span>
                @if(($breach['breach_date'] ?? null) !== null)<span class="text-ink/50 dark:text-[#b8ffcf]/40"> · breached {{ $breach['breach_date'] }}</span>@endif
                @if(($breach['data_classes'] ?? []) !== [])<p class="mt-1 text-ink/60 dark:text-[#b8ffcf]/60">exposed data categories: {{ implode(', ', $breach['data_classes']) }}</p>@endif
            </div>
            @endforeach
        </div>
        @endif
        <p class="mt-3 text-xs text-ink/40 dark:text-[#b8ffcf]/40">Only breach metadata (service name, date, data categories) is displayed. Passwords and leaked record contents are never shown or stored. Results may not be complete.</p>
    </div>

    {{-- Evidence and Limitations --}}
    <div class="mt-8 border border-amber-400/70 bg-amber-400/10 p-6">
        <p class="font-display text-xs font-bold uppercase tracking-[.2em] text-amber-300">[ evidence / limitations ]</p>
        <ul class="mt-3 space-y-2 text-sm leading-6 text-amber-100/80">
            <li>• This report is based on publicly observable information and does not prove ownership of any account.</li>
            <li>• A "POTENTIAL MATCH" means a public source returned a possible association — treat every result as unverified until independently corroborated.</li>
            <li>• Identifier probes derive handles from the email local part; similar handles are common and do not imply the same owner.</li>
            <li>• Valid format, MX presence, and a Gravatar profile do not prove the mailbox is active or currently controlled by anyone in particular.</li>
            <li>• Some platforms do not offer a public, unauthenticated verification path; those report "UNABLE TO VERIFY" instead of being guessed.</li>
            <li>• No login attempts, password-reset probes, enumeration, or private-data access were used. Use only for lawful research on public information.</li>
        </ul>
    </div>
</section>
@endsection
