<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProcessInstances\ProcessInstanceResource;
use App\Models\ProcessInstance;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * "Cosa devo fare": le pratiche in corso assegnate all'utente loggato, più
 * quelle che il suo reparto (RACI 'R') può ancora prendere in carico —
 * riusa gli scope già esistenti su ProcessInstance
 * (whereCanBeClaimedBy/currentAssignee), non introduce nuova logica RACI.
 */
class RemindersWidget extends Widget
{
    protected string $view = 'filament.widgets.reminders-widget';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array{assigned: Collection<int, ProcessInstance>, claimable: Collection<int, ProcessInstance>}
     */
    public function getMyWork(): array
    {
        $profile = auth()->user()?->profile;

        if (! $profile) {
            return ['assigned' => collect(), 'claimable' => collect()];
        }

        $assigned = ProcessInstance::query()
            ->where('status', 'in_progress')
            ->where('current_assignee_type', get_class($profile))
            ->where('current_assignee_id', $profile->getKey())
            ->with(['process', 'currentTask', 'currentTaskExecution'])
            ->get();

        $claimable = ProcessInstance::query()
            ->whereCanBeClaimedBy($profile)
            ->with(['process', 'currentTask'])
            ->get();

        return ['assigned' => $assigned, 'claimable' => $claimable];
    }

    public function editUrl(ProcessInstance $instance): string
    {
        return ProcessInstanceResource::getUrl('edit', ['record' => $instance]);
    }
}
