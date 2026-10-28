<?php

namespace App\Providers;

use App\Filament\Actions\ExcelExportAction;
use App\Listeners\BccOutgoingMail;
use App\Listeners\ResolveCompanyPlanTypeOnLogin;
use App\Listeners\ResolveUserRoleOnLogin;
use App\Listeners\SaveSentMessageCopy;
use App\Models\Audit;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\ComplaintRegistry;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Fornitore;
use App\Models\Website;
use App\Services\Correlation;
use App\Services\Replies\CompositeReplyInterpreter;
use App\Services\Replies\NeuronReplyInterpreter;
use App\Services\Replies\ReplyInterpreter;
use App\Services\Replies\RuleBasedReplyInterpreter;
use Filament\Tables\Table;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Google\GoogleExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\MicrosoftExtendSocialite;
use Unico\Core\UnicoCoreServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Un solo identificativo di correlazione per richiesta/esecuzione, riusato da tutte le chiamate verso le altre app.
        $this->app->singleton(Correlation::class);

        // Risposte email: prima le regole (risposte brevi e inequivocabili), poi l'AI per il resto.
        $this->app->bind(ReplyInterpreter::class, fn () => new CompositeReplyInterpreter([
            new RuleBasedReplyInterpreter,
            new NeuronReplyInterpreter,
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ogni tabella (risorse, relation manager, widget) ha il pulsante «Excel»: colonne visibili e filtri applicati.
        Table::configureUsing(fn (Table $table): Table => $table->pushHeaderActions([ExcelExportAction::make()]));

        // Il pacchetto unico-core sta nel database condiviso (connessione `core`), creato da un'altra app: qui non si
        // eseguono le sue migration. Se invece usa la connessione dell'app (es. nei test), si creano con `migrate`.
        if (config('unico-core.connection') === config('database.default')) {
            $this->loadMigrationsFrom(UnicoCoreServiceProvider::migrationsPath());
        }

        Relation::morphMap([
            'audit' => Audit::class,
            'branch' => Branch::class,
            'client' => Client::class,
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

        Event::listen(MessageSending::class, BccOutgoingMail::class);
        Event::listen(MessageSent::class, SaveSentMessageCopy::class);
        Event::listen(Login::class, ResolveUserRoleOnLogin::class);
        Event::listen(Login::class, ResolveCompanyPlanTypeOnLogin::class);
    }
}
