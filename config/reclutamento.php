<?php

return [
    /*
    | Retention delle candidature alla rete commerciale non inserite (decisione del 23/11/2026, TBD-06 del modello operativo):
    | dopo questi mesi dalla chiusura della candidatura (pratica annullata, rifiutata o archiviata senza seguito) i dati del candidato
    | si cancellano (comando reclutamento:retention). I candidati con pratica in corso o inseriti in rete non si toccano.
    */
    'retention_months' => (int) env('RECLUTAMENTO_RETENTION_MONTHS', 6),
];
