<?php

namespace App\Services\Replies;

/**
 * Riconosce le risposte inequivocabili e brevi («Sì», «Confermo», «Non accetto») senza chiamare l'AI. Tutto il resto lo lascia
 * all'interprete successivo: nel dubbio non decide.
 */
class RuleBasedReplyInterpreter implements ReplyInterpreter
{
    public function interpret(string $reply, ?string $question = null): ?Interpretation
    {
        $text = mb_strtolower(trim($reply));

        if ($text === '' || mb_strlen($text) > 160) {
            return null;
        }

        if (preg_match('/\b(non (accetto|confermo|sono d.accordo|approvo|autorizzo)|rifiuto|declino|no grazie)\b/iu', $text) || preg_match('/^no[\s,.!]*$/iu', $text)) {
            return new Interpretation(Interpretation::REFUSE, 90, mb_substr($reply, 0, 200), source: 'rule');
        }

        if (preg_match('/\b(non|nessun|però|ma|se|quando|prima)\b/iu', $text)) {
            return null; // condizioni, riserve, negazioni: serve capire il contesto
        }

        if (preg_match('/^(s[ìi]|ok|okay|certo|certamente|perfetto|confermo|confermato|accetto|va bene|d.accordo|approvato)\b/iu', $text)) {
            return new Interpretation(Interpretation::CONFIRM, 90, mb_substr($reply, 0, 200), source: 'rule');
        }

        return null;
    }
}
