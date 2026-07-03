<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BusinessFunctionSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::parse('2026-03-18 10:19:00');

        $functions = [
            [
                'id' => 1,
                'code' => 'GOV-CDA',
                'macro_area' => 'Governance',
                'name' => 'Consiglio di Amministrazione / Direzione',
                'type' => 'Strategica',
                'description' => 'Definisce strategie, approva procedure organizzative, politiche di rischio e assicura l’adeguatezza dell’assetto organizzativo.',
                'outsourcable_status' => 'no',
                'managed_by_code' => null,
                'mission' => null,
                'responsibility' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'code' => 'BUS-DIRCOM',
                'macro_area' => 'Business / Commerciale',
                'name' => 'Direzione Commerciale',
                'type' => 'Operativa',
                'description' => 'Sviluppo accordi con Banche/Finanziarie, monitoraggio volumi e coordinamento Area Manager.',
                'outsourcable_status' => 'no',
                'managed_by_code' => 'GOV-CDA',
                'mission' => "Garantire, in coerenza con le strategie aziendali, il raggiungimento degli obiettivi di produzione...\nAssicurare la gestione...",
                'responsibility' => "• Supervisiona il raggiungimento degli obiettivi...\n• Gestisce e anima le risorse...",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 4,
                'code' => 'BUS-RETE-EXT',
                'macro_area' => 'Business / Commerciale',
                'name' => 'Gestione Rete e Collaboratori',
                'type' => 'Operativa',
                'description' => 'Gestione della rete di agenti e intermediari per lo sviluppo del business.',
                'outsourcable_status' => 'yes',
                'managed_by_code' => 'BUS-DIRCOM',
                'mission' => "Fornire supporto e consulenza alla rete di agenti.",
                'responsibility' => "• Coordina gli agenti\n• Fornisce supporto...",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 5,
                'code' => 'BUS-BO',
                'macro_area' => 'Business / Commerciale',
                'name' => 'Back Office / Istruttoria Pratiche',
                'type' => 'Operativa',
                'description' => 'Gestione dei processi amministrativi e contabili, supporto operativo.',
                'outsourcable_status' => 'yes',
                'managed_by_code' => 'BUS-DIRCOM',
                'mission' => "Garantire l'efficienza dei processi amministrativi e contabili.",
                'responsibility' => "• Gestisce documentazione\n• Supporto operativo...",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 9,
                'code' => 'CTRL-COMPL',
                'macro_area' => 'Controlli (II Livello)',
                'name' => 'Compliance (Conformità)',
                'type' => 'Controllo',
                'description' => 'Monitoraggio della conformità alle normative e alle politiche aziendali.',
                'outsourcable_status' => 'no',
                'managed_by_code' => 'GOV-CDA',
                'mission' => 'Garantire la conformità dell\'azienda alle normative vigenti.',
                'responsibility' => "• Monitora la conformità\n• Fornisce consulenza...",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 10,
                'code' => 'CTRL-AML',
                'macro_area' => 'Controlli (II Livello)',
                'name' => 'Antiriciclaggio (AML)',
                'type' => 'Controllo',
                'description' => 'Profilatura rischio, tenuta AUI, analisi operazioni sospette e segnalazioni SOS.',
                'outsourcable_status' => 'yes',
                'managed_by_code' => 'GOV-CDA',
                'mission' => 'Garantire gli adempimenti previsti in materia di antiriciclaggio, secondo quanto previsto dalla normativa vigente, assicurando adeguati livelli di servizio.',
                'responsibility' => "• Garantisce ogni forma di supporto legale all’azienda fornendo pareri tecnici.\n• Difende l’operato...",
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('business_functions')->insert($functions);
    }
}
