<?php

namespace App\Providers;

use App\Listeners\ResolveCompanyPlanTypeOnLogin;
use App\Listeners\ResolveUserRoleOnLogin;
use App\Models\Audit;
use App\Models\Branch;
use App\Models\Company;
use App\Models\ComplaintRegistry;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Fornitore;
use App\Models\PROFORMA\Clienti;
use App\Models\Website;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Google\GoogleExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\MicrosoftExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'audit' => Audit::class,
            'branch' => Branch::class,
            'cliente' => Clienti::class,
            'company' => Company::class,
            'complaint' => ComplaintRegistry::class,
            'document' => Document::class,
            'employee' => Employee::class,
            'fornitore' => Fornitore::class,
            'website' => Website::class,
        ]);

        Event::listen(
            SocialiteWasCalled::class,
            [MicrosoftExtendSocialite::class, 'handle']
        );
        Event::listen(
            SocialiteWasCalled::class,
            [GoogleExtendSocialite::class, 'handle']
        );

        Event::listen(Login::class, ResolveUserRoleOnLogin::class);
        Event::listen(Login::class, ResolveCompanyPlanTypeOnLogin::class);
    }
}
