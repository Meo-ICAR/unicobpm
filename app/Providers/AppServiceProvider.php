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
        // Un solo identificativo di correlazione per richiesta/esecuzione, riusato da tutte le chiamate verso le altre app.
        $this->app->singleton(\App\Services\Correlation::class);

        // Risposte email: prima le regole (risposte brevi e inequivocabili), poi l'AI per il resto.
        $this->app->bind(\App\Services\Replies\ReplyInterpreter::class, fn () => new \App\Services\Replies\CompositeReplyInterpreter([
            new \App\Services\Replies\RuleBasedReplyInterpreter,
            new \App\Services\Replies\NeuronReplyInterpreter,
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Il pacchetto unico-core sta nel database condiviso (connessione `core`), creato da un'altra app: qui non si
        // eseguono le sue migration. Se invece usa la connessione dell'app (es. nei test), si creano con `migrate`.
        if (config('unico-core.connection') === config('database.default')) {
            $this->loadMigrationsFrom(\Unico\Core\UnicoCoreServiceProvider::migrationsPath());
        }

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
