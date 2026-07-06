<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
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
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
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
        // 1. Recuperiamo la funzione aziendale "Responsible" dal task
        $responsibleAssignment = $task->raciAssignments()
            ->where('role_type', 'responsible')
            ->first();

        if (! $responsibleAssignment) {
            return collect();
        }

        // STEP 1: Recuperiamo TUTTI gli utenti della funzione aziendale (il reparto)
        $repartoQuery = self::whereHas('business_functions', function ($q) use ($responsibleAssignment) {
            $q->where('business_functions.id', $responsibleAssignment->business_function_id);
        });

        $utentiReparto = $repartoQuery->get();

        // SE NEL REPARTO C'È UNA SOLA PERSONA IN TOTALE -> La priorità va a lei (Ignora specializzazione)
        if ($utentiReparto->count() === 1) {
            return $utentiReparto;
        }

        // STEP 2: Se nel reparto c'è più di una persona, controlliamo la specializzazione
        if (! empty($instance->type) && $utentiReparto->count() > 1) {

            // Rieseguiamo la query clonata applicando il filtro della specializzazione della pratica
            $utentiSpecializzati = $repartoQuery->clone()
                ->where(function ($q) use ($instance) {
                    $q->where('specialization', $instance->type)
                        ->orWhereNull('specialization'); // Tiene conto anche di eventuali generalisti se vuoi
                })
                ->get();

            return $utentiSpecializzati;
        }

        // Se non c'è nessuna specializzazione passata, restituisce l'intero reparto
        return $utentiReparto;
    }
}
