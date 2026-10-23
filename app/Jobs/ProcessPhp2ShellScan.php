<?php

namespace App\Jobs;

use App\Models\Php2ShellScan;
use App\Models\Php2ShellScanTarget;
use App\Services\Php2Shell\Php2ShellScannerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPhp2ShellScan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries = 1;

    public function __construct(public Php2ShellScan $scan)
    {
    }

    public function handle(Php2ShellScannerService $scanner): void
    {
        $scan = $this->scan->refresh();
        $scan->update([
            'status' => Php2ShellScan::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        $pending = $scan->targets()->orderBy('id')->whereNull('verdict')->get();
        $urls = $pending->pluck('target_url')->all();

        $results = $scanner->scanTargets($urls, [
            'sapi' => config('php2shell.sapi', 'auto'),
        ]);

        foreach ($results as $index => $result) {
            $target = $pending[$index] ?? null;
            if (! $target) {
                continue;
            }

            $target->update([
                'normalized_url' => $result['normalized_url'] ?? null,
                'hostname' => $result['hostname'] ?? null,
                'verdict' => $result['verdict'],
                'reason' => $result['reason'],
                'evidence' => $result['evidence'],
                'sapi' => $result['sapi'],
                'x_powered_by' => $result['x_powered_by'],
                'duration_ms' => $result['duration_ms'] ?? null,
                'checked_at' => now(),
            ]);
        }

        $this->finalizeScan($scan);
    }

    private function finalizeScan(Php2ShellScan $scan): void
    {
        $scan = $scan->refresh();
        $total = (int) $scan->total_targets;
        $completed = (int) $scan->targets()->whereNotNull('verdict')->count();
        $errored = (int) $scan->targets()
            ->whereIn('verdict', [
                Php2ShellScanTarget::VERDICT_ERROR,
                Php2ShellScanTarget::VERDICT_TIMEOUT,
                Php2ShellScanTarget::VERDICT_UNREACHABLE,
            ])->count();

        $scan->update(['completed_targets' => $completed]);

        if ($completed < $total) {
            $status = Php2ShellScan::STATUS_PARTIAL;
        } elseif ($errored === $total && $total > 0) {
            $status = Php2ShellScan::STATUS_FAILED;
        } else {
            $status = Php2ShellScan::STATUS_COMPLETED;
        }

        $scan->update([
            'status' => $status,
            'completed_at' => now(),
            'duration_ms' => $scan->started_at ? (int) round(now()->diffInSeconds($scan->started_at) * 1000) : null,
        ]);
    }
}
