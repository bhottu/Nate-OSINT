<?php

namespace App\Http\Controllers;

use App\Models\Scan;
use App\Services\SecurityInspector\SecurityScanner;
use Illuminate\Http\Request;
use Throwable;

class SecurityInspectorController extends Controller
{
    public function index()
    {
        return view('security.index');
    }

    public function scan(Request $request, SecurityScanner $scanner)
    {
        $validated = $request->validate(['url' => ['required', 'url:http,https', 'max:2048']]);
        $url = $validated['url'];
        $scan = Scan::create(['target_url' => $url, 'normalized_domain' => (string) parse_url($url, PHP_URL_HOST), 'status' => 'SCANNING', 'started_at' => now()]);

        try {
            $result = $scanner->scan($url);
            $scan->update(['status' => 'COMPLETED', 'score' => $result['score']['value'], 'grade' => $result['score']['grade'], 'result' => $result, 'completed_at' => now()]);

            return redirect()->route('security.show', $scan);
        } catch (Throwable $exception) {
            $scan->update(['status' => 'FAILED', 'result' => ['error' => $exception->getMessage()], 'completed_at' => now()]);

            return back()->withInput()->withErrors(['url' => $exception->getMessage()]);
        }
    }

    public function show(Scan $scan)
    {
        abort_unless($scan->status === 'COMPLETED' && $scan->result, 404);

        return view('security.result', ['scan' => $scan, 'report' => $scan->result]);
    }
}
