<?php

declare(strict_types=1);

namespace App\Neuron;

use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\OutOfRange;

/** Output strutturato di EmailReplyAgent. */
class EmailReplyResult
{
    public function __construct(
        #[SchemaProperty(description: 'Intento di chi risponde: confirm, refuse, postpone, question oppure other.', required: true)]
        public string $intent,
        #[SchemaProperty(description: "Confidenza dell'interpretazione, da 0 (nessuna) a 100 (certa).", required: true)]
        #[OutOfRange(min: 0, max: 100)]
        public int $confidence,
        #[SchemaProperty(description: 'Riassunto in italiano, in una frase, di ciò che dice la risposta.', required: false)]
        public ?string $summary,
        #[SchemaProperty(description: 'Data proposta nella risposta, formato AAAA-MM-GG; null se non ce n\'è.', required: false)]
        public ?string $date,
    ) {}
}
