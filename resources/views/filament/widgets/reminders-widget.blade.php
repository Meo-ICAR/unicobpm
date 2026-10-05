<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-bell-alert" icon-color="warning">
        <x-slot name="heading">
            Cosa devo fare
        </x-slot>

        @php($work = $this->getMyWork())

        @if ($work['assigned']->isEmpty() && $work['claimable']->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Nessuna pratica in carico o da prendere in carico al momento.
            </p>
        @endif

        @if ($work['assigned']->isNotEmpty())
            <div class="mb-4">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">In carico a te</h3>
                <ul class="space-y-2">
                    @foreach ($work['assigned'] as $instance)
                        @php($overdue = $instance->isPastHardDeadline() || ($instance->currentTaskExecution?->isOverdue() ?? false))
                        <li>
                            <a href="{{ $this->editUrl($instance) }}" class="flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-700 p-3 hover:bg-gray-50 dark:hover:bg-gray-800">
                                <span>
                                    <span class="font-medium">{{ $instance->process?->name }}</span>
                                    <span class="text-gray-500 dark:text-gray-400"> — {{ $instance->currentTask?->name }}</span>
                                </span>
                                @if ($overdue)
                                    <x-filament::badge color="danger">In ritardo</x-filament::badge>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($work['claimable']->isNotEmpty())
            <div>
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Da prendere in carico</h3>
                <ul class="space-y-2">
                    @foreach ($work['claimable'] as $instance)
                        <li>
                            <a href="{{ $this->editUrl($instance) }}" class="block rounded-lg border border-gray-200 dark:border-gray-700 p-3 hover:bg-gray-50 dark:hover:bg-gray-800">
                                <span class="font-medium">{{ $instance->process?->name }}</span>
                                <span class="text-gray-500 dark:text-gray-400"> — {{ $instance->currentTask?->name }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
