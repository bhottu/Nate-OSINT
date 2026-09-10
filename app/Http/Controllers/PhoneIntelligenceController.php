<?php

namespace App\Http\Controllers;

use App\Models\PhoneScan;
use App\Services\PhoneIntelligence\PhoneScanner;
use Illuminate\Http\Request;
use Throwable;

class PhoneIntelligenceController extends Controller
{
    public function index()
    {
        return view('phone.index');
    }

    public function scan(Request $request, PhoneScanner $scanner)
    {
        $data = $request->validate(['business_name' => ['nullable', 'string', 'max:160'], 'company_name' => ['nullable', 'string', 'max:160'], 'domain' => ['nullable', 'string', 'max:255'], 'url' => ['nullable', 'url:http,https', 'max:2048']]);
        if (blank($data['domain'] ?? null) && blank($data['url'] ?? null)) {
            return back()->withInput()->withErrors(['url' => 'Provide a business domain or website URL.']);
        }
        $target = $data['url'] ?? $data['domain'];
        if (! preg_match('/^https?:\/\//i', $target)) {
            $target = 'https://'.$target;
        }
        $scan = PhoneScan::create(['business_name' => $data['business_name'] ?? null, 'company_name' => $data['company_name'] ?? null, 'target_url' => $target, 'normalized_domain' => (string) parse_url($target, PHP_URL_HOST), 'status' => 'SCANNING', 'started_at' => now()]);
        try {
            $result = $scanner->scan($data['business_name'] ?? null, $data['company_name'] ?? null, $data['domain'] ?? null, $data['url'] ?? null);
            $scan->update(['status' => 'COMPLETED', 'result' => $result, 'completed_at' => now()]);

            return redirect()->route('phone.show', $scan);
        } catch (Throwable $exception) {
            $scan->update(['status' => 'FAILED', 'result' => ['error' => $exception->getMessage()], 'completed_at' => now()]);

            return back()->withInput()->withErrors(['url' => $exception->getMessage()]);
        }
    }

    public function show(PhoneScan $scan)
    {
        abort_unless($scan->status === 'COMPLETED' && $scan->result, 404);

        return view('phone.result', ['scan' => $scan, 'report' => $scan->result]);
    }
}
