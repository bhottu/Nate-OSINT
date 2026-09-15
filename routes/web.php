<?php

use App\Http\Controllers\ExifController;
use App\Http\Controllers\PhoneIntelligenceController;
use App\Http\Controllers\ReversePhoneOSINTController;
use App\Http\Controllers\SecurityInspectorController;
use App\Http\Controllers\SocialAccountCorrelationController;
use App\Http\Controllers\DomainIntelligenceController;
use App\Http\Controllers\UsernameHunterController;
use App\Http\Controllers\EmailIntelligenceController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * ================================================
 * TEST: Web Routes and Controllers - 2026-09-03
 * ================================================
 */

Route::get('/debug-session', function (\Illuminate\Http\Request $request) {
    return response()->json([
        'session_id' => session()->getId(),
        'csrf_token' => csrf_token(),
        'cookie_names' => array_keys($request->cookies->all()),
        'session_cookie_config' => config('session.cookie'),
        'session_driver' => config('session.driver'),
        'session_secure' => config('session.secure'),
        'session_domain' => config('session.domain'),
        'db_default' => config('database.default'),
    ]);
});

Route::get('/', fn () => view('welcome'))->name('home');
Route::get('/exif', [ExifController::class, 'index'])->name('exif.index');
Route::post('/extract', [ExifController::class, 'extract'])->name('extract');
Route::get('/security-inspector', [SecurityInspectorController::class, 'index'])->name('security.index');
Route::post('/security-inspector/scan', [SecurityInspectorController::class, 'scan'])->middleware('throttle:10,1')->name('security.scan');
Route::get('/security-inspector/{scan}', [SecurityInspectorController::class, 'show'])->name('security.show');
Route::get('/phone-intelligence', [PhoneIntelligenceController::class, 'index'])->name('phone.index');
Route::post('/phone-intelligence/scan', [PhoneIntelligenceController::class, 'scan'])->middleware('throttle:10,1')->name('phone.scan');
Route::get('/phone-intelligence/{scan}', [PhoneIntelligenceController::class, 'show'])->name('phone.show');
Route::get('/reverse-phone-osint', [ReversePhoneOSINTController::class, 'index'])->name('reverse-phone.index');
Route::post('/reverse-phone-osint/scan', [ReversePhoneOSINTController::class, 'scan'])->middleware('throttle:5,1')->name('reverse-phone.scan');
Route::get('/social-account-correlation', [SocialAccountCorrelationController::class, 'index'])->name('social-correlation.index');
Route::post('/social-account-correlation/scan', [SocialAccountCorrelationController::class, 'scan'])->middleware('throttle:5,1')->name('social-correlation.scan');

Route::get('/privacy', [ExifController::class, 'privacy'])->name('privacy');
Route::get('/tools/{tool}', fn (string $tool) => view('tools.placeholder', [
    'tool' => Str::headline($tool),
]))->name('tools.placeholder');

/**
 * ================================================
 * Domain Intelligence - 2026-09-13
 * Passive OSINT: DNS, subdomains, certificates, TLS, HTTP.
 * ================================================
 */
Route::get('/domain-intelligence', [DomainIntelligenceController::class, 'index'])->name('domain-intelligence.index');
Route::post('/domain-intelligence/scan', [DomainIntelligenceController::class, 'scan'])->middleware('throttle:6,1')->name('domain-intelligence.scan');
Route::get('/domain-intelligence/{scan}', [DomainIntelligenceController::class, 'show'])
    ->whereNumber('scan')
    ->name('domain-intelligence.show');
Route::get('/domain-intelligence/{scan}/export', [DomainIntelligenceController::class, 'export'])
    ->whereNumber('scan')
    ->name('domain-intelligence.export');

/**
 * ================================================
 * Username / Social Media Hunter - 2026-09-14
 * Passive public username lookup across platforms.
 * ================================================
 */
Route::get('/username-hunter', [UsernameHunterController::class, 'index'])->name('username-hunter.index');
Route::post('/username-hunter/scan', [UsernameHunterController::class, 'scan'])->middleware('throttle:6,1')->name('username-hunter.scan');

/**
 * ================================================
 * Email Intelligence - 2026-09-15
 * Passive OSINT: public account discovery, technical
 * email/domain analysis, optional breach exposure.
 * ================================================
 */
Route::get('/email-intelligence', [EmailIntelligenceController::class, 'index'])->name('email-intelligence.index');
Route::post('/email-intelligence/scan', [EmailIntelligenceController::class, 'scan'])->middleware('throttle:6,1')->name('email-intelligence.scan');
Route::get('/email-intelligence/{scan}', [EmailIntelligenceController::class, 'show'])
    ->where('scan', '[A-Za-z0-9]{12}')
    ->name('email-intelligence.show');
Route::get('/email-intelligence/{scan}/export', [EmailIntelligenceController::class, 'export'])
    ->where('scan', '[A-Za-z0-9]{12}')
    ->name('email-intelligence.export');
