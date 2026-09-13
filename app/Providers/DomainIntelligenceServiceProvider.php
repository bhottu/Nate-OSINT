<?php

namespace App\Providers;

use App\Services\DomainIntelligence\Contracts\CtProviderInterface;
use App\Services\DomainIntelligence\Contracts\DnsProviderInterface;
use App\Services\DomainIntelligence\Contracts\IpIntelligenceProviderInterface;
use App\Services\DomainIntelligence\Contracts\RdapProviderInterface;
use App\Services\DomainIntelligence\Contracts\SearchProviderInterface;
use App\Services\DomainIntelligence\Providers\CrtShProvider;
use App\Services\DomainIntelligence\Providers\IpWhoisProvider;
use App\Services\DomainIntelligence\Providers\NativeDnsProvider;
use App\Services\DomainIntelligence\Providers\NullSearchProvider;
use App\Services\DomainIntelligence\Providers\RdapProvider;
use Illuminate\Support\ServiceProvider;

class DomainIntelligenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DnsProviderInterface::class, function () {
            return match (config('domain_intelligence.providers.dns.driver', 'native')) {
                'native' => new NativeDnsProvider(),
                default => new NativeDnsProvider(),
            };
        });

        $this->app->bind(RdapProviderInterface::class, function () {
            return match (config('domain_intelligence.providers.rdap.driver', 'rdap.org')) {
                'rdap.org' => new RdapProvider(),
                default => new RdapProvider(),
            };
        });

        $this->app->bind(CtProviderInterface::class, function () {
            return match (config('domain_intelligence.providers.ct.driver', 'crt.sh')) {
                'crt.sh' => new CrtShProvider(),
                default => new CrtShProvider(),
            };
        });

        $this->app->bind(IpIntelligenceProviderInterface::class, function () {
            return match (config('domain_intelligence.providers.ip.driver', 'ipwho.is')) {
                'ipwho.is' => new IpWhoisProvider(),
                default => new IpWhoisProvider(),
            };
        });

        $this->app->bind(SearchProviderInterface::class, function () {
            return match (config('domain_intelligence.providers.search.driver', 'null')) {
                default => new NullSearchProvider(),
            };
        });
    }

    public function boot(): void
    {
        //
    }
}