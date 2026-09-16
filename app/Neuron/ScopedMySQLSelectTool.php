<?php

declare(strict_types=1);

namespace App\Neuron;

use NeuronAI\Tools\Toolkits\MySQL\MySQLSelectTool;
use PDO;

/**
 * MySQLSelectTool valida solo che la query sia di sola lettura (SELECT/SHOW/...),
 * ma non limita QUALI tabelle può interrogare: un utente potrebbe chiedere
 * all'assistente di leggere `users` (password hash, token Microsoft) o altre
 * tabelle non pertinenti. Qui aggiungiamo un allowlist di tabelle come seconda
 * barriera, oltre a quella già applicata a MySQLSchemaTool (che limita solo
 * cosa l'LLM "vede" nello schema, non cosa può interrogare).
 *
 * @method static static make(PDO $pdo, array $allowedTables)
 */
class ScopedMySQLSelectTool extends MySQLSelectTool
{
    /**
     * Il pacchetto non offre un limite di righe integrato: senza questo taglio
     * l'assistente potrebbe restituire (e far girare nel contesto del modello)
     * tabelle intere.
     */
    protected const MAX_ROWS = 50;

    /**
     * @param  array<int, string>  $allowedTables
     */
    public function __construct(PDO $pdo, protected array $allowedTables)
    {
        parent::__construct($pdo);
    }

    public function __invoke(string $query, ?array $parameters = []): string|array
    {
        $result = parent::__invoke($query, $parameters);

        if (is_array($result) && count($result) > self::MAX_ROWS) {
            return array_slice($result, 0, self::MAX_ROWS);
        }

        return $result;
    }

    protected function validateReadOnly(string $query): bool
    {
        if (! parent::validateReadOnly($query)) {
            return false;
        }

        return $this->referencesOnlyAllowedTables($query);
    }

    protected function referencesOnlyAllowedTables(string $query): bool
    {
        preg_match_all('/\b(?:FROM|JOIN)\s+`?([a-zA-Z0-9_]+)`?/i', $query, $matches);

        $allowed = array_map(strtolower(...), $this->allowedTables);

        foreach (array_map(strtolower(...), $matches[1]) as $table) {
            if (! in_array($table, $allowed, true)) {
                return false;
            }
        }

        return true;
    }
}
