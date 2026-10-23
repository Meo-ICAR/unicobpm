<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Unico\Core\Models\User as CoreUser;

#[Hidden(['password', 'remember_token'])]
class User extends CoreUser implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ]);
    }

    /**
     * Ruoli che accedono al pannello: fuori dall'ambiente locale Filament nega l'accesso a chi non lo dichiara.
     *
     * @var list<string>
     */
    public const STAFF_ROLES = ['admin', 'super_admin', 'employee', 'inspector', 'user'];

    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role, self::STAFF_ROLES, true);
    }

    public function profile(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Recupera gli utenti abilitati per un task e applica l'auto-assegnazione a cascata.
     */
    public static function getResponsibleUsersForInstance(ProcessTask $task, ProcessInstance $instance): Collection
    {
        // 1. Recuperiamo il ruolo "Responsible" dal task
        $responsibleAssignment = $task->raciAssignments()
            ->where('raci_role', 'R')
            ->first();

        if (! $responsibleAssignment) {
            return collect();
        }

        // STEP 1: Recuperiamo TUTTI gli utenti che ricoprono il ruolo (il reparto)
        $role = EmployeeType::find($responsibleAssignment->employee_type_id);
        $utentiReparto = $role?->loginUsers() ?? collect();

        // SE NEL REPARTO C'È UNA SOLA PERSONA IN TOTALE -> La priorità va a lei (Ignora specializzazione)
        if ($utentiReparto->count() === 1) {
            return $utentiReparto;
        }

        // STEP 2: Se nel reparto c'è più di una persona, controlliamo la specializzazione
        if (! empty($instance->type) && $utentiReparto->count() > 1) {
            $utentiSpecializzati = $utentiReparto->filter(
                fn (self $user) => is_null($user->specialization) || $user->specialization === $instance->type
            );

            return $utentiSpecializzati->values();
        }

        // Se non c'è nessuna specializzazione passata, restituisce l'intero reparto
        return $utentiReparto;
    }
}
