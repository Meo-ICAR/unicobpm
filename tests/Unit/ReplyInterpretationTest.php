<?php

namespace Tests\Unit;

use App\Services\Replies\Interpretation;
use App\Services\Replies\RuleBasedReplyInterpreter;
use App\Services\Replies\ReplyText;
use PHPUnit\Framework\TestCase;

class ReplyInterpretationTest extends TestCase
{
    /** @return array<string, array{0: string, 1: ?string}> */
    public static function replies(): array
    {
        return [
            'sì' => ['Sì', 'confirm'], 'ok' => ['OK, grazie', 'confirm'], 'confermo' => ['Confermo la data.', 'confirm'], 'va bene' => ['Va bene così', 'confirm'],
            'no' => ['No', 'refuse'], 'non accetto' => ['Non accetto, mi spiace', 'refuse'], 'rifiuto' => ['Rifiuto la proposta', 'refuse'],
            'condizionata' => ['Sì ma solo se spostiamo la data', null], 'negazione' => ['Non ho capito la richiesta', null],
            'lunga' => [str_repeat('Vi scrivo per spiegare nel dettaglio la situazione. ', 8), null], 'vuota' => ['', null], 'altro' => ['Vi richiamo domani', null],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('replies')]
    public function test_only_clear_short_replies_are_decided_by_the_rules(string $reply, ?string $intent): void
    {
        $result = (new RuleBasedReplyInterpreter)->interpret($reply);

        $this->assertSame($intent, $result?->intent);
        if ($result !== null) {
            $this->assertSame(90, $result->confidence);
            $this->assertSame('rule', $result->source);
        }
    }

    public function test_quoted_text_and_signature_are_removed(): void
    {
        $body = "Confermo.\n\n--\nMario Rossi\n\nIl giorno 10 novembre 2026, Acme <info@acme.test> ha scritto:\n> Conferma la visita?";

        $this->assertSame('Confermo.', ReplyText::clean($body));
        $this->assertSame("Sì\nanche domani", ReplyText::clean("Sì\nanche domani\n> citazione\n-----Original Message-----\nDa: x@y.it"));
        $this->assertSame('', ReplyText::clean(null));
        $this->assertSame(10, mb_strlen(ReplyText::clean(str_repeat('a', 50), 10)));
    }

    public function test_the_intent_constants_are_the_ones_stored(): void
    {
        $this->assertSame(['confirm', 'refuse', 'postpone', 'question', 'other'], [Interpretation::CONFIRM, Interpretation::REFUSE, Interpretation::POSTPONE, Interpretation::QUESTION, Interpretation::OTHER]);
    }
}
