@extends('layouts.app')
@section('content')
<section class="mx-auto max-w-7xl px-5 py-12 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-5">
        <div>
            <p class="font-display text-xs uppercase tracking-[.24em] text-coral">php2shell scanner :: scan result</p>
            <h1 class="mt-3 break-all font-display text-3xl font-bold">{{ $scan->mode === 'single' ? 'Single Website' : 'Multiple Websites' }}</h1>
            <p class="mt-2 font-display text-xs text-ink/50 dark:text-[#b8ffcf]/50">STATUS: {{ $scan->status }} · total: {{ $scan->total_targets }} · selesai: {{ $scan->completed_targets }} · versi scanner: {{ $scan->scanner_version }}</p>
        </div>
        <div class="flex flex-wrap gap-2"><a href="{{ route('php2shell.index') }}" class="action">New scan</a></div>
    </div>

    <div class="mt-10 grid gap-6 lg:grid-cols-4">
        <div class="col-span-4 border border-coral/40 bg-sand/20 p-5 dark:bg-[#0d2117]/20">
            <p class="font-display text-xs uppercase tracking-wider text-coral">progress</p>
            <div class="mt-3 flex items-center gap-3">
                <span class="font-display text-xs" id="percent-text">{{ $scan->percent }}%</span>
                <div class="flex-1 h-2 bg-coral/10 rounded-none overflow-hidden"><div class="progress-bar-fill h-full bg-coral" style="width: {{ $scan->percent }}%"></div></div>
            </div>
            <p class="mt-2 font-display text-xs text-ink/50" id="progress-text">{{ $scan->completed_targets }}/{{ $scan->total_targets }} target</p>
        </div>
    </div>

    <div class="mt-10 overflow-x-auto">
        <table class="w-full text-left border-collapse border border-coral/20">
            <thead><tr class="border-b border-coral/40 bg-sand/10 dark:bg-[#0d2117]/20">
                <th class="p-3 font-display text-xs uppercase text-coral">Target</th>
                <th class="p-3 font-display text-xs uppercase text-coral">Hostname</th>
                <th class="p-3 font-display text-xs uppercase text-coral">Verdict</th>
                <th class="p-3 font-display text-xs uppercase text-coral">Alasan</th>
                <th class="p-3 font-display text-xs uppercase text-coral">Durasi</th>
            </tr></thead>
            <tbody>
            @forelse($scan->targets as $target)
            <tr class="border-b border-coral/10">
                <td class="p-3 font-display text-sm break-all">{{ $target->target_url }}</td>
                <td class="p-3 font-display text-sm">{{ $target->hostname ?? '-' }}</td>
                <td class="p-3 font-display text-sm {{ $target->severityClass() }}">{{ $target->verdict }}</td>
                <td class="p-3 font-display text-sm text-ink/60 dark:text-[#b8ffcf]/60">{{ $target->reason ?? '-' }}</td>
                <td class="p-3 font-display text-sm">{{ $target->duration_ms ? $target->duration_ms . ' ms' : '-' }}</td>
            </tr>
            @empty
            <tr><td class="p-3 text-coral" colspan="5">Belum ada hasil target.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @forelse($scan->targets as $target)
    <div class="mt-6 border border-coral/40 bg-sand/20 p-5 dark:bg-[#0d2117]/20">
        <p class="font-display text-xs uppercase text-coral">detail: {{ $target->target_url }}</p>
        <p class="mt-2 font-display text-xs"><strong>Verdict:</strong> {{ $target->verdict }} · <strong>SAPI:</strong> {{ $target->sapi ?? '-' }} · <strong>X-Powered-By:</strong> {{ $target->x_powered_by ?? 'tidak diiklanan' }}</p>
        @if($target->evidence)<p class="mt-2 font-display text-xs text-ink/60 break-all"><strong>Evidence:</strong> {{ $target->evidence }}</p>@endif
        @if($target->reason)<p class="mt-2 font-display text-xs text-ink/60"><strong>Alasan:</strong> {{ $target->reason }}</p>@endif
    </div>
    @empty
    @endforelse

    <script>
    (function(){
        var interval = setInterval(function(){
            fetch('{{ route('php2shell.progress', $scan) }}')
                .then(function(r){ return r.json(); })
                .then(function(data){
                    var bar = document.querySelector('.progress-bar-fill');
                    var pct = document.getElementById('percent-text');
                    var txt = document.getElementById('progress-text');
                    if(bar){ bar.style.width = data.percent + '%'; }
                    if(pct){ pct.textContent = data.percent + '%'; }
                    if(txt){ txt.textContent = data.completed_targets + '/' + data.total_targets + ' target'; }
                    if(data.status !== 'QUEUED' && data.status !== 'RUNNING'){
                        clearInterval(interval);
                    }
                }).catch(function(){});
        }, 2000);
        setTimeout(function(){ clearInterval(interval); }, 600000);
    })();
    </script>
@endsection