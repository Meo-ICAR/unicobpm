<?php

namespace App\Jobs;

use App\Models\Fornitore;
use App\Models\ProcessInstance;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Azione «Task di sistema» dell'onboarding agente: crea l'account di accesso al portale agenti per il produttore della pratica
 * (soggetto `fornitore`) e lo collega alla scheda (`fornitoris.user_id`). Se esiste già un utente con la stessa email lo collega;
 * la password è casuale e l'agente la imposta con «Password dimenticata» del portale agenti (UnicoFinance). Se il produttore ha già
 * un account non fa nulla.
 * Un errore (soggetto non produttore, email mancante o usata da un altro produttore) sospende la pratica.
 */
class ProvisionAgentAccountJob
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $config  Config dell'azione (non usata).
     */
    public function __construct(public int $instanceId, public array $config = []) {}

    public function handle(): void
    {
        $instance = ProcessInstance::query()->findOrFail($this->instanceId);
        $fornitore = $instance->subject;

        if (! $fornitore instanceof Fornitore) {
            throw new RuntimeException('La pratica non riguarda un produttore: impossibile creare l\'account agente.');
        }

        if ($fornitore->user_id !== null) {
            return;
        }

        $email = Str::lower(trim((string) $fornitore->email));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException("Il produttore {$fornitore->name} non ha un'email valida: è il suo login.");
        }

        if (Fornitore::query()->whereKeyNot($fornitore->getKey())->whereHas('user', fn ($query) => $query->where('email', $email))->exists()) {
            throw new RuntimeException("L'email {$email} è già il login di un altro produttore.");
        }

        $user = User::query()->where('email', $email)->first() ?? User::query()->create([
            'name' => (string) $fornitore->name,
            'email' => $email,
            'password' => Str::random(40),
            'role' => 'fornitore',
            'profile_type' => 'fornitore',
            'profile_id' => $fornitore->getKey(),
            'company_id' => $fornitore->company_id,
        ]);

        $fornitore->forceFill(['user_id' => $user->getKey()])->save();
    }
}
