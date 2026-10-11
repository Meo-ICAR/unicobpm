<?php

namespace App\Services\Replies;

/** Prova gli interpreti nell'ordine dato (prima le regole, poi l'AI) e prende il primo che risponde. */
class CompositeReplyInterpreter implements ReplyInterpreter
{
    /** @param list<ReplyInterpreter> $interpreters */
    public function __construct(private array $interpreters) {}

    public function interpret(string $reply, ?string $question = null): ?Interpretation
    {
        foreach ($this->interpreters as $interpreter) {
            if (($result = $interpreter->interpret($reply, $question)) !== null) {
                return $result;
            }
        }

        return null;
    }
}
