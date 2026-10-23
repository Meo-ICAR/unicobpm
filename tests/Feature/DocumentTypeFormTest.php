<?php

namespace Tests\Feature;

use App\Filament\Resources\DocumentTypes\Pages\EditDocumentType;
use App\Models\DocumentType;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Il tipo documento dice come si ottiene, a chi si applica, chi firma e chi approva; il form segnala le incoerenze.
 */
class DocumentTypeFormTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_source_scope_signature_and_end_of_month_are_saved_and_problems_are_shown(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Filament::setCurrentPanel('admin');
        $type = DocumentType::create(['name' => 'Informativa', 'code' => 'INF', 'slug' => 'informativa', 'source' => 'template']);

        Livewire::test(EditDocumentType::class, ['record' => $type->getKey()])
            ->assertSee('Fonte template senza URL né file del template.')
            ->fillForm([
                'source' => 'template',
                'document_url' => 'https://example.com/informativa.pdf',
                'client_scope' => 'persona_fisica',
                'is_signed' => true,
                'signer_model' => 'fornitore',
                'approver_model' => 'employee',
                'is_monitored' => true,
                'duration' => 12,
                'duration_unit' => 'months',
                'is_end_month' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $type->refresh();
        $this->assertSame('persona_fisica', $type->client_scope);
        $this->assertSame('fornitore', $type->signer_model);
        $this->assertSame('employee', $type->approver_model);
        $this->assertTrue($type->is_end_month);
        $this->assertTrue($type->is_template);
    }
}
