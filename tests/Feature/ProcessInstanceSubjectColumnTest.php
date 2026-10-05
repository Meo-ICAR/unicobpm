<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProcessInstanceSubjectColumnTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * `subject_id` used to be a bigint (Laravel's default nullableMorphs()),
     * but every real subject model (Fornitore, Clienti, Client) has a UUID
     * primary key — starting a process against a real subject always failed
     * in MySQL with "Data truncated for column 'subject_id'". Locks in that
     * the column is string-typed (nullableUuidMorphs) so it doesn't regress.
     */
    public function test_subject_id_is_a_string_column_not_an_integer(): void
    {
        $this->assertNotContains(
            Schema::getColumnType('process_instances', 'subject_id'),
            ['bigint', 'integer'],
        );
    }
}
