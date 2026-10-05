<?php

declare(strict_types=1);

namespace App\Neuron;

use NeuronAI\Tools\Toolkits\MySQL\MySQLSchemaTool;
use PDO;

/**
 * MySQLSchemaTool::getRelationships() ordina per una colonna
 * (kcu.ORDINAL_POSITION) assente dalla SELECT DISTINCT: sotto lo sql_mode di
 * questo server (ONLY_FULL_GROUP_BY) MySQL rifiuta la query con l'errore
 * "Expression #2 of ORDER BY clause is not in SELECT list ... incompatible
 * with DISTINCT". Qui la stessa query, ordinata solo per una colonna già
 * selezionata.
 *
 * @method static static make(PDO $pdo, ?array $tables = null)
 */
class ScopedMySQLSchemaTool extends MySQLSchemaTool
{
    protected function getRelationships(): array
    {
        $whereClause = 'WHERE kcu.TABLE_SCHEMA = DATABASE() AND kcu.REFERENCED_TABLE_NAME IS NOT NULL';
        $params = [];

        if ($this->tables !== null && $this->tables !== []) {
            $placeholders = str_repeat('?,', count($this->tables) - 1).'?';
            $whereClause .= " AND (kcu.TABLE_NAME IN ({$placeholders}) OR kcu.REFERENCED_TABLE_NAME IN ({$placeholders}))";
            $params = array_merge($this->tables, $this->tables);
        }

        $stmt = $this->pdo->prepare("
            SELECT DISTINCT
                kcu.CONSTRAINT_NAME,
                kcu.TABLE_NAME as source_table,
                kcu.COLUMN_NAME as source_column,
                kcu.REFERENCED_TABLE_NAME as target_table,
                kcu.REFERENCED_COLUMN_NAME as target_column,
                rc.UPDATE_RULE,
                rc.DELETE_RULE
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
            JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
                ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
                AND kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
            {$whereClause}
            ORDER BY kcu.TABLE_NAME
        ");

        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
