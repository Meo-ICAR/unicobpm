<?php

namespace Tests\Feature;

use App\Filament\Resources\Tasks\Pages\CreateTask;
use App\Filament\Resources\Tasks\Pages\EditTask;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Models\DocumentType;
use App\Models\Task;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Pratica;

/**
 * Un plico si applica ai record del model collegato (`taskable`) che passano le due condizioni di attivazione.
 */
class TaskConditionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_both_trigger_conditions_must_hold(): void
    {
        $task = new Task([
            'taskable' => 'pratica',
            'trigger_field' => 'tipo_prodotto',
            'trigger_state' => 'equals',
            'trigger_value' => 'Cessione',
            'trigger_subfield' => 'stato_pratica',
            'trigger_subvalue' => 'PERFEZIONATA',
        ]);

        $this->assertTrue($task->matchesConditions(new Pratica(['tipo_prodotto' => 'Cessione', 'stato_pratica' => 'PERFEZIONATA'])));
        $this->assertFalse($task->matchesConditions(new Pratica(['tipo_prodotto' => 'Cessione', 'stato_pratica' => 'APERTA'])));
        $this->assertFalse($task->matchesConditions(new Pratica(['tipo_prodotto' => 'Mutuo', 'stato_pratica' => 'PERFEZIONATA'])));
    }

    public function test_record_of_another_model_is_rejected_and_exclusion_applies(): void
    {
        $task = new Task(['taskable' => 'pratica', 'exclude_field' => 'stato_pratica', 'exclude_state' => 'filled']);

        $this->assertTrue($task->matchesConditions(new Pratica));
        $this->assertFalse($task->matchesConditions(new Pratica(['stato_pratica' => 'APERTA'])));
        $this->assertFalse($task->matchesConditions(new User));
    }

    public function test_form_saves_the_trigger_columns(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Filament::setCurrentPanel('admin');

        Livewire::test(CreateTask::class)
            ->fillForm([
                'name' => 'Plico di prova',
                'taskable' => 'pratica',
                'trigger_field' => 'tipo_prodotto',
                'trigger_state' => 'equals',
                'trigger_value' => 'Cessione',
                'trigger_subfield' => 'stato_pratica',
                'trigger_subvalue' => 'PERFEZIONATA',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('tasks', ['name' => 'Plico di prova', 'trigger_subfield' => 'stato_pratica', 'trigger_subvalue' => 'PERFEZIONATA']);
    }

    public function test_form_saves_required_documents_and_clone_copies_them(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Filament::setCurrentPanel('admin');
        $type = DocumentType::create(['name' => 'Carta identità', 'code' => 'CI']);

        Livewire::test(CreateTask::class)
            ->fillForm([
                'name' => 'Plico con documenti',
                'taskable' => 'pratica',
                'documentRequirements' => [['document_type_id' => $type->id, 'is_required' => false, 'documentable' => 'client', 'client_roles' => ['richiedente', 'garante']]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $task = Task::where('name', 'Plico con documenti')->firstOrFail();
        $this->assertDatabaseHas('document_requirements', ['task_id' => $task->id, 'document_type_id' => $type->id, 'is_required' => 0, 'sort_order' => 1, 'documentable' => 'client']);

        Livewire::test(ListTasks::class)->callTableAction('clone', $task);

        $clone = Task::where('name', 'Plico con documenti (Copia)')->firstOrFail();
        $this->assertDatabaseHas('document_requirements', ['task_id' => $clone->id, 'document_type_id' => $type->id, 'is_required' => 0, 'documentable' => 'client']);
        $this->assertSame(['richiedente', 'garante'], $clone->documentRequirements()->first()->client_roles);
    }

    public function test_edit_page_populates_the_task_from_other_tasks(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Filament::setCurrentPanel('admin');
        $type = DocumentType::create(['name' => 'TEGM', 'code' => 'TEGM']);
        $task = Task::create(['name' => 'Mutuo', 'taskable' => 'pratica']);
        $sub = Task::create(['name' => 'Documenti comuni', 'taskable' => 'pratica']);
        $sub->documentRequirements()->create(['document_type_id' => $type->id, 'sort_order' => 1]);

        Livewire::test(EditTask::class, ['record' => $task->getKey()])
            ->callAction('popolaDaAltriTask', ['source_ids' => [$sub->id]])
            ->assertNotified();

        $this->assertDatabaseHas('document_requirements', ['task_id' => $task->id, 'document_type_id' => $type->id]);
    }
}
