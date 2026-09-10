<?php

namespace App\Http\Controllers;

use App\Models\SocialCorrelationScan;
use App\Services\SocialAccountCorrelation\SocialAccountCorrelationService;
use Illuminate\Http\Request;
use Throwable;

class SocialAccountCorrelationController extends Controller
{
    public function index()
    {
        return view('social-correlation.index');
    }

    public function scan(Request $request, SocialAccountCorrelationService $service)
    {
        $data = $request->validate(['input' => ['required', 'string', 'max:2048'], 'platform' => ['required', 'string', 'in:Instagram,TikTok,X,Facebook,YouTube,LinkedIn,Reddit,GitHub,Other']]);
        try {
            $report = $service->analyze($data['input'], $data['platform']);
            $profiles = $report['profiles'];
            $scan = SocialCorrelationScan::create(['seed_platform' => $data['platform'], 'seed_username' => $report['seed']['username'], 'seed_url' => $report['seed']['url'], 'status' => 'COMPLETED', 'score' => min(100, collect($profiles)->max('score') ?? 0), 'confidence' => $profiles ? $profiles[0]['confidence'] : 'INSUFFICIENT EVIDENCE', 'results' => $report]);

            return view('social-correlation.result', compact('scan', 'report'));
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['input' => $exception->getMessage()]);
        }
    }
}
