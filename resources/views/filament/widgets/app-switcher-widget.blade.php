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
                    @if ($app['post'] ?? false)
                        {{-- Il token si chiede con un POST e parte verso l'app in POST: mai in un indirizzo. --}}
                        <form method="POST" action="{{ route('sso.passa', $app['key']) }}">
                            @csrf
                            <x-filament::button type="submit" color="gray" icon="heroicon-o-arrow-top-right-on-square">
                                <span class="flex items-center gap-2">
                                    @if ($app['logo'])
                                        <img src="{{ $app['logo'] }}" alt="" class="h-5 w-5 rounded-sm object-contain" />
                                    @endif
                                    {{ $app['label'] }}
                                </span>
                            </x-filament::button>
                        </form>
                    @else
                        <x-filament::button
                            color="gray"
                            icon="heroicon-o-arrow-top-right-on-square"
                            wire:click="switchTo('{{ $app['key'] }}')"
                        >
                            <span class="flex items-center gap-2">
                                @if ($app['logo'])
                                    <img src="{{ $app['logo'] }}" alt="" class="h-5 w-5 rounded-sm object-contain" />
                                @endif
                                {{ $app['label'] }}
                            </span>
                        </x-filament::button>
                    @endif
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
