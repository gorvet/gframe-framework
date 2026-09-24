<?php

final class SqliteDialect implements DatabaseDialectInterface
{
    public function name(): string
    {
        return 'sqlite';
    }

    public function jsonEmptyObjectExpression(): string
    {
        return "json('{}')";
    }

    public function jsonValuePlaceholder($value, array &$params, bool $useJsonCast): string
    {
        if (is_bool($value)) {
            $params[] = $value ? 'true' : 'false';
            return 'json(?)';
        }

        if ($value === null) {
            $params[] = 'null';
            return 'json(?)';
        }

        if (is_array($value) || is_object($value)) {
            $params[] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return 'json(?)';
        }

        $params[] = $value;
        return '?';
    }

    public function castAsStringExpression(string $column): string
    {
        return "CAST($column AS TEXT)";
    }

    public function groupConcatExpression(string $column, string $separator): string
    {
        $separator = str_replace("'", "''", $separator);
        return "GROUP_CONCAT($column, '$separator')";
    }

    public function compileUpsertSql(
        string $quotedTable,
        array $quotedColumns,
        string $placeholders,
        array $updateColumns,
        array $uniqueByColumns,
        callable $quoteIdentifier
    ): string {
        $conflictCols = array_map(
            static fn($column) => $quoteIdentifier((string)$column),
            $uniqueByColumns
        );
        if (empty($conflictCols)) {
            throw new Exception('Cannot compile sqlite upsert without conflict columns.');
        }

        if (empty($updateColumns)) {
            return "INSERT INTO {$quotedTable} (" . implode(', ', $quotedColumns) . ") VALUES ({$placeholders}) ON CONFLICT (" . implode(', ', $conflictCols) . ") DO NOTHING";
        }

        $parts = [];
        foreach ($updateColumns as $column) {
            $quoted = $quoteIdentifier((string)$column);
            $parts[] = "{$quoted} = excluded.{$quoted}";
        }
        $updateSql = implode(', ', $parts);

        return "INSERT INTO {$quotedTable} (" . implode(', ', $quotedColumns) . ") VALUES ({$placeholders}) ON CONFLICT (" . implode(', ', $conflictCols) . ") DO UPDATE SET {$updateSql}";
    }

    public function fetchUniqueIndexes(PDO $pdo, string $table, ?string $schema, callable $quoteIdentifier): array
    {
        $schemaPrefix = '';
        if ($schema !== null) {
            $schemaPrefix = $quoteIdentifier($schema) . '.';
        }

        $tableLiteral = str_replace("'", "''", $table);
        $indexListSql = "PRAGMA {$schemaPrefix}index_list('{$tableLiteral}')";
        $listStmt = $pdo->query($indexListSql);
        $listRows = $listStmt ? $listStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        if (empty($listRows)) {
            return [];
        }

        $indexes = [];
        foreach ($listRows as $row) {
            if ((int)($row['unique'] ?? 0) !== 1) {
                continue;
            }

            $name = (string)($row['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $indexLiteral = str_replace("'", "''", $name);
            $infoSql = "PRAGMA {$schemaPrefix}index_info('{$indexLiteral}')";
            $infoStmt = $pdo->query($infoSql);
            $infoRows = $infoStmt ? $infoStmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($infoRows as $info) {
                $seq = (int)($info['seqno'] ?? -1) + 1;
                $column = strtolower((string)($info['name'] ?? ''));
                if ($seq < 1 || $column === '') {
                    continue;
                }
                $indexes[$name][$seq] = $column;
            }
        }

        return $indexes;
    }
}

