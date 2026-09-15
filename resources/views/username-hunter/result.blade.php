@extends('layouts.app')
@section('content')
<section class="mx-auto max-w-7xl px-5 py-12 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-5">
        <div>
            <p class="font-display text-xs uppercase tracking-[.24em] text-coral">[ username hunter result ]</p>
            <h1 class="mt-3 font-display text-3xl font-bold">{{ '@'.$report['username'] }}</h1>
            <p class="mt-2 font-display text-xs text-ink/50 dark:text-[#b8ffcf]/50">STATUS: {{ $scan->status }} · {{ $report['summary']['scan_time_seconds'] }}s scan time</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button class="action" onclick="copyReport()">Copy report</button>
            <button class="action" onclick="downloadJson()">JSON</button>
            <a href="{{ route('username-hunter.index') }}" class="action">New scan</a>
        </div>
    </div>

    <div class="mt-10 border border-coral/50 bg-sand/20 p-5 font-display text-xs leading-6 text-ink/60 dark:text-[#b8ffcf]/60">
        &gt; SCAN COMPLETE<br>
        &gt; PLATFORMS CHECKED: {{ $report['summary']['total_checked'] }}<br>
        &gt; FOUND: {{ $report['summary']['found'] }} · NOT FOUND: {{ $report['summary']['not_found'] }} · UNKNOWN: {{ $report['summary']['unknown'] }}<br>
        &gt; NOTE: SAME USERNAME ACROSS PLATFORMS DOES NOT CONFIRM SAME OWNER
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <div class="border border-coral/40 bg-sand/20 p-5 dark:bg-[#0d2117]/20">
            <p class="font-display text-xs uppercase tracking-wider text-coral">found</p>
            <p class="mt-2 font-display text-3xl font-bold text-coral">{{ $report['summary']['found'] }}</p>
        </div>
        <div class="border border-coral/40 bg-sand/20 p-5 dark:bg-[#0d2117]/20">
            <p class="font-display text-xs uppercase tracking-wider text-ink/50 dark:text-[#b8ffcf]/50">not found</p>
            <p class="mt-2 font-display text-3xl font-bold">{{ $report['summary']['not_found'] }}</p>
        </div>
        <div class="border border-coral/40 bg-sand/20 p-5 dark:bg-[#0d2117]/20">
            <p class="font-display text-xs uppercase tracking-wider text-ink/50 dark:text-[#b8ffcf]/50">unknown</p>
            <p class="mt-2 font-display text-3xl font-bold">{{ $report['summary']['unknown'] }}</p>
        </div>
    </div>

    <div class="mt-8 border border-coral/40 bg-paper dark:bg-[#07110c]">
        <div class="border-b border-coral/40 bg-sand/60 px-5 py-4 dark:bg-[#0d2117] flex items-center justify-between">
            <p class="font-display text-sm font-bold uppercase tracking-wider text-coral">[ platform results ]</p>
            <span class="font-display text-xs text-ink/50 dark:text-[#b8ffcf]/50">{{ count($report['results']) }} platforms</span>
        </div>
        <div class="divide-y divide-coral/20">
            @foreach($report['results'] as $result)
            <article class="p-5">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <span class="inline-flex items-center justify-center w-10 h-10 border border-coral/30 font-display text-sm font-bold text-coral">{{ strtoupper(substr($result['platform'], 0, 2)) }}</span>
                        <div>
                            <p class="font-display text-lg font-bold">{{ $result['platform'] }}</p>
                            <p class="font-display text-xs text-ink/50 dark:text-[#b8ffcf]/50">{{ '@'.$result['username'] }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        @if($result['status'] === 'FOUND')
                            <span class="font-display text-xs font-bold uppercase tracking-wider px-3 py-1 border border-coral/50 text-coral">FOUND</span>
                        @elseif($result['status'] === 'NOT FOUND')
                            <span class="font-display text-xs font-bold uppercase tracking-wider px-3 py-1 border border-ink/20 text-ink/50 dark:border-[#b8ffcf]/20 dark:text-[#b8ffcf]/50">NOT FOUND</span>
                        @else
                            <span class="font-display text-xs font-bold uppercase tracking-wider px-3 py-1 border border-yellow-400/50 text-yellow-400">UNKNOWN</span>
                        @endif
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-4 font-display text-xs text-ink/50 dark:text-[#b8ffcf]/50">
                    @if($result['http_status'])
                        <span>HTTP: {{ $result['http_status'] }}</span>
                    @endif
                    @if($result['response_time_ms'])
                        <span>Response: {{ $result['response_time_ms'] }}ms</span>
                    @endif
                    @if($result['error'])
                        <span class="text-yellow-400">Error: {{ $result['error'] }}</span>
                    @endif
                </div>
                @if($result['status'] === 'FOUND')
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ $result['profile_url'] }}" target="_blank" rel="noopener noreferrer" class="action">Open Profile</a>
                    <button class="action" onclick="copyToClipboard('{{ $result['profile_url'] }}')">Copy URL</button>
                </div>
                @endif
            </article>
            @endforeach
        </div>
    </div>

    <p class="mt-6 text-xs leading-5 text-ink/50 dark:text-[#b8ffcf]/50">Disclaimer: Results are based on public HTTP responses only. A "FOUND" status means the username exists on the platform, not that the account belongs to a specific individual. "UNKNOWN" means the platform blocked automated verification or returned an ambiguous response.</p>
</section>
<script>
const report = @json($report);
function copyReport() {
    navigator.clipboard.writeText(JSON.stringify(report, null, 2));
}
function downloadJson() {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([JSON.stringify(report, null, 2)], { type: 'application/json' }));
    a.download = 'username-hunter-{{ $report['username'] }}.json';
    a.click();
}
function copyToClipboard(text) {
    navigator.clipboard.writeText(text);
}
</script>
@endsection
