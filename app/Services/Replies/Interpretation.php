<?php

namespace App\Services\Replies;

/** Cosa dice una risposta email: intento, quanto è sicura l'interpretazione, riassunto, data proposta e chi l'ha capita (rule o ai). */
final class Interpretation
{
    public const CONFIRM = 'confirm';

    public const REFUSE = 'refuse';

    public const POSTPONE = 'postpone';

    public const QUESTION = 'question';

    public const OTHER = 'other';

    public function __construct(
        public readonly string $intent,
        public readonly int $confidence,
        public readonly ?string $summary = null,
        public readonly ?string $date = null,
        public readonly string $source = 'rule',
    ) {}
}
