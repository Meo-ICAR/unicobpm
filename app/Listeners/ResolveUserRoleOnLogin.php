<?php

namespace App\Listeners;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Fornitore;
use Illuminate\Auth\Events\Login;

class ResolveUserRoleOnLogin
{
    /**
     * Se l'utente non ha già un ruolo assegnato manualmente (es. admin/super_admin)
     * e non è collegato a nessun profilo Employee/Fornitore/Client, gli assegna il
     * ruolo base 'user'. Se invece è collegato a uno di questi profili, il suo
     * accesso alle procedure è già governato dall'array di ruoli (employee_roles)
     * tramite resolveUserEmployeeTypeIds() e EmployeeTypePermission/
     * EmployeeTypeResourcePreset (vedi resolvePianoAccess() in app/helpers.php),
     * quindi non forziamo qui alcun valore su User::role.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! empty($user->role)) {
            return;
        }

        $profile = $user->profile ?? null;

        if (! $profile instanceof Employee && ! $profile instanceof Fornitore && ! $profile instanceof Client) {
            $user->forceFill(['role' => UserRole::USER->value])->save();
        }
    }
}
