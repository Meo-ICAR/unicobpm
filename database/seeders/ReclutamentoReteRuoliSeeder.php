<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Employee;
use Illuminate\Database\Seeder;
use Unico\Core\Models\Fornitore;

/**
 * Assegna alle persone i ruoli (employee_types) del processo di reclutamento della rete (PRC-RECLUTAMENTO-RETE), come nel modello operativo
 * del 22/11/2026. I ruoli stanno in `employee_roles` per nome; chi ha già il ruolo non cambia. Dipendenti e produttori si cercano per
 * email, gli esterni per email o nome. Idempotente.
 *
 * Non assegnati perché non risultano in anagrafica (con email) o non hanno un record attivo: Gioia Quarto (legale), Antonio Perreca /
 * Bankeo-Move (outsourcer recruiting), Eustacchio Allegretti (organizzazione e risorse umane), owner IT/Operations (TBD-01 del modello).
 */
class ReclutamentoReteRuoliSeeder extends Seeder
{
    /** [modello, campo di ricerca, valore, ruoli (nomi)]. */
    private const ASSEGNAZIONI = [
        // Direzione: Presidente e AD
        [Employee::class, 'email', 'sergio.bracale@races.it', ['amministratore delegato']],
        // Organizzazione e Risorse Umane (process owner: Eustacchio Allegretti) – NON assegnato: i suoi due record dipendente attivi sono
        // cancellati (id 15, 24/10) o inattivi (id 3, ruolo SOS) e come esterno ha solo lo studio (client 53, compliance). Da decidere.
        // Area Commerciale
        [Employee::class, 'email', 'riccardo.voce@races.it', ['commerciale']],
        // Marketing, coordinamento outsourcing e Back Office (esclusi Quinto/TFS)
        [Employee::class, 'email', 'davide.bastione@races.it', ['marketing e referente outsourcing', 'coordinatore back office']],
        // Segreteria di Rete (Roberto Perna): ha già il ruolo il record segreteria@races.it (id 14); quello personale (id 16) è cancellato.
        // Responsabile BO Quinto/TFS
        [Employee::class, 'email', 'raffaele.gravina@races.it', ['back office quinto e tfs']],
        // Compliance e Risk Management (consulente esterno)
        [Client::class, 'email', 'avvocatofrancescomaiello@gmail.com', ['compliance']],
        // Affari societari e contabilità (consulente esterno; in anagrafica senza email)
        [Client::class, 'name', 'Muscetti Gennaro', ['affari societari e contabilità']],
        // Area Manager e supporto Divisione Mutui (agenti della rete, quindi produttori)
        [Fornitore::class, 'email', 'paolo.sajeva@races.it', ['area manager e supporto mutui']],
        [Fornitore::class, 'email', 'giorgio.campanella@agenti.races.it', ['area manager e supporto mutui']],
        [Fornitore::class, 'email', 'massimo.vannuccini@agenti.races.it', ['area manager e supporto mutui']],
        [Fornitore::class, 'email', 'giovanni.leo@agenti.races.it', ['area manager e supporto mutui']],
    ];

    public function run(): void
    {
        $this->allegretti();
        $this->nuoviDipendenti();

        foreach (self::ASSEGNAZIONI as [$model, $campo, $valore, $ruoli]) {
            $persona = $model::query()->where($campo, $valore)->first();

            if ($persona === null) {
                $this->command?->warn("Non trovato: {$valore}");

                continue;
            }

            $attuali = collect((array) $persona->employee_roles)->filter(fn ($r) => filled($r))->values();
            $nuovi = $attuali->merge($ruoli)->unique()->values();

            if ($nuovi->count() !== $attuali->count()) {
                $persona->forceFill(['employee_roles' => $nuovi->all()])->save();
                $this->command?->info("{$valore}: ".implode(', ', $ruoli));
            }
        }
    }

    /** Eustacchio Allegretti (organizzazione e risorse umane): si riattiva il record dipendente più recente (id 15, cancellato il 24/10). */
    private function allegretti(): void
    {
        $record = Employee::withTrashed()->where('email', 'eustacchio.allegretti@races.it')->orderByDesc('id')->first();

        if ($record === null) {
            $this->command?->warn('Allegretti: record non trovato.');

            return;
        }

        if ($record->trashed()) {
            $record->restore();
        }

        $roles = collect((array) $record->employee_roles)->filter(fn ($r) => filled($r))->merge(['organizzazione e risorse umane'])->unique()->values()->all();
        $record->forceFill(['is_active' => true, 'employee_roles' => $roles])->save();
        $this->command?->info("Allegretti riattivato (id {$record->id}): organizzazione e risorse umane");
    }

    /**
     * Record in organigramma per chi non ne aveva: Gioia Quarto (legale) e Antonio Perreca (referente Bankeo/Move, outsourcer recruiting).
     * La loro email non è nota: va aggiunta (senza non ricevono i solleciti).
     */
    private function nuoviDipendenti(): void
    {
        foreach ([
            ['QUARTO GIOIA', 'Gioia', 'Quarto', 'Legale', 'Legale', ['legale']],
            ['PERRECA ANTONIO', 'Antonio', 'Perreca', 'Referente Bankeo / Move S.r.l. (outsourcer recruiting)', 'Outsourcing recruiting', ['outsourcer recruiting']],
        ] as [$nome, $first, $last, $titolo, $reparto, $ruoli]) {
            $esiste = Employee::withTrashed()->where('name', $nome)->first();

            if ($esiste !== null) {
                continue;
            }

            Employee::query()->create([
                'company_id' => 1, 'name' => $nome, 'first_name' => $first, 'last_name' => $last, 'role_title' => $titolo,
                'department' => $reparto, 'is_active' => true, 'employee_roles' => $ruoli,
            ]);
            $this->command?->info("Creato in organigramma: {$nome} (".implode(', ', $ruoli).'), email da inserire');
        }
    }
}
