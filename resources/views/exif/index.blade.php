@extends('layouts.app')
@section('content')
<section class="mx-auto max-w-7xl px-5 pb-20 pt-16 lg:px-8 lg:pt-24"><div class="grid items-end gap-12 lg:grid-cols-[1.1fr_.9fr]">
    <div><p class="mb-5 font-display text-sm font-bold uppercase tracking-[.2em] text-coral">[ secure_exif_console ]</p><h1 class="max-w-3xl font-display text-5xl font-bold leading-[.98] tracking-tight lg:text-7xl">Decode the<br><span class="text-coral">hidden layer.</span></h1><p class="mt-7 max-w-xl text-lg leading-8 text-ink/65 dark:text-[#b8ffcf]/65">Inspect camera fingerprints, timestamps, and coordinates buried inside an image. One file. Zero guesswork.</p></div>
    <div class="rounded-none border border-coral/40 bg-[#07110c] p-7 text-[#b8ffcf] shadow-[0_0_32px_rgba(57,255,136,.08)]"><p class="font-display text-2xl font-bold">$ exif_extract --scan</p><p class="mt-2 text-sm text-[#b8ffcf]/60">accepted: JPG / PNG / TIFF · max: 20 MB</p>
        <form action="{{ route('extract') }}" method="POST" enctype="multipart/form-data" x-data="uploadForm()" @submit="loading = true" :aria-busy="loading" class="mt-6">@csrf
            <label @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="drop($event)" :class="dragging ? 'border-coral bg-coral/10' : 'border-[#b8ffcf]/20'" class="block cursor-pointer rounded-none border-2 border-dashed p-8 text-center transition">
                <input type="file" name="image" accept=".jpg,.jpeg,.png,.tif,.tiff,image/jpeg,image/png,image/tiff" class="sr-only" @change="fileName = $event.target.files[0]?.name || ''" required>
                <span class="mx-auto grid h-14 w-14 place-items-center rounded-none border border-coral text-2xl text-coral">+</span><span class="mt-4 block font-semibold" x-text="fileName || 'Drop image payload here'"></span><span class="mt-1 block text-sm text-[#b8ffcf]/55">or browse local storage</span>
            </label>
            @error('image')<p class="mt-3 text-sm text-red-300">{{ $message }}</p>@enderror
            <button type="submit" class="mt-5 flex w-full items-center justify-center gap-2 rounded-none bg-coral px-5 py-3.5 font-bold text-[#07110c] hover:bg-coral/90 disabled:cursor-wait disabled:opacity-70" :disabled="!fileName || loading">
                <span x-show="!loading">Start analyze <span>→</span></span>
                <span x-show="loading" x-cloak class="flex items-center gap-2"><span class="h-4 w-4 animate-spin rounded-full border-2 border-paper/30 border-t-paper"></span>Analyzing image...</span>
            </button>
        </form>
    </div>
</div></section>
<section class="border-y border-ink/10 bg-sand/40 dark:border-[#b8ffcf]/10 dark:bg-[#0d2117]/40"><div class="mx-auto grid max-w-7xl gap-10 px-5 py-14 lg:grid-cols-3 lg:px-8"><div><p class="font-display text-xl font-bold">Metadata, made legible.</p><p class="mt-2 text-sm leading-6 text-ink/60 dark:text-[#b8ffcf]/60">Camera settings, timestamps, device details, and more in one clean report.</p></div><div><p class="font-display text-xl font-bold">Location aware.</p><p class="mt-2 text-sm leading-6 text-ink/60 dark:text-[#b8ffcf]/60">GPS coordinates are visualized only when they exist, with a clear privacy warning.</p></div><div><p class="font-display text-xl font-bold">Nothing lingers.</p><p class="mt-2 text-sm leading-6 text-ink/60 dark:text-[#b8ffcf]/60">Files are read temporarily and never written to the database.</p></div></div></section>
<script>function uploadForm(){return {dragging:false,fileName:'',loading:false,drop(e){const input=this.$root.querySelector('input[type=file]'); input.files=e.dataTransfer.files; this.fileName=input.files[0]?.name||''; this.dragging=false}}}</script>
@endsection
