<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Fornitore;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Abbina gli utenti di accesso alla loro anagrafica (`profile_type`/`profile_id`) in base al dominio dell'email: @races.it dipendente,
 *
 * @agenti.races.it produttore, tutti gli altri consulente aziendale (client con `is_company` attivo). Si cerca l'anagrafica con la stessa
 * email, prima del tipo previsto e poi degli altri; se manca la si crea. Un abbinamento già presente (anche fatto a mano) resta, gli account
 *
 * @example.com sono saltati. Idempotente.
 */
class LinkUsersToProfiles extends Command
{
    protected $signature = 'bpm:link-users-to-profiles {--dry-run : Mostra gli abbinamenti senza scriverli}';

    protected $description = 'Abbina gli utenti a dipendente, produttore o consulente in base al dominio dell\'email';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        foreach (User::query()->orderBy('id')->get() as $user) {
            if (self::isTechnical($user->email)) {
                $this->line("{$user->email} → saltato (utente tecnico)");

                continue;
            }

            // Un abbinamento già fatto resta (anche a mano); poi l'anagrafica del tipo previsto, poi una esistente di un altro tipo.
            $profile = $user->profile;
            $type = $profile === null ? self::profileTypeFor($user->email) : $user->profile_type;
            $profile ??= $this->findProfile($type, $user->email);

            if ($profile === null) {
                foreach (array_diff(array_keys(self::PROFILE_MODELS), [$type]) as $otherType) {
                    if (($profile = $this->findProfile($otherType, $user->email)) !== null) {
                        $type = $otherType;

                        break;
                    }
                }
            }

            $action = $profile === null ? 'nuova anagrafica' : "anagrafica #{$profile->getKey()}";

            $this->line("{$user->email} → {$type} ({$action})".($dryRun ? ' [simulazione]' : ''));

            if ($dryRun) {
                continue;
            }

            $profile ??= $this->createProfile($type, $user);

            if ($type === 'client' && ! $profile->is_company) {
                $profile->is_company = true;
            }

            $profile->user_id ??= $user->id;
            $profile->save();

            $user->forceFill(['profile_type' => $type, 'profile_id' => $profile->getKey()])->save();
        }

        return self::SUCCESS;
    }

    /** @var array<string, class-string<Model>> */
    private const PROFILE_MODELS = ['employee' => Employee::class, 'fornitore' => Fornitore::class, 'client' => Client::class];

    /** Account di prova, senza anagrafica. */
    public static function isTechnical(string $email): bool
    {
        return Str::endsWith(Str::lower($email), '@example.com');
    }

    /** @return 'employee'|'fornitore'|'client' */
    public static function profileTypeFor(string $email): string
    {
        $email = Str::lower($email);

        return match (true) {
            Str::endsWith($email, '@agenti.races.it') => 'fornitore',
            Str::endsWith($email, '@races.it') => 'employee',
            default => 'client',
        };
    }

    /** @return class-string<Model> */
    private function modelFor(string $type): string
    {
        return self::PROFILE_MODELS[$type];
    }

    private function findProfile(string $type, string $email): ?Model
    {
        return $this->modelFor($type)::query()->whereRaw('lower(email) = ?', [Str::lower($email)])->first();
    }

    private function createProfile(string $type, User $user): Model
    {
        return $this->modelFor($type)::query()->create(['name' => $user->name, 'email' => $user->email]);
    }
}
