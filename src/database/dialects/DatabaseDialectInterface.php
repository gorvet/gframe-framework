<?php

interface DatabaseDialectInterface
{
    public function name(): string;

    public function jsonEmptyObjectExpression(): string;

    public function jsonValuePlaceholder($value, array &$params, bool $useJsonCast): string;

    public function castAsStringExpression(string $column): string;

    public function groupConcatExpression(string $column, string $separator): string;

    /**
     * @param array<int, string> $quotedColumns
     * @param array<int, string> $updateColumns
     * @param array<int, string> $uniqueByColumns
     */
    public function compileUpsertSql(
        string $quotedTable,
        array $quotedColumns,
        string $placeholders,
        array $updateColumns,
        array $uniqueByColumns,
        callable $quoteIdentifier
    ): string;

    /**
     * @return array<string, array<int, string>>
     */
    public function fetchUniqueIndexes(PDO $pdo, string $table, ?string $schema, callable $quoteIdentifier): array;
}

