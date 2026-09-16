<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaskSeeder extends Seeder
{
    /**
     * Anagrafica reale dei Task (plichi documentali per OnBoarding/OAM/IVASS/...),
     * esportata dal database 'unicooam' di produzione. Gli 'id' sono preservati
     * esplicitamente perché referenziati da task_document_types.task_id (vedi
     * TaskDocumentTypeSeeder).
     */
    public function run(): void
    {
        $rows = [
            ['id' => 1, 'parent_id' => null, 'name' => 'OnBoarding', 'description' => 'Attività e documenti richiesti per il caricamento di una nuovo produttore.', 'app_identifier' => 'UNICOFin', 'taskable' => 'fornitore', 'trigger_field' => 'oam_at', 'trigger_state' => 'empty', 'trigger_value' => null, 'exclude_field' => null, 'exclude_state' => null, 'exclude_value' => null, 'is_active' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 2, 'parent_id' => null, 'name' => 'OAM-Agenti', 'description' => 'Attività e controlli per il rinnovo periodico OAM', 'app_identifier' => 'UnicoOAM', 'taskable' => 'fornitore', 'trigger_field' => 'oam_at', 'trigger_state' => 'filled', 'trigger_value' => null, 'exclude_field' => 'ivass_section', 'exclude_state' => 'equals', 'exclude_value' => 'E', 'is_active' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 3, 'parent_id' => null, 'name' => 'IVASS-Agenti', 'description' => 'Attività e controlli per il rinnovo periodico IVASS', 'app_identifier' => 'UnicoOAM', 'taskable' => 'fornitore', 'trigger_field' => 'ivass_section', 'trigger_state' => 'equals', 'trigger_value' => 'E', 'exclude_field' => null, 'exclude_state' => null, 'exclude_value' => null, 'is_active' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 4, 'parent_id' => null, 'name' => 'OAM-dipendenti', 'description' => 'Attività e controlli per il rinnovo periodico OAM', 'app_identifier' => 'UnicoOAM', 'taskable' => 'employee', 'trigger_field' => 'oam_at', 'trigger_state' => 'filled', 'trigger_value' => null, 'exclude_field' => null, 'exclude_state' => null, 'exclude_value' => null, 'is_active' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 5, 'parent_id' => null, 'name' => 'OAM-cda', 'description' => 'Attività per il rinnovo periodico OAM dei CdA', 'app_identifier' => 'UnicoOAM', 'taskable' => 'employee', 'trigger_field' => 'employee_type', 'trigger_state' => 'equals', 'trigger_value' => 'cda', 'exclude_field' => 'oam_at', 'exclude_state' => 'filled', 'exclude_value' => null, 'is_active' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 6, 'parent_id' => null, 'name' => 'OAM-Semestrale', 'description' => 'Documenti aziendali semestrale OAM', 'app_identifier' => 'UnicoOAM', 'taskable' => 'company', 'trigger_field' => null, 'trigger_state' => null, 'trigger_value' => null, 'exclude_field' => null, 'exclude_state' => null, 'exclude_value' => null, 'is_active' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 7, 'parent_id' => null, 'name' => 'Renewal', 'description' => 'Attività e controlli per il rinnovo periodico delle convenzioni o contratti.', 'app_identifier' => null, 'taskable' => 'company', 'trigger_field' => null, 'trigger_state' => null, 'trigger_value' => null, 'exclude_field' => null, 'exclude_state' => null, 'exclude_value' => null, 'is_active' => 0, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 8, 'parent_id' => null, 'name' => 'Audit', 'description' => 'Attività di controllo conformità e verifica della documentazione interna.', 'app_identifier' => null, 'taskable' => 'audit', 'trigger_field' => null, 'trigger_state' => null, 'trigger_value' => null, 'exclude_field' => null, 'exclude_state' => null, 'exclude_value' => null, 'is_active' => 0, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 9, 'parent_id' => null, 'name' => 'Ispezione', 'description' => 'Attività di ispezione in sede', 'app_identifier' => null, 'taskable' => 'audit', 'trigger_field' => null, 'trigger_state' => null, 'trigger_value' => null, 'exclude_field' => null, 'exclude_state' => null, 'exclude_value' => null, 'is_active' => 0, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 10, 'parent_id' => null, 'name' => 'OffBoarding', 'description' => 'Attività e documenti richiesti per la chiusura di una anagrafica.', 'app_identifier' => 'UnicoFin', 'taskable' => 'fornitore', 'trigger_field' => 'dismissed_at', 'trigger_state' => 'filled', 'trigger_value' => null, 'exclude_field' => null, 'exclude_state' => null, 'exclude_value' => null, 'is_active' => 0, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 11, 'parent_id' => null, 'name' => 'OAM-Onorabilita-produttori', 'description' => 'Onorabilita agenti', 'app_identifier' => 'UnicoOAM', 'taskable' => 'fornitore', 'trigger_field' => null, 'trigger_state' => null, 'trigger_value' => null, 'exclude_field' => null, 'exclude_state' => null, 'exclude_value' => 'E', 'is_active' => 1, 'created_at' => '2026-06-30 12:33:06', 'updated_at' => '2026-06-30 17:47:48'],
            ['id' => 12, 'parent_id' => null, 'name' => 'OAM-Onorabilita Dipendenti', 'description' => 'Onorabilita agenti', 'app_identifier' => 'UnicoOAM', 'taskable' => 'employee', 'trigger_field' => null, 'trigger_state' => null, 'trigger_value' => null, 'exclude_field' => null, 'exclude_state' => null, 'exclude_value' => 'E', 'is_active' => 1, 'created_at' => '2026-06-30 12:34:14', 'updated_at' => '2026-06-30 12:34:55'],
        ];

        foreach ($rows as $row) {
            DB::connection('mysql_unicooam')->table('tasks')->updateOrInsert(
                ['id' => $row['id']],
                $row
            );
        }
    }
}
