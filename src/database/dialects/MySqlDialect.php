<?php

final class MySqlDialect implements DatabaseDialectInterface
{
    public function name(): string
    {
        return 'mysql';
    }

    public function jsonEmptyObjectExpression(): string
    {
        return 'JSON_OBJECT()';
    }

    public function jsonValuePlaceholder($value, array &$params, bool $useJsonCast): string
    {
        if ($useJsonCast) {
            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }
            if ($value === null) {
                $params[] = 'null';
                return 'CAST(? AS JSON)';
            }
            if (is_array($value) || is_object($value)) {
                $params[] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                return 'CAST(? AS JSON)';
            }
        }

        if (is_array($value) || is_object($value)) {
            $params[] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return '?';
        }

        $params[] = $value;
        return '?';
    }

    public function castAsStringExpression(string $column): string
    {
        return "CAST($column AS CHAR)";
    }

    public function groupConcatExpression(string $column, string $separator): string
    {
        $separator = str_replace("'", "''", $separator);
        return "GROUP_CONCAT($column SEPARATOR '$separator')";
    }

    public function compileUpsertSql(
        string $quotedTable,
        array $quotedColumns,
        string $placeholders,
        array $updateColumns,
        array $uniqueByColumns,
        callable $quoteIdentifier
    ): string {
        if (empty($updateColumns)) {
            $firstCol = $quotedColumns[0] ?? '';
            if ($firstCol === '') {
                throw new Exception('Cannot compile upsert without columns.');
            }
            $updateSql = "{$firstCol} = {$firstCol}";
        } else {
            $parts = [];
            foreach ($updateColumns as $column) {
                $quoted = $quoteIdentifier((string)$column);
                $parts[] = "{$quoted} = VALUES({$quoted})";
            }
            $updateSql = implode(', ', $parts);
        }

        return "INSERT INTO {$quotedTable} (" . implode(', ', $quotedColumns) . ") VALUES ({$placeholders}) ON DUPLICATE KEY UPDATE {$updateSql}";
    }

    public function fetchUniqueIndexes(PDO $pdo, string $table, ?string $schema, callable $quoteIdentifier): array
    {
        $sql = "SHOW INDEX FROM " . $quoteIdentifier($table);
        if ($schema !== null) {
            $sql .= " FROM " . $quoteIdentifier($schema);
        }

        $stmt = $pdo->query($sql);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        if (empty($rows)) {
            return [];
        }

        $indexes = [];
        foreach ($rows as $row) {
            if ((int)($row['Non_unique'] ?? 1) !== 0) {
                continue;
            }

            $name = (string)($row['Key_name'] ?? '');
            $seq = (int)($row['Seq_in_index'] ?? 0);
            $column = strtolower((string)($row['Column_name'] ?? ''));
            if ($name === '' || $seq < 1 || $column === '') {
                continue;
            }

            $indexes[$name][$seq] = $column;
        }

        return $indexes;
    }
}

