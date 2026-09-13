@extends('layouts.app')
@section('content')
<section class="mx-auto max-w-5xl px-5 py-16 lg:px-8 lg:py-24" x-data="{ loading: false }">
    <p class="font-display text-xs font-bold uppercase tracking-[.24em] text-coral">[ passive domain intelligence ]</p>
    <h1 class="mt-4 max-w-4xl font-display text-5xl font-bold leading-[.95] lg:text-7xl">Map the<br><span class="text-coral">infrastructure.</span></h1>
    <p class="mt-7 max-w-2xl text-lg leading-8 text-ink/65 dark:text-paper/65">Passive OSINT reconnaissance: DNS records, email security posture, subdomains from Certificate Transparency logs, TLS certificate health, and HTTP security headers. No exploitation or intrusive probing.</p>
    <div class="mt-12 border border-coral/50 bg-sand/30 p-6 shadow-[0_0_32px_rgba(57,255,136,.08)] dark:bg-sand/20">
        <div class="mb-6 flex justify-between border-b border-coral/20 pb-4 font-display text-xs text-ink/50 dark:text-paper/50"><span>~/inspection-tools/domain-intelligence</span><span>MODE: PASSIVE</span></div>
        <form action="{{ route('domain-intelligence.scan') }}" method="POST" @submit="loading = true" :aria-busy="loading">@csrf
            <label for="hostname" class="font-display text-xs uppercase tracking-wider text-coral">target_domain</label>
            <div class="mt-2 flex flex-col gap-3 md:flex-row"><input id="hostname" name="hostname" value="{{ $hostname }}" type="text" required maxlength="253" placeholder="example.com" class="min-w-0 flex-1 rounded-none border border-coral/40 bg-paper px-4 py-3 font-display text-sm text-ink outline-none placeholder:text-ink/35 focus:border-coral dark:bg-ink dark:text-paper dark:placeholder:text-paper/35"><button type="submit" :disabled="loading" class="flex items-center justify-center gap-2 rounded-none bg-coral px-5 py-3 font-display text-sm font-bold uppercase tracking-wider text-paper disabled:cursor-wait disabled:opacity-70"><span x-show="!loading">Analyze domain →</span><span x-show="loading" x-cloak class="flex items-center gap-2"><span class="h-4 w-4 animate-spin rounded-full border-2 border-paper/30 border-t-paper"></span>Scanning...</span></button></div>
            @error('hostname')<p class="mt-3 text-sm text-red-400">{{ $message }}</p>@enderror
        </form>
        <p class="mt-5 font-display text-xs leading-5 text-ink/50 dark:text-paper/50">Only analyze systems you own or have permission to assess. Private, localhost, metadata, and internal network targets are blocked.</p>
    </div>

</section>
@endsection