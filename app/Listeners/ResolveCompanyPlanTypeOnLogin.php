<?php

namespace App\Listeners;

use Filament\Notifications\Notification;
use Illuminate\Auth\Events\Login;
use Unico\Core\Access\AccessChecker;

class ResolveCompanyPlanTypeOnLogin
{
    public function __construct(private AccessChecker $access) {}

    /**
     * Il piano della company per questa app si ricava a ogni richiesta (Unico\Core\Access\AccessChecker), quindi al login
     * resta solo da avvisare se la company è in prova: la data di scadenza è `company_modules.trial_ends_at`.
     */
    public function handle(Login $event): void
    {
        $endsAt = $this->access->trialEndsAt($event->user);

        if ($endsAt !== null) {
            Notification::make()
                ->title('Procedura in test')
                ->body('Scade il '.$endsAt->format('d/m/Y'))
                ->warning()
                ->send();
        }
    }
}
