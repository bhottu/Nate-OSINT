<!doctype html>
<html lang="en" x-data="{ dark: localStorage.getItem('theme') !== 'light' }" :class="{ 'dark': dark }">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Nate OSINT' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-paper text-ink antialiased dark:bg-[#07110c] dark:text-[#b8ffcf]">
    <nav class="border-b border-ink/10 dark:border-[#b8ffcf]/10"><div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-5 lg:px-8">
        <a href="{{ route('home') }}" class="flex items-center gap-3 font-display text-lg font-bold"><span class="grid h-9 w-9 rounded-none border border-coral text-coral">&gt;_</span><span><span class="block">NATE OSINT<span class="text-coral">_</span></span><span class="brand-credit block font-normal uppercase tracking-[.18em] text-ink/55 dark:text-[#b8ffcf]/55">by Nate Nasution</span></span></a>
        <div class="flex items-center gap-5 text-sm font-semibold"><a href="{{ route('privacy') }}" class="uppercase tracking-wider hover:text-coral">/privacy</a><button type="button" @click="dark = !dark; localStorage.setItem('theme', dark ? 'dark' : 'light')" class="rounded-none border border-coral/40 px-3 py-1.5 uppercase tracking-wider text-coral dark:border-coral/40" aria-label="Toggle dark mode"><span x-text="dark ? 'Light' : 'Dark'"></span></button></div>
    </div></nav>
    <main>@yield('content')</main>
    <footer class="mx-auto max-w-7xl px-5 py-10 text-sm text-ink/55 dark:text-[#b8ffcf]/55 lg:px-8">
        <div class="flex items-center justify-between">
            <span>Nate OSINT · Public-source intelligence and cybersecurity inspection toolkit.</span>
            <a href="https://github.com/bhottu/Nate-OSINT" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 hover:text-ink dark:hover:text-[#b8ffcf] transition-colors" aria-label="GitHub Repository">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
            </a>
        </div>
    </footer>
</body></html>
