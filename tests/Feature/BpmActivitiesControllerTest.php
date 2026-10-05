<?php

namespace Tests\Feature;

use App\Models\Process;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BpmActivitiesControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lists_only_active_processes_matching_the_model_type(): void
    {
        Process::create(['name' => 'Onboarding Agente', 'code' => 'PRC-ONB', 'target_model' => 'fornitore', 'is_active' => true]);
        Process::create(['name' => 'AML', 'code' => 'PRC-AML', 'target_model' => 'cliente', 'is_active' => true]);
        Process::create(['name' => 'Inattivo', 'code' => 'PRC-OFF', 'target_model' => 'fornitore', 'is_active' => false]);

        $response = $this->postJson('/api/bpm/available-activities', [
            'model_type' => 'fornitore',
            'model_id' => 'abc-123',
            'fields' => [],
        ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'activities');
        $response->assertJsonFragment(['code' => 'PRC-ONB']);
    }

    public function test_excludes_a_process_whose_exclude_condition_is_met(): void
    {
        Process::create([
            'name' => 'Onboarding Agente',
            'code' => 'PRC-ONB',
            'target_model' => 'fornitore',
            'is_active' => true,
            'exclude_field' => 'stipulated_at',
            'exclude_state' => 'filled',
        ]);

        $response = $this->postJson('/api/bpm/available-activities', [
            'model_type' => 'fornitore',
            'model_id' => 'abc-123',
            'fields' => ['stipulated_at' => '2024-01-01'],
        ]);

        $response->assertOk();
        $response->assertJsonCount(0, 'activities');
    }

    public function test_includes_a_process_whose_exclude_condition_is_not_met(): void
    {
        Process::create([
            'name' => 'Onboarding Agente',
            'code' => 'PRC-ONB',
            'target_model' => 'fornitore',
            'is_active' => true,
            'exclude_field' => 'stipulated_at',
            'exclude_state' => 'filled',
        ]);

        $response = $this->postJson('/api/bpm/available-activities', [
            'model_type' => 'fornitore',
            'model_id' => 'abc-123',
            'fields' => ['stipulated_at' => null],
        ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'activities');
    }
}
