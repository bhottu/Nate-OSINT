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
<body class="min-h-screen bg-paper text-ink antialiased dark:bg-ink dark:text-paper">
    <nav class="border-b border-ink/10 dark:border-paper/10"><div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-5 lg:px-8">
        <a href="{{ route('home') }}" class="flex items-center gap-3 font-display text-lg font-bold"><span class="grid h-9 w-9 rounded-none border border-coral text-coral">&gt;_</span><span><span class="block">NATE OSINT<span class="text-coral">_</span></span><span class="brand-credit block font-normal uppercase tracking-[.18em] text-ink/55 dark:text-paper/55">by Nate Nasution</span></span></a>
        <div class="flex items-center gap-5 text-sm font-semibold"><a href="{{ route('privacy') }}" class="uppercase tracking-wider hover:text-coral">/privacy</a><button type="button" @click="dark = !dark; localStorage.setItem('theme', dark ? 'dark' : 'light')" class="rounded-none border border-coral/40 px-3 py-1.5 uppercase tracking-wider text-coral dark:border-coral/40" aria-label="Toggle dark mode"><span x-text="dark ? 'Light' : 'Dark'"></span></button></div>
    </div></nav>
    <main>@yield('content')</main>
    <footer class="mx-auto max-w-7xl px-5 py-10 text-sm text-ink/55 dark:text-paper/55 lg:px-8">Nate OSINT · Public-source intelligence and cybersecurity inspection toolkit.</footer>
</body></html>
