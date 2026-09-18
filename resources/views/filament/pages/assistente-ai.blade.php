<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6">
        <x-filament::section icon="heroicon-o-chat-bubble-left-right" icon-color="primary">
            <x-slot name="heading">
                Chiedi all'Assistente AI
            </x-slot>

            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                Risponde usando il manuale operativo dell'applicazione. Se non trova la risposta nel manuale, te lo dirà invece di inventarla.
            </p>

            {{ $this->form }}

            <div wire:loading wire:target="send" class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                Sto cercando nel manuale...
            </div>

            @if ($error)
                <div class="mt-4 rounded-lg bg-danger-50 dark:bg-danger-500/10 p-4 text-sm text-danger-700 dark:text-danger-400">
                    {{ $error }}
                </div>
            @endif

            @if ($answer)
                <div class="mt-4 rounded-lg bg-gray-50 dark:bg-gray-800 p-4 prose dark:prose-invert max-w-none">
                    {!! Str::markdown($answer, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                </div>

                @if ($inputTokens !== null)
                    <div class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                        Token Anthropic — input: {{ $inputTokens }}, output: {{ $outputTokens }}
                        @if ($cachedInputTokens)
                            , da cache: {{ $cachedInputTokens }}
                        @endif
                        (totale: {{ $inputTokens + $outputTokens }})
                    </div>
                @endif
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
