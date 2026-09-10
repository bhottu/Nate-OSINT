<?php

namespace App\Http\Controllers;

use App\Services\ReversePhoneOSINT\ReversePhoneScanner;
use Illuminate\Http\Request;
use Throwable;

class ReversePhoneOSINTController extends Controller
{
    public function index()
    {
        return view('reverse-phone.index');
    }

    public function scan(Request $request, ReversePhoneScanner $scanner)
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:40']]);
        try {
            return view('reverse-phone.result', ['report' => $scanner->scan($data['phone'])]);
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['phone' => $exception->getMessage()]);
        }
    }
}
