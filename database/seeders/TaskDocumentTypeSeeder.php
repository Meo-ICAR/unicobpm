<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaskDocumentTypeSeeder extends Seeder
{
    /**
     * Associazioni Task <-> DocumentType (con is_required per riga), esportate
     * dal database 'unicooam' di produzione. Richiede che TaskSeeder e
     * DocumentTypeSeeder siano già stati eseguiti (task_id/document_type_id
     * sono referenziati da FK con cascadeOnDelete).
     */
    public function run(): void
    {
        $rows = [
            ['id' => 1, 'task_id' => 1, 'document_type_id' => 1, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 2, 'task_id' => 1, 'document_type_id' => 2, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 3, 'task_id' => 1, 'document_type_id' => 5, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 4, 'task_id' => 1, 'document_type_id' => 6, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 6, 'task_id' => 1, 'document_type_id' => 11, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 11, 'task_id' => 1, 'document_type_id' => 27, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 13, 'task_id' => 2, 'document_type_id' => 1, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 14, 'task_id' => 2, 'document_type_id' => 2, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 15, 'task_id' => 2, 'document_type_id' => 3, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 16, 'task_id' => 2, 'document_type_id' => 8, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 17, 'task_id' => 2, 'document_type_id' => 12, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 18, 'task_id' => 5, 'document_type_id' => 1, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 19, 'task_id' => 5, 'document_type_id' => 2, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 20, 'task_id' => 5, 'document_type_id' => 3, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 21, 'task_id' => 5, 'document_type_id' => 8, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 22, 'task_id' => 5, 'document_type_id' => 12, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 23, 'task_id' => 4, 'document_type_id' => 1, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 24, 'task_id' => 4, 'document_type_id' => 2, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 25, 'task_id' => 4, 'document_type_id' => 3, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 26, 'task_id' => 4, 'document_type_id' => 8, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 27, 'task_id' => 4, 'document_type_id' => 12, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 28, 'task_id' => 3, 'document_type_id' => 1, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 29, 'task_id' => 3, 'document_type_id' => 2, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 30, 'task_id' => 3, 'document_type_id' => 3, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 31, 'task_id' => 3, 'document_type_id' => 7, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 32, 'task_id' => 3, 'document_type_id' => 10, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 33, 'task_id' => 3, 'document_type_id' => 12, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 34, 'task_id' => 6, 'document_type_id' => 4, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 35, 'task_id' => 6, 'document_type_id' => 13, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 36, 'task_id' => 6, 'document_type_id' => 14, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 37, 'task_id' => 6, 'document_type_id' => 15, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 38, 'task_id' => 6, 'document_type_id' => 16, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 39, 'task_id' => 6, 'document_type_id' => 18, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 40, 'task_id' => 6, 'document_type_id' => 19, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 41, 'task_id' => 6, 'document_type_id' => 23, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 42, 'task_id' => 6, 'document_type_id' => 24, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 43, 'task_id' => 6, 'document_type_id' => 26, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 44, 'task_id' => 6, 'document_type_id' => 29, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 45, 'task_id' => 6, 'document_type_id' => 30, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 46, 'task_id' => 6, 'document_type_id' => 31, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 47, 'task_id' => 7, 'document_type_id' => 1, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 48, 'task_id' => 7, 'document_type_id' => 2, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 49, 'task_id' => 7, 'document_type_id' => 7, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 50, 'task_id' => 7, 'document_type_id' => 8, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 51, 'task_id' => 7, 'document_type_id' => 12, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 52, 'task_id' => 8, 'document_type_id' => 24, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 53, 'task_id' => 8, 'document_type_id' => 25, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 54, 'task_id' => 8, 'document_type_id' => 29, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 55, 'task_id' => 8, 'document_type_id' => 30, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 56, 'task_id' => 8, 'document_type_id' => 32, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 57, 'task_id' => 9, 'document_type_id' => 13, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 58, 'task_id' => 9, 'document_type_id' => 17, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 59, 'task_id' => 9, 'document_type_id' => 19, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 60, 'task_id' => 9, 'document_type_id' => 20, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 61, 'task_id' => 9, 'document_type_id' => 21, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 62, 'task_id' => 9, 'document_type_id' => 22, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 63, 'task_id' => 10, 'document_type_id' => 32, 'is_required' => 1, 'created_at' => '2026-06-30 09:36:06', 'updated_at' => '2026-06-30 09:36:06'],
            ['id' => 66, 'task_id' => 11, 'document_type_id' => 3, 'is_required' => 1, 'created_at' => '2026-06-30 12:33:06', 'updated_at' => '2026-06-30 12:33:06'],
            ['id' => 69, 'task_id' => 12, 'document_type_id' => 1, 'is_required' => 1, 'created_at' => '2026-06-30 12:34:14', 'updated_at' => '2026-06-30 12:34:14'],
            ['id' => 70, 'task_id' => 12, 'document_type_id' => 3, 'is_required' => 1, 'created_at' => '2026-06-30 12:34:14', 'updated_at' => '2026-06-30 12:34:14'],
            ['id' => 71, 'task_id' => 1, 'document_type_id' => 38, 'is_required' => 1, 'created_at' => '2026-07-08 08:53:30', 'updated_at' => '2026-07-08 08:53:30'],
            ['id' => 72, 'task_id' => 1, 'document_type_id' => 37, 'is_required' => 1, 'created_at' => '2026-07-08 08:53:30', 'updated_at' => '2026-07-08 08:53:30'],
            ['id' => 73, 'task_id' => 1, 'document_type_id' => 35, 'is_required' => 1, 'created_at' => '2026-07-08 08:53:30', 'updated_at' => '2026-07-08 08:53:30'],
            ['id' => 74, 'task_id' => 1, 'document_type_id' => 36, 'is_required' => 1, 'created_at' => '2026-07-08 08:53:30', 'updated_at' => '2026-07-08 08:53:30'],
            ['id' => 75, 'task_id' => 1, 'document_type_id' => 39, 'is_required' => 1, 'created_at' => '2026-07-08 08:53:30', 'updated_at' => '2026-07-08 08:53:30'],
        ];

        foreach ($rows as $row) {
            DB::connection('mysql_unicooam')->table('document_requirements')->updateOrInsert(
                ['id' => $row['id']],
                $row
            );
        }
    }
}
