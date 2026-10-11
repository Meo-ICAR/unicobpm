<?php

namespace App\Services\Replies;

interface ReplyInterpreter
{
    /**
     * @param  string  $reply  testo della risposta, già senza il messaggio citato
     * @param  string|null  $question  cosa si era chiesto (configurazione dell'azione), se nota
     */
    public function interpret(string $reply, ?string $question = null): ?Interpretation;
}
