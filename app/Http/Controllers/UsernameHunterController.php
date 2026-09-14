<?php

namespace App\Http\Controllers;

use App\Models\UsernameScan;
use App\Services\UsernameHunter\UsernameHunterService;
use App\Services\UsernameHunter\UsernameValidator;
use Illuminate\Http\Request;
use Throwable;

class UsernameHunterController extends Controller
{
    public function __construct(
        private UsernameHunterService $service,
        private UsernameValidator $validator
    ) {}

    public function index()
    {
        return view('username-hunter.index');
    }

    public function scan(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:64', 'min:1'],
        ]);

        $originalUsername = trim($data['username']);
        $normalizedUsername = $this->validator->normalize($originalUsername);

        if (! $this->validator->validate($normalizedUsername)) {
            return back()->withInput()->withErrors(['username' => 'Enter a valid public username. Only letters, numbers, dots, hyphens, and underscores are allowed.']);
        }

        try {
            $report = $this->service->scan($normalizedUsername);

            $scan = UsernameScan::create([
                'username' => $originalUsername,
                'normalized_username' => $normalizedUsername,
                'total_checked' => $report['summary']['total_checked'],
                'total_found' => $report['summary']['found'],
                'status' => 'COMPLETED',
                'scan_time_seconds' => $report['summary']['scan_time_seconds'],
            ]);

            $resultData = [];
            foreach ($report['results'] as $result) {
                $resultData[] = $scan->results()->create([
                    'platform' => $result['platform'],
                    'username' => $result['username'],
                    'profile_url' => $result['profile_url'],
                    'status' => $result['status'],
                    'http_status' => $result['http_status'],
                    'response_time_ms' => $result['response_time_ms'],
                    'error_message' => $result['error'],
                ]);
            }

            return view('username-hunter.result', [
                'scan' => $scan,
                'report' => $report,
            ]);
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['username' => 'Scan failed: '.$exception->getMessage()]);
        }
    }
}
