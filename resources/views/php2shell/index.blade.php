@extends('layouts.app')
@section('content')
<section class="mx-auto max-w-5xl px-5 py-16 lg:px-8 lg:py-24" x-data="{ loading: false, mode: 'single' }">
    <p class="font-display text-xs font-bold uppercase tracking-[.24em] text-coral">[ active php2shell scanner ]</p>
    <h1 class="mt-4 max-w-4xl font-display text-5xl font-bold leading-[.95] lg:text-7xl">PHP2Shell<br><span class="text-coral">Scanner</span></h1>
    <p class="mt-7 max-w-2xl text-lg leading-8 text-ink/65 dark:text-[#b8ffcf]/65">Scanner for detecting CVE-2026-109618 and CVE-2026-109619 (php2shell).</p>
    <div class="mt-12 border border-coral/50 bg-sand/30 p-6 shadow-[0_0_32px_rgba(57,255,136,.08)] dark:bg-[#0d2117]/20">
        <div class="mb-6 flex justify-between border-b border-coral/20 pb-4 font-display text-xs text-ink/50 dark:text-[#b8ffcf]/50"><span>~/inspection-tools/php2shell</span><span>MODE: ACTIVE</span></div>
        <form action="{{ route('php2shell.scan') }}" method="POST" @submit="loading = true" :aria-busy="loading">@csrf
            <input type="hidden" name="mode" x-model="mode">
            <label class="font-display text-xs uppercase tracking-wider text-coral mb-2 block">target mode</label>
            <div class="mt-2 flex border border-coral/40 rounded-none mb-6">
                <label class="flex-1 cursor-pointer py-3 px-4 text-center font-display text-xs uppercase hover:text-coral transition-colors" :class="mode === 'single' ? 'bg-coral/10 text-coral border-b-2 border-coral' : 'bg-transparent'" @click="mode = 'single'">Single Website</label>
                <label class="flex-1 cursor-pointer py-3 px-4 text-center font-display text-xs uppercase hover:text-coral transition-colors" :class="mode === 'multiple' ? 'bg-coral/10 text-coral border-b-2 border-coral' : 'bg-transparent'" @click="mode = 'multiple'">Multiple Websites</label>
            </div>
            <div x-show="mode === 'single'">
                <label for="url_single" class="font-display text-xs uppercase tracking-wider text-coral">url_target</label>
                <div class="mt-2 flex flex-col gap-3 md:flex-row"><input id="url_single" name="urls" value="{{ old('urls') }}" type="url" required maxlength="2048" placeholder="https://example.com" class="min-w-0 flex-1 rounded-none border border-coral/40 bg-paper px-4 py-3 font-display text-sm text-ink outline-none placeholder:text-ink/35 focus:border-coral dark:bg-[#07110c] dark:text-[#b8ffcf] dark:placeholder:text-[#b8ffcf]/35"><button type="submit" :disabled="loading" class="flex items-center justify-center gap-2 rounded-none bg-coral px-5 py-3 font-display text-sm font-bold uppercase tracking-wider text-[#07110c] hover:bg-coral/90 disabled:cursor-wait disabled:opacity-70"><span x-show="!loading">Start Scan →</span><span x-show="loading" x-cloak class="flex items-center gap-2"><span class="h-4 w-4 animate-spin rounded-full border-2 border-paper/30 border-t-paper"></span>Scanning...</span></button></div>
            </div>
            <div x-show="mode === 'multiple'">
                <label for="url_multi" class="font-display text-xs uppercase tracking-wider text-coral">url_target (One URL per line)</label>
                <div class="mt-2"><textarea id="url_multi" name="urls" required rows="6" placeholder="https://example.com&#10;https://example.org" class="w-full rounded-none border border-coral/40 bg-paper px-4 py-3 font-display text-sm text-ink outline-none placeholder:text-ink/35 focus:border-coral dark:bg-[#07110c] dark:text-[#b8ffcf] dark:placeholder:text-[#b8ffcf]/35">{{ old('urls') }}</textarea></div>
                <p class="mt-2 font-display text-xs text-ink/50 dark:text-[#b8ffcf]/50">Maximum {{ $maxTargets }} targets per batch. Empty lines and duplicates will be removed, and each target will be validated individually.</p>
                <div class="mt-3"><button type="submit" :disabled="loading" class="rounded-none bg-coral px-5 py-3 font-display text-sm font-bold uppercase tracking-wider text-[#07110c] hover:bg-coral/90 disabled:cursor-wait disabled:opacity-70 w-full md:w-auto"><span x-show="!loading">Start Scan →</span><span x-show="loading" x-cloak class="flex items-center gap-2"><span class="h-4 w-4 animate-spin rounded-full border-2 border-paper/30 border-t-paper"></span>Scanning...</span></button></div>
            </div>
            @error('urls')<p class="mt-3 text-sm text-red-400">{{ $message }}</p>@enderror
        </form>
        <p class="mt-5 font-display text-xs leading-5 text-ink/50 dark:text-[#b8ffcf]/50"><span class="text-red-400 font-bold">WARNING:</span> Only test websites you own or have explicit permission to assess. The scanner sends control requests and multipart probes to the specified targets; it does not execute exploits against target servers.</p>
    </div>
</section>
@endsection