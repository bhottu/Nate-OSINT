@extends('layouts.app')
@section('content')
<section class="mx-auto max-w-5xl px-5 py-16 lg:px-8 lg:py-24" x-data="{ loading: false }">
    <p class="font-display text-xs font-bold uppercase tracking-[.24em] text-coral">[ email / intelligence ]</p>
    <h1 class="mt-4 max-w-4xl font-display text-5xl font-bold leading-[.95] lg:text-7xl">Follow the<br><span class="text-coral">email trail.</span></h1>
    <p class="mt-7 max-w-2xl text-lg leading-8 text-ink/65 dark:text-[#b8ffcf]/65">Search for public account indicators linked to an email address and analyze its domain. Results are possible matches based on public evidence — never proof of ownership.</p>
    <div class="mt-12 border border-coral/50 bg-sand/30 p-6 shadow-[0_0_32px_rgba(57,255,136,.08)] dark:bg-[#0d2117]/30">
        <div class="mb-6 flex justify-between border-b border-coral/20 pb-4 font-display text-xs text-ink/50 dark:text-[#b8ffcf]/50"><span>~/inspection-tools/email-intelligence</span><span>MODE: PASSIVE-ONLY</span></div>
        <form action="{{ route('email-intelligence.scan') }}" method="POST" @submit="loading = true" :aria-busy="loading">@csrf
            <label for="email" class="font-display text-xs uppercase tracking-wider text-coral">email address</label>
            <div class="mt-2 flex flex-col gap-3 md:flex-row">
                <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="254" placeholder="example@email.com" class="min-w-0 flex-1 rounded-none border border-coral/40 bg-paper px-4 py-3 font-display text-sm text-ink outline-none placeholder:text-ink/35 focus:border-coral dark:bg-[#07110c] dark:text-[#b8ffcf] dark:placeholder:text-[#b8ffcf]/35">
                <button type="submit" :disabled="loading" class="flex items-center justify-center gap-2 rounded-none bg-coral px-5 py-3 font-display text-sm font-bold uppercase tracking-wider text-[#07110c] hover:bg-coral/90 disabled:cursor-wait disabled:opacity-70"><span x-show="!loading">Analyze →</span><span x-show="loading" x-cloak class="flex items-center gap-2"><span class="h-4 w-4 animate-spin rounded-full border-2 border-paper/30 border-t-paper"></span>Scanning...</span></button>
            </div>
            @error('email')<p class="mt-3 text-sm text-red-400">{{ $message }}</p>@enderror
        </form>
        <div class="mt-7 border-t border-coral/15 pt-5 font-display text-xs leading-6 text-ink/50 dark:text-[#b8ffcf]/50" x-show="loading" x-cloak>
            &gt; Validating email...<br>&gt; Analyzing domain...<br>&gt; Checking public sources...<br>&gt; Checking available account indicators...<br>&gt; Preparing results...
        </div>
        <div class="mt-7 border-t border-coral/15 pt-5 font-display text-xs leading-6 text-ink/50 dark:text-[#b8ffcf]/50">&gt; PASSIVE PUBLIC LOOKUP ONLY — NO LOGIN TESTS, NO PASSWORD RESET PROBES<br>&gt; {{ $hibpConfigured ? 'BREACH EXPOSURE CHECK: CONFIGURED (HIBP API)' : 'BREACH EXPOSURE CHECK: NOT CONFIGURED' }}<br>&gt; POSSIBLE MATCH ≠ CONFIRMED OWNERSHIP<br>&gt; USE ONLY FOR LAWFUL RESEARCH ON PUBLIC INFORMATION</div>
    </div>
</section>
@endsection
