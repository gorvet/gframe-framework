<?php

namespace GFrame\Install;

final class SqlStatementParser
{
    /** @return list<string> */
    public function parse(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $length = strlen($sql);

        for ($index = 0; $index < $length; $index++) {
            $char = $sql[$index];
            $next = $index + 1 < $length ? $sql[$index + 1] : '';

            if ($quote === null && $char === '-' && $next === '-') {
                while ($index < $length && $sql[$index] !== "\n") {
                    $index++;
                }
                $buffer .= "\n";
                continue;
            }
            if ($quote === null && $char === '/' && $next === '*') {
                $index += 2;
                while ($index + 1 < $length && !($sql[$index] === '*' && $sql[$index + 1] === '/')) {
                    $index++;
                }
                $index++;
                continue;
            }

            if ($quote !== null) {
                $buffer .= $char;
                if ($char === '\\' && $index + 1 < $length) {
                    $buffer .= $sql[++$index];
                    continue;
                }
                if ($char === $quote) {
                    if ($next === $quote) {
                        $buffer .= $sql[++$index];
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }
}
