@extends('layouts.app')
@section('content')
<section class="mx-auto max-w-5xl px-5 py-16 lg:px-8 lg:py-24" x-data="{ loading: false }">
    <p class="font-display text-xs font-bold uppercase tracking-[.24em] text-coral">[ identity graph / public evidence ]</p>
    <h1 class="mt-4 max-w-4xl font-display text-5xl font-bold leading-[.95] lg:text-7xl">Map the public<br><span class="text-coral">social footprint.</span></h1>
    <p class="mt-7 max-w-2xl text-lg leading-8 text-ink/65 dark:text-paper/65">Correlate publicly linked social accounts through observable evidence. Similarity is a lead, not proof of identity or account ownership.</p>
    <div class="mt-12 border border-coral/50 bg-sand/30 p-6 shadow-[0_0_32px_rgba(57,255,136,.08)] dark:bg-sand/20"><div class="mb-6 flex justify-between border-b border-coral/20 pb-4 font-display text-xs text-ink/50 dark:text-paper/50"><span>~/inspection-tools/social-correlation</span><span>MODE: PUBLIC-ONLY</span></div>
        <form action="{{ route('social-correlation.scan') }}" method="POST" @submit="loading = true" :aria-busy="loading">@csrf
            <label for="input" class="font-display text-xs uppercase tracking-wider text-coral">seed_profile_or_username<input id="input" name="input" value="{{ old('input') }}" required maxlength="2048" placeholder="https://instagram.com/example or @example" class="mt-2 block w-full rounded-none border border-coral/40 bg-paper px-4 py-3 font-display text-sm text-ink outline-none placeholder:text-ink/35 focus:border-coral dark:bg-ink dark:text-paper"></label>
            <label for="platform" class="mt-4 block font-display text-xs uppercase tracking-wider text-coral">platform<select id="platform" name="platform" class="mt-2 block w-full rounded-none border border-coral/40 bg-paper px-4 py-3 font-display text-sm text-ink outline-none focus:border-coral dark:bg-ink dark:text-paper"><option>Instagram</option><option>TikTok</option><option>X</option><option>Facebook</option><option>YouTube</option><option>LinkedIn</option><option>Reddit</option><option>GitHub</option><option>Other</option></select></label>
            @error('input')<p class="mt-3 text-sm text-red-400">{{ $message }}</p>@enderror
            <button type="submit" :disabled="loading" class="mt-6 flex w-full items-center justify-center gap-2 rounded-none bg-coral px-5 py-3.5 font-display text-sm font-bold uppercase tracking-wider text-paper disabled:cursor-wait disabled:opacity-70"><span x-show="!loading">Start correlation →</span><span x-show="loading" x-cloak class="flex items-center gap-2"><span class="h-4 w-4 animate-spin rounded-full border-2 border-paper/30 border-t-paper"></span>Building identity graph...</span></button>
        </form>
        <div class="mt-7 border-t border-coral/15 pt-5 font-display text-xs leading-6 text-ink/50 dark:text-paper/50">&gt; INITIALIZING SOCIAL CORRELATION ENGINE...<br>&gt; ANALYZING SEED PROFILE...<br>&gt; GENERATING USERNAME VARIANTS...<br>&gt; SEARCHING PUBLIC SOURCES...<br>&gt; NO PRIVATE PROFILE OR ACCOUNT ENUMERATION</div>
    </div>
</section>
@endsection
