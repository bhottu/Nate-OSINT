<?php

use App\Providers\AppServiceProvider;
use App\Providers\DomainIntelligenceServiceProvider;
use Illuminate\View\ViewServiceProvider;

return [
    AppServiceProvider::class,
    DomainIntelligenceServiceProvider::class,
    ViewServiceProvider::class,
];
