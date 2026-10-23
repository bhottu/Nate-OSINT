<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPhp2ShellScan;
use App\Models\Php2ShellScan;
use App\Models\Php2ShellScanTarget;
use App\Services\Php2Shell\Php2ShellScannerService;
use App\Services\Php2Shell\Php2ShellTargetValidator;
use Illuminate\Http\Request;
use RuntimeException;

class Php2ShellScannerController extends Controller
{
    public function index()
    {
        return view('php2shell.index', [
            'maxTargets' => (int) config('php2shell.limits.max_targets_per_batch', 25),
        ]);
    }

    public function scan(Request $request)
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:single,multiple'],
            'urls' => ['required', 'string'],
        ]);

        $mode = $validated['mode'];
        $rawUrls = $mode === 'single'
            ? [$validated['urls']]
            : preg_split('/\r\n|\n|\r/', $validated['urls']);

        $urls = [];
        foreach ($rawUrls as $u) {
            $u = trim($u);
            if ($u === '' || in_array($u, $urls, true)) {
                continue;
            }
            $urls[] = $u;
        }

        $max = (int) config('php2shell.limits.max_targets_per_batch', 25);
        if (count($urls) === 0) {
            return back()->withInput()->withErrors(['urls' => 'Masukkan minimal satu target website.']);
        }
        if (count($urls) > $max) {
            return back()->withInput()->withErrors(['urls' => "Maksimal {$max} target per batch."]);
        }

        $scan = Php2ShellScan::create([
            'user_id' => auth()->check() ? auth()->id() : null,
            'mode' => $mode,
            'total_targets' => count($urls),
            'status' => Php2ShellScan::STATUS_QUEUED,
            'scanner_version' => Php2ShellScannerService::SCANNER_VERSION,
        ]);

        $validator = app(Php2ShellTargetValidator::class);
        foreach ($urls as $url) {
            try {
                $normalized = $validator->validate($url);
                Php2ShellScanTarget::create([
                    'php2shell_scan_id' => $scan->id,
                    'target_url' => $url,
                    'normalized_url' => $normalized,
                    'hostname' => parse_url($normalized, PHP_URL_HOST) ?? null,
                    'verdict' => null,
                ]);
            } catch (RuntimeException $e) {
                Php2ShellScanTarget::create([
                    'php2shell_scan_id' => $scan->id,
                    'target_url' => $url,
                    'verdict' => Php2ShellScanTarget::VERDICT_ERROR,
                    'reason' => $e->getMessage(),
                    'checked_at' => now(),
                ]);
            }
        }

        return redirect()->route('php2shell.show', $scan->id)->with('status', 'Scan initiated successfully.');
    }

    public function show($id)
    {
        $scan = Php2ShellScan::with('targets')->findOrFail($id);
        return view('php2shell.result', compact('scan'));
    }

    public function progress($id)
    {
        $scan = Php2ShellScan::findOrFail($id);
        return response()->json([
            'status' => $scan->status,
            'progress' => $scan->progress,
        ]);
    }
}