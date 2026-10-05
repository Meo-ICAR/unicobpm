<?php

namespace App\Listeners;

use App\Enums\PlanType;
use App\Models\CompanyModule;
use App\Models\Employee;
use Filament\Notifications\Notification;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Str;

class ResolveCompanyPlanTypeOnLogin
{
    /**
     * Al login, se PLAN_TYPE non è valorizzata nel .env, risolve il piano
     * dell'azienda dell'utente per il modulo corrente (company_modules) e lo
     * salva in sessione, cosi' effectivePlanType() lo trova nelle richieste
     * successive. Se il piano risultante è TRIAL, notifica la scadenza.
     */
    public function handle(Login $event): void
    {
        if (! empty(config('plan.type'))) {
            return;
        }

        $employee = $event->user->profile ?? null;

        if (! $employee instanceof Employee || ! $employee->company_id) {
            return;
        }

        $moduleCode = Str::upper(str_replace(' ', '', (string) config('app.name')));

        $companyModule = CompanyModule::query()
            ->where('company_id', $employee->company_id)
            ->whereHas('module', fn ($query) => $query->where('code', $moduleCode))
            ->first();

        if (! $companyModule || ! $companyModule->plan_type) {
            return;
        }

        session(['plan_type' => $companyModule->plan_type->value]);

        if ($companyModule->plan_type === PlanType::Trial && $companyModule->trial_ends_at) {
            Notification::make()
                ->title('Procedura in test')
                ->body('Scade il '.$companyModule->trial_ends_at->format('d/m/Y'))
                ->warning()
                ->send();
        }
    }
}
