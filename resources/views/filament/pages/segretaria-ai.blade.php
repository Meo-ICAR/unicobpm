<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6">
        <x-filament::section icon="heroicon-o-user-circle" icon-color="primary">
            <x-slot name="heading">
                Segretaria AI
            </x-slot>

            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                Ricorda la conversazione e può occuparsi di richieste estemporanee (es. preparare un'email di sollecito). Le azioni con effetti reali restano sempre bozze in attesa della tua conferma.
            </p>

            @if ($messages->isNotEmpty())
                <div class="grid grid-cols-1 gap-3 mb-4">
                    @foreach ($messages as $message)
                        @continue (blank($message->textContent()))

                        <div
                            wire:key="message-{{ $message->id }}"
                            @class([
                                'rounded-lg p-4 prose dark:prose-invert max-w-none text-sm',
                                'bg-primary-50 dark:bg-primary-500/10' => $message->role === 'user',
                                'bg-gray-50 dark:bg-gray-800' => $message->role !== 'user',
                            ])
                        >
                            <div class="not-prose text-xs font-medium text-gray-400 dark:text-gray-500 mb-1">
                                {{ $message->role === 'user' ? 'Tu' : 'Segretaria AI' }}
                            </div>

                            {!! Str::markdown($message->textContent(), ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                        </div>
                    @endforeach
                </div>
            @endif

            {{ $this->form }}

            <div wire:loading wire:target="send" class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                Sto elaborando la richiesta...
            </div>

            @if ($error)
                <div class="mt-4 rounded-lg bg-danger-50 dark:bg-danger-500/10 p-4 text-sm text-danger-700 dark:text-danger-400">
                    {{ $error }}
                </div>
            @endif
        </x-filament::section>

        @if ($pendingDrafts->isNotEmpty())
            <x-filament::section icon="heroicon-o-envelope" icon-color="warning">
                <x-slot name="heading">
                    Bozze email in attesa di conferma
                </x-slot>

                <div class="grid grid-cols-1 gap-4">
                    @foreach ($pendingDrafts as $draft)
                        <div wire:key="draft-{{ $draft->id }}" class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <div class="text-sm">
                                <span class="font-medium">A:</span> {{ $draft->payload['to_name'] ?? '' }} &lt;{{ $draft->payload['to'] }}&gt;
                            </div>
                            <div class="text-sm mt-1">
                                <span class="font-medium">Oggetto:</span> {{ $draft->payload['subject'] }}
                            </div>
                            <div class="text-sm mt-2 whitespace-pre-line text-gray-600 dark:text-gray-400">{{ $draft->payload['body'] }}</div>

                            <div class="mt-3 flex gap-2">
                                <x-filament::button size="sm" color="success" wire:click="confirmDraft({{ $draft->id }})" wire:loading.attr="disabled">
                                    Invia
                                </x-filament::button>
                                <x-filament::button size="sm" color="gray" wire:click="cancelDraft({{ $draft->id }})" wire:loading.attr="disabled">
                                    Annulla
                                </x-filament::button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
