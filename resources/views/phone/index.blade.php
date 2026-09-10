@extends('layouts.app')
@section('content')
<section class="mx-auto max-w-5xl px-5 py-16 lg:px-8 lg:py-24" x-data="{ loading: false }">
    <p class="font-display text-xs font-bold uppercase tracking-[.24em] text-coral">[ public business contact intelligence ]</p>
    <h1 class="mt-4 max-w-4xl font-display text-5xl font-bold leading-[.95] lg:text-7xl">Find the public<br><span class="text-coral">contact signal.</span></h1>
    <p class="mt-7 max-w-2xl text-lg leading-8 text-ink/65 dark:text-paper/65">Discover and normalize business phone numbers published on official websites. No people finding. No private data lookup. Just public contact evidence.</p>
    <div class="mt-12 border border-coral/50 bg-sand/30 p-6 shadow-[0_0_32px_rgba(57,255,136,.08)] dark:bg-sand/20">
        <div class="mb-6 flex justify-between border-b border-coral/20 pb-4 font-display text-xs text-ink/50 dark:text-paper/50"><span>~/inspection-tools/phone</span><span>MODE: PUBLIC-ONLY</span></div>
        <form action="{{ route('phone.scan') }}" method="POST" @submit="loading = true" :aria-busy="loading">@csrf
            <div class="grid gap-4 md:grid-cols-2"><label class="font-display text-xs uppercase tracking-wider text-coral">business_name<input name="business_name" value="{{ old('business_name') }}" maxlength="160" placeholder="Acme Corporation" class="mt-2 block w-full rounded-none border border-coral/40 bg-paper px-4 py-3 font-display text-sm text-ink outline-none placeholder:text-ink/35 focus:border-coral dark:bg-ink dark:text-paper"></label><label class="font-display text-xs uppercase tracking-wider text-coral">company_name<input name="company_name" value="{{ old('company_name') }}" maxlength="160" placeholder="Optional context" class="mt-2 block w-full rounded-none border border-coral/40 bg-paper px-4 py-3 font-display text-sm text-ink outline-none placeholder:text-ink/35 focus:border-coral dark:bg-ink dark:text-paper"></label></div>
            <label class="mt-4 block font-display text-xs uppercase tracking-wider text-coral">domain_or_url<input name="url" value="{{ old('url') }}" type="url" maxlength="2048" placeholder="https://example.com" class="mt-2 block w-full rounded-none border border-coral/40 bg-paper px-4 py-3 font-display text-sm text-ink outline-none placeholder:text-ink/35 focus:border-coral dark:bg-ink dark:text-paper"><span class="mt-2 block normal-case tracking-normal text-ink/45 dark:text-paper/45">A public official website is required. Use a domain with https://.</span></label>
            @error('url')<p class="mt-3 text-sm text-red-400">{{ $message }}</p>@enderror
            <button type="submit" :disabled="loading" class="mt-6 flex w-full items-center justify-center gap-2 rounded-none bg-coral px-5 py-3.5 font-display text-sm font-bold uppercase tracking-wider text-paper disabled:cursor-wait disabled:opacity-70"><span x-show="!loading">Start phone intelligence scan →</span><span x-show="loading" x-cloak class="flex items-center gap-2"><span class="h-4 w-4 animate-spin rounded-full border-2 border-paper/30 border-t-paper"></span>Scanning public pages...</span></button>
        </form>
        <div class="mt-7 border-t border-coral/15 pt-5 font-display text-xs leading-6 text-ink/50 dark:text-paper/50">&gt; INITIALIZING PHONE INTELLIGENCE...<br>&gt; ANALYZING PUBLIC PAGES...<br>&gt; NORMALIZING PHONE NUMBERS...<br>&gt; NO PRIVATE LOOKUPS OR AUTHENTICATED ACCESS</div>
    </div>
</section>
@endsection
