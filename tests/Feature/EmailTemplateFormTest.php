<?php

namespace Tests\Feature;

use App\Filament\Resources\EmailTemplates\Pages\CreateEmailTemplate;
use App\Models\Company;
use App\Models\EmailTemplate;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmailTemplateFormTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_template_is_saved_with_the_company_app_and_type_used_to_choose_it(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $company = Company::query()->create(['name' => 'Races']);

        Livewire::test(CreateEmailTemplate::class)
            ->fillForm([
                'code' => 'TASK_ESCALATION',
                'name' => 'Sollecito presa in carico',
                'subject' => 'Pratica {pratica} in attesa',
                'body' => '<p>Il passo {task} attende da {ore} ore.</p>',
                'is_active' => true,
                'company_id' => $company->getKey(),
                'app_identifier' => 'unicobpm',
                'type' => 'processo',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $template = EmailTemplate::query()->where('code', 'TASK_ESCALATION')->sole();
        $this->assertSame($company->getKey(), $template->company_id);
        $this->assertSame('unicobpm', $template->app_identifier);
        $this->assertSame('processo', $template->type);
    }
}
