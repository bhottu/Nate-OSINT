<?php

use App\Http\Controllers\ExifController;
use App\Http\Controllers\PhoneIntelligenceController;
use App\Http\Controllers\ReversePhoneOSINTController;
use App\Http\Controllers\SecurityInspectorController;
use App\Http\Controllers\SocialAccountCorrelationController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * ================================================
 * TEST: Web Routes and Controllers - 2026-09-03
 * ================================================
 */

Route::get('/debug-session', function () {
    return response()->json([
        'session_id' => session()->getId(),
        'csrf_token' => csrf_token(),
        'session_data' => session()->all(),
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
/**
 * ================================================
 * Note : for domain intelligence still not working
 * ================================================
 */

Route::get('/privacy', [ExifController::class, 'privacy'])->name('privacy');
Route::get('/tools/{tool}', fn (string $tool) => view('tools.placeholder', [
    'tool' => Str::headline($tool),
]))->name('tools.placeholder');

/**
 * ================================================
 * Domain Intelligence: Subdomain Discovery Routes - 2026-09-03
 * ================================================
 */
Route::get('/subdomains', function () {
    $service = new App\Services\SubdomainDiscoveryService();
    return collect($service->discoverSubdomains());
})->name('subdomains.list');

Route::get('/subdomains/analyze/{scan}', function ($scan) {
    $subdomains = $service = new App\Services\SubdomainDiscoveryService();
    $subdomains->analyzeSubdomains(DB::table('domain_dns_records')->where('hostname', $scan->hostname)->pluck('value')->toArray());
    
    return view('security.result', [
        'scan' => $scan,
        'report' => ['subdomain_count' => count($subdomains)],
    ]);
})->name('subdomains.analyze');
/**
 * ================================================
 * Domain Intelligence: Certificate Transparency Routes - 2026-09-03
 * ================================================
 */
Route::get('/certificates', function () {
    $service = new App\Services\SubdomainDiscoveryService();
    return collect($service->discoverSubdomains())->take(10);
})->name('certificates.list');

Route::get('/certificate-transparency/{scan}', function ($scan) {
    $certificates = new App\Services\SubdomainDiscoveryService();
    $certificates->analyzeSubdomains(DB::table('domain_dns_records')->where('hostname', $scan->hostname)->pluck('value')->toArray());
    
    return view('security.result', [
        'scan' => $scan,
        'report' => ['certificate_count' => count($certificates)],
    ]);
})->name('certificates.analyze');

/**
 * ================================================
 * Domain Intelligence: Search Engine OSINT Routes - 2026-09-03
 * ================================================
 */
Route::get('/search', function () {
    return view('security.result', [
        'report' => ['message' => 'Search engine integration coming soon'],
    ]);
})->name('search.index');

Route::get('/search/{domain}', function ($domain) {
    $service = new App\Services\SubdomainDiscoveryService();
    $subdomains = $service->discoverSubdomains();
    
    return view('security.result', [
        'domain' => $domain,
        'report' => ['search_count' => count($subdomains)],
    ]);
})->name('search.analyze');

/**
 * ================================================
 * Domain Intelligence: Security Headers Routes - 2026-09-03
 * ================================================
 */
Route::get('/security/headers', function () {
    return view('security.result', [
        'report' => ['message' => 'Security headers analysis coming soon'],
    ]);
})->name('headers.index');

Route::post('/security/headers/scan/{scan}', function ($scan) {
    $service = new App\Services\SubdomainDiscoveryService();
    $scanned = $service->discoverSubdomains();
    
    return view('security.result', [
        'scan' => $scan,
        'report' => ['header_count' => count($scanned)],
    ]);
})->name('headers.analyze');

/**
 * ================================================
 * Domain Intelligence: Redirect Analysis Routes - 2026-09-03
 * ================================================
 */
Route::get('/redirects', function () {
    return view('security.result', [
        'report' => ['message' => 'Redirect analysis coming soon'],
    ]);
})->name('redirects.index');

Route::post('/redirects/scan/{scan}', function ($scan) {
    $service = new App\Services\SubdomainDiscoveryService();
    $scanned = $service->discoverSubdomains();
    
    return view('security.result', [
        'scan' => $scan,
        'report' => ['redirect_count' => count($scanned)],
    ]);
})->name('redirects.analyze');

/**
 * ================================================
 * Domain Intelligence: Search Engine OSINT Routes - 2026-09-03
 * ================================================
 */
Route::get('/search', function () {
    return view('security.result', [
        'report' => ['message' => 'Search engine integration coming soon'],
    ]);
})->name('search.index');

Route::get('/search/{domain}', function ($domain) {
    $service = new App\Services\SubdomainDiscoveryService();
    $subdomains = $service->discoverSubdomains();
    
    return view('security.result', [
        'domain' => $domain,
        'report' => ['search_count' => count($subdomains)],
    ]);
})->name('search.analyze');
