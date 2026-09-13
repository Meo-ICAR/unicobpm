<?php

namespace App\Livewire;

use App\Models\Process;
use App\Models\ProcessTask;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ProcessRaciMatrix extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public Process $process;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ProcessTask::query()
                    ->where('process_id', $this->process->id)
                    ->with('raciAssignments.businessFunction')
            )
            ->defaultSort('ordine')
            ->paginated(false)
            ->columns([
                TextColumn::make('ordine')
                    ->label('#'),
                TextColumn::make('name')
                    ->label('Task')
                    ->weight('bold'),
                TextColumn::make('raci_r')
                    ->label('R')
                    ->badge()
                    ->color('info')
                    ->state(fn (ProcessTask $record) => static::namesForRole($record, 'R'))
                    ->placeholder('—'),
                TextColumn::make('raci_a')
                    ->label('A')
                    ->badge()
                    ->color('danger')
                    ->state(fn (ProcessTask $record) => static::namesForRole($record, 'A'))
                    ->placeholder('—'),
                TextColumn::make('raci_c')
                    ->label('C')
                    ->badge()
                    ->color('warning')
                    ->state(fn (ProcessTask $record) => static::namesForRole($record, 'C'))
                    ->placeholder('—'),
                TextColumn::make('raci_i')
                    ->label('I')
                    ->badge()
                    ->color('gray')
                    ->state(fn (ProcessTask $record) => static::namesForRole($record, 'I'))
                    ->placeholder('—'),
            ]);
    }

    /**
     * @return array<int, string>
     */
    protected static function namesForRole(ProcessTask $task, string $role): array
    {
        return $task->raciAssignments
            ->where('raci_role', $role)
            ->map(fn ($assignment) => $assignment->businessFunction?->name)
            ->filter()
            ->values()
            ->toArray();
    }

    public function render(): View
    {
        return view('livewire.process-raci-matrix');
    }
}
