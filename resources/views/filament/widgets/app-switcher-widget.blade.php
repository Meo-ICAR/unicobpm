<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-arrows-right-left" icon-color="gray">
        <x-slot name="heading">
            Le tue altre app
        </x-slot>

        @php($apps = $this->getAvailableApps())

        @if (empty($apps))
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Nessun altro account trovato con questa email.
            </p>
        @else
            <div class="flex flex-wrap gap-3">
                @foreach ($apps as $app)
                    <x-filament::button
                        color="gray"
                        icon="heroicon-o-arrow-top-right-on-square"
                        wire:click="switchTo('{{ $app['key'] }}')"
                    >
                        @if ($app['logo'])
                            <img src="{{ $app['logo'] }}" alt="{{ $app['label'] }}" class="h-5 w-auto" />
                        @else
                            {{ $app['label'] }}
                        @endif
                    </x-filament::button>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
