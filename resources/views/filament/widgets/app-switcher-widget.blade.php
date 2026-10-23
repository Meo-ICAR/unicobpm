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
                                <span style="display:flex;align-items:center;gap:0.5rem">
                                    @if ($app['logo'])
                                        <img src="{{ $app['logo'] }}" alt="" style="width:1.25rem;height:1.25rem;object-fit:contain;border-radius:0.125rem" />
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
                            <span style="display:flex;align-items:center;gap:0.5rem">
                                @if ($app['logo'])
                                    <img src="{{ $app['logo'] }}" alt="" style="width:1.25rem;height:1.25rem;object-fit:contain;border-radius:0.125rem" />
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
