<?php

declare(strict_types=1);

namespace App\Neuron;

use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\OutOfRange;

/**
 * Output strutturato di DocumentClassifierAgent: quale candidato (tra quelli elencati nel
 * prompt) corrisponde al file allegato, con quanta confidenza.
 */
class DocumentClassificationResult
{
    public function __construct(
        #[SchemaProperty(
            description: "ID dell'item candidato che corrisponde al documento, tra quelli elencati nel prompt. Null se nessun candidato corrisponde chiaramente.",
            required: false,
        )]
        public ?int $item_id,
        #[SchemaProperty(
            description: 'Confidenza della classificazione, da 0 (nessuna) a 100 (certa).',
            required: true,
        )]
        #[OutOfRange(min: 0, max: 100)]
        public int $confidence,
        #[SchemaProperty(
            description: 'Breve riassunto in italiano di cosa contiene il documento.',
            required: false,
        )]
        public ?string $abstract,
    ) {}
}
