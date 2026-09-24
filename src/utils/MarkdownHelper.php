<?php

/**
 * Utilidades estaticas para convertir entre Markdown y HTML.
 *
 * Perfiles soportados:
 * - chat: Markdown mas amplio para el chat web/admin.
 * - channel: Markdown simple compatible con canales (WhatsApp/Telegram).
 */
final class MarkdownHelper
{
    /** Perfil de conversion para chat web/admin. */
    private const PROFILE_CHAT = 'chat';
    /** Perfil de conversion para canales externos. */
    private const PROFILE_CHANNEL = 'channel';

    private function __construct()
    {
    }

    /**
     * Convierte Markdown de canal a HTML seguro.
     *
     * Soporta enfasis simple de canales:
     * *bold*, _italic_, ~strike~, `inline code`, ```code block```.
     *
     * @param string $text Markdown del canal.
     * @return string HTML renderizado.
     */
    public static function channelMarkdownToHtml(string $text): string
    {
        $source = str_replace(["\r\n", "\r"], "\n", (string)$text);
        if ($source === '') return '';

        $source = htmlspecialchars($source, ENT_QUOTES, 'UTF-8');

        $blockTokens = [];
        $blockIndex = 0;
        $source = preg_replace_callback('/```([\s\S]*?)```/u', static function ($m) use (&$blockTokens, &$blockIndex): string {
            $token = '__WAMD_BLOCK_' . $blockIndex . '__';
            $code = trim((string)($m[1] ?? ''), "\n");
            $blockTokens[$token] = '<pre><code>' . $code . '</code></pre>';
            $blockIndex++;
            return $token;
        }, $source);

        $inlineTokens = [];
        $inlineIndex = 0;
        $source = preg_replace_callback('/`([^`\n]+)`/u', static function ($m) use (&$inlineTokens, &$inlineIndex): string {
            $token = '__WAMD_INLINE_' . $inlineIndex . '__';
            $inlineTokens[$token] = '<code>' . (string)($m[1] ?? '') . '</code>';
            $inlineIndex++;
            return $token;
        }, $source);

        $source = preg_replace('/\*(?=\S)(.+?)(?<=\S)\*/u', '<strong>$1</strong>', (string)$source);
        $source = preg_replace('/_(?=\S)(.+?)(?<=\S)_/u', '<em>$1</em>', (string)$source);
        $source = preg_replace('/~(?=\S)(.+?)(?<=\S)~/u', '<del>$1</del>', (string)$source);
        $source = nl2br((string)$source);

        if (!empty($inlineTokens)) {
            $source = str_replace(array_keys($inlineTokens), array_values($inlineTokens), (string)$source);
        }
        if (!empty($blockTokens)) {
            $source = str_replace(array_keys($blockTokens), array_values($blockTokens), (string)$source);
        }

        return (string)$source;
    }

    /**
     * Convierte HTML a Markdown del perfil channel.
     *
     * @param string $html Contenido HTML.
     * @return string Markdown para canales.
     */
    public static function htmlToChannelMarkdown(string $html): string
    {
        return self::htmlToMarkdownWithProfile($html, self::PROFILE_CHANNEL);
    }

    /**
     * Convierte HTML a Markdown del perfil chat.
     *
     * @param string $html Contenido HTML.
     * @return string Markdown para chat.
     */
    public static function htmlToChatMarkdown(string $html): string
    {
        return self::htmlToMarkdownWithProfile($html, self::PROFILE_CHAT);
    }

    /**
     * Motor interno de conversion HTML -> Markdown basado en perfil.
     *
     * @param string $html HTML de entrada.
     * @param string $profile Perfil de salida (chat|channel).
     * @return string Markdown normalizado.
     */
    private static function htmlToMarkdownWithProfile(string $html, string $profile): string
    {
        $content = str_replace(["\r\n", "\r"], "\n", (string)$html);
        if (trim($content) === '') return '';

        if (!class_exists('DOMDocument')) {
            return self::normalizeMarkdownOutput((string)strip_tags($content));
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;

        $options = 0;
        if (defined('LIBXML_HTML_NOIMPLIED')) $options |= LIBXML_HTML_NOIMPLIED;
        if (defined('LIBXML_HTML_NODEFDTD')) $options |= LIBXML_HTML_NODEFDTD;

        $prevErrors = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8"><html><body>' . $content . '</body></html>', $options);
        libxml_clear_errors();
        libxml_use_internal_errors($prevErrors);

        if ($loaded !== true) {
            return self::normalizeMarkdownOutput((string)strip_tags($content));
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        $markdown = $body instanceof \DOMNode
            ? self::renderHtmlChildren($body, $profile, 0)
            : self::renderHtmlChildren($dom, $profile, 0);

        return self::normalizeMarkdownOutput($markdown);
    }

    /**
     * Renderiza todos los hijos de un nodo HTML a Markdown.
     *
     * @param \DOMNode $node Nodo origen.
     * @param string $profile Perfil de conversion.
     * @param int $listDepth Nivel actual de anidacion de listas.
     * @return string Markdown generado.
     */
    private static function renderHtmlChildren(\DOMNode $node, string $profile, int $listDepth): string
    {
        $result = '';
        foreach ($node->childNodes as $child) {
            $result .= self::renderHtmlNode($child, $profile, $listDepth);
        }
        return $result;
    }

    /**
     * Convierte un nodo HTML individual a Markdown segun perfil.
     *
     * @param \DOMNode $node Nodo a convertir.
     * @param string $profile Perfil de conversion.
     * @param int $listDepth Nivel actual de lista anidada.
     * @return string Markdown del nodo.
     */
    private static function renderHtmlNode(\DOMNode $node, string $profile, int $listDepth): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $value = html_entity_decode((string)$node->nodeValue, ENT_QUOTES, 'UTF-8');
            return str_replace("\u{00A0}", ' ', (string)$value);
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return '';
        }

        $tag = strtolower((string)$node->nodeName);
        $inner = self::renderHtmlChildren($node, $profile, $listDepth);

        if ($tag === 'br') {
            return "\n";
        }

        if ($tag === 'hr') {
            return $profile === self::PROFILE_CHAT ? "\n---\n\n" : "\n----------------\n\n";
        }

        if (in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
            $text = trim($inner);
            if ($text === '') return '';
            if ($profile === self::PROFILE_CHAT) {
                $level = (int)substr($tag, 1);
                return str_repeat('#', max(1, min(6, $level))) . ' ' . $text . "\n\n";
            }
            return $text . "\n\n";
        }

        if (in_array($tag, ['p', 'div', 'section', 'article', 'header', 'footer'], true)) {
            $text = trim($inner);
            return $text === '' ? '' : $text . "\n\n";
        }

        if (in_array($tag, ['strong', 'b'], true)) {
            return self::wrapInline(trim($inner), $profile === self::PROFILE_CHAT ? '**' : '*');
        }

        if (in_array($tag, ['em', 'i'], true)) {
            return self::wrapInline(trim($inner), $profile === self::PROFILE_CHAT ? '*' : '_');
        }

        if (in_array($tag, ['del', 's', 'strike'], true)) {
            return self::wrapInline(trim($inner), $profile === self::PROFILE_CHAT ? '~~' : '~');
        }

        if ($tag === 'code') {
            if ($node->parentNode instanceof \DOMElement && strtolower($node->parentNode->tagName) === 'pre') {
                return '';
            }
            $code = trim(html_entity_decode((string)$node->textContent, ENT_QUOTES, 'UTF-8'));
            if ($code === '') return '';
            return '`' . str_replace('`', '\`', $code) . '`';
        }

        if ($tag === 'pre') {
            $code = trim(html_entity_decode((string)$node->textContent, ENT_QUOTES, 'UTF-8'), "\n");
            if ($code === '') return '';
            return "```\n" . $code . "\n```\n\n";
        }

        if ($tag === 'a') {
            $label = trim($inner);
            $url = '';
            if ($node instanceof \DOMElement && $node->hasAttribute('href')) {
                $url = self::safeUrl((string)$node->getAttribute('href'));
            }
            if ($url === '') return $label;
            if ($profile === self::PROFILE_CHAT) {
                if ($label === '') $label = $url;
                return '[' . $label . '](' . $url . ')';
            }
            if ($label === '' || $label === $url) return $url;
            return $label . ' (' . $url . ')';
        }

        if ($tag === 'img') {
            if (!$node instanceof \DOMElement || !$node->hasAttribute('src')) return '';
            $src = self::safeUrl((string)$node->getAttribute('src'));
            if ($src === '') return '';
            $alt = $node->hasAttribute('alt') ? trim((string)$node->getAttribute('alt')) : '';
            if ($profile === self::PROFILE_CHAT) {
                return '![' . $alt . '](' . $src . ')';
            }
            if ($alt === '') return $src;
            return $alt . ' (' . $src . ')';
        }

        if ($tag === 'ul') {
            return self::renderListNode($node, false, $profile, $listDepth);
        }

        if ($tag === 'ol') {
            return self::renderListNode($node, true, $profile, $listDepth);
        }

        if ($tag === 'blockquote') {
            $text = trim($inner);
            if ($text === '') return '';
            $lines = preg_split("/\n/u", $text) ?: [];
            $lines = array_map(static fn(string $line): string => '> ' . ltrim($line), $lines);
            return implode("\n", $lines) . "\n\n";
        }

        if ($tag === 'table') {
            $rows = self::collectTableRows($node, $profile);
            if (empty($rows)) return '';

            if ($profile === self::PROFILE_CHANNEL) {
                $plainRows = array_map(static fn(array $cells): string => implode(' | ', $cells), $rows);
                return implode("\n", $plainRows) . "\n\n";
            }

            $markdownRows = [];
            $header = $rows[0];
            $markdownRows[] = '| ' . implode(' | ', $header) . ' |';
            $markdownRows[] = '| ' . implode(' | ', array_fill(0, count($header), '---')) . ' |';
            for ($i = 1; $i < count($rows); $i++) {
                $cells = $rows[$i];
                if (count($cells) < count($header)) {
                    $cells = array_pad($cells, count($header), '');
                }
                $markdownRows[] = '| ' . implode(' | ', array_slice($cells, 0, count($header))) . ' |';
            }
            return implode("\n", $markdownRows) . "\n\n";
        }

        return $inner;
    }

    /**
     * Convierte un nodo de lista (<ul>/<ol>) a Markdown.
     *
     * @param \DOMNode $node Nodo lista.
     * @param bool $ordered True para lista numerada.
     * @param string $profile Perfil de conversion.
     * @param int $listDepth Profundidad para indentacion.
     * @return string Markdown de la lista.
     */
    private static function renderListNode(\DOMNode $node, bool $ordered, string $profile, int $listDepth): string
    {
        $items = [];
        $index = 1;
        foreach ($node->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE || strtolower((string)$child->nodeName) !== 'li') {
                continue;
            }

            $raw = trim(self::renderHtmlChildren($child, $profile, $listDepth + 1));
            if ($raw === '') continue;

            $raw = (string)preg_replace("/\n{2,}/u", "\n", $raw);
            $lines = preg_split("/\n/u", $raw) ?: [];
            $prefix = $ordered ? ($index++) . '. ' : '- ';
            $baseIndent = str_repeat('  ', $listDepth);
            $nextIndent = str_repeat('  ', $listDepth + 1);

            $firstLine = array_shift($lines);
            $item = $baseIndent . $prefix . $firstLine;
            foreach ($lines as $line) {
                $item .= "\n" . $nextIndent . $line;
            }
            $items[] = $item;
        }

        if (empty($items)) return '';
        return implode("\n", $items) . "\n\n";
    }

    /**
     * Extrae las filas/celdas de una tabla HTML.
     *
     * @param \DOMNode $node Nodo tabla o seccion de tabla.
     * @param string $profile Perfil de conversion.
     * @return array Filas con celdas en texto plano.
     */
    private static function collectTableRows(\DOMNode $node, string $profile): array
    {
        $rows = [];

        foreach ($node->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $tag = strtolower((string)$child->nodeName);
            if ($tag === 'tr') {
                $cells = [];
                foreach ($child->childNodes as $cell) {
                    if ($cell->nodeType !== XML_ELEMENT_NODE) continue;
                    $cellTag = strtolower((string)$cell->nodeName);
                    if (!in_array($cellTag, ['th', 'td'], true)) continue;
                    $cells[] = trim((string)preg_replace("/\s+/u", ' ', self::renderHtmlChildren($cell, $profile, 0)));
                }
                if (!empty($cells)) {
                    $rows[] = $cells;
                }
                continue;
            }

            if (in_array($tag, ['thead', 'tbody', 'tfoot'], true)) {
                foreach (self::collectTableRows($child, $profile) as $row) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    /**
     * Envuelve texto con un marcador inline de Markdown.
     *
     * @param string $text Texto a envolver.
     * @param string $marker Marcador markdown (ej: **, *, _, ~~).
     * @return string Texto envuelto.
     */
    private static function wrapInline(string $text, string $marker): string
    {
        if ($text === '') return '';
        return $marker . $text . $marker;
    }

    /**
     * Normaliza salida Markdown:
     * - saltos de linea consistentes
     * - limpieza de espacios sobrantes
     * - preserva bloques de codigo
     *
     * @param string $text Markdown crudo.
     * @return string Markdown normalizado.
     */
    private static function normalizeMarkdownOutput(string $text): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", (string)$text);
        $normalized = str_replace("\u{00A0}", ' ', $normalized);
        $parts = preg_split('/(```[\s\S]*?```)/u', $normalized, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

        foreach ($parts as $index => $part) {
            if ($part === '') continue;
            if (str_starts_with($part, '```')) {
                $parts[$index] = trim($part, "\n");
                continue;
            }
            $part = (string)preg_replace("/[ \t]+\n/u", "\n", $part);
            $part = (string)preg_replace("/\n{3,}/u", "\n\n", $part);
            $parts[$index] = $part;
        }

        $normalized = implode('', $parts);
        $normalized = (string)preg_replace("/\n{3,}/u", "\n\n", $normalized);
        return trim($normalized);
    }

    /**
     * Convierte Markdown del chat a HTML seguro.
     *
     * Soporta bloques, encabezados, listas, tablas, citas, links e imagenes.
     *
     * @param string $markdown Markdown del chat.
     * @return string HTML renderizado.
     */
    public static function chatMarkdownToHtml(string $markdown): string
    {
        $source = str_replace(["\r\n", "\r"], "\n", (string)$markdown);
        $source = trim($source);
        if ($source === '') return '';

        $lines = explode("\n", $source);
        $count = count($lines);
        $i = 0;
        $chunks = [];
        $paragraphLines = [];

        $flushParagraph = static function () use (&$paragraphLines, &$chunks): void {
            if (empty($paragraphLines)) return;
            foreach ($paragraphLines as $paragraphLine) {
                $line = (string)$paragraphLine;
                if (trim($line) === '') continue;
                $chunks[] = '<p>' . self::chatInlineToHtml($line) . '</p>';
            }
            $paragraphLines = [];
        };

        while ($i < $count) {
            $line = (string)$lines[$i];
            $trimmed = trim($line);

            if ($trimmed === '') {
                $flushParagraph();
                $i++;
                continue;
            }

            if (preg_match('/^```([a-zA-Z0-9_+\-]*)\s*$/', $trimmed, $m) === 1) {
                $flushParagraph();
                $lang = preg_replace('/[^a-zA-Z0-9_+\-]/', '', (string)($m[1] ?? ''));
                $i++;
                $codeLines = [];
                while ($i < $count) {
                    $codeLine = (string)$lines[$i];
                    if (preg_match('/^```/', trim($codeLine)) === 1) {
                        $i++;
                        break;
                    }
                    $codeLines[] = $codeLine;
                    $i++;
                }
                $classAttr = $lang !== '' ? ' class="language-' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '"' : '';
                $chunks[] = '<pre><code' . $classAttr . '>' . htmlspecialchars(implode("\n", $codeLines), ENT_QUOTES, 'UTF-8') . '</code></pre>';
                continue;
            }

            if (preg_match('/^ {0,3}(#{1,6})\s+(.+?)\s*#*\s*$/u', $line, $m) === 1) {
                $flushParagraph();
                $level = strlen((string)$m[1]);
                $chunks[] = '<h' . $level . '>' . self::chatInlineToHtml((string)$m[2]) . '</h' . $level . '>';
                $i++;
                continue;
            }

            if (preg_match('/^ {0,3}(?:\*{3,}|-{3,}|_{3,})\s*$/', $trimmed) === 1) {
                $flushParagraph();
                $chunks[] = '<hr>';
                $i++;
                continue;
            }

            if (preg_match('/^\s{0,3}>\s?(.*)$/u', $line) === 1) {
                $flushParagraph();
                $quoteLines = [];
                while ($i < $count) {
                    $quoteLine = (string)$lines[$i];
                    if (trim($quoteLine) === '') {
                        $quoteLines[] = '';
                        $i++;
                        continue;
                    }
                    if (preg_match('/^\s{0,3}>\s?(.*)$/u', $quoteLine, $qm) !== 1) {
                        break;
                    }
                    $quoteLines[] = (string)($qm[1] ?? '');
                    $i++;
                }
                $quoteHtml = self::chatMarkdownToHtml(implode("\n", $quoteLines));
                $chunks[] = '<blockquote>' . $quoteHtml . '</blockquote>';
                continue;
            }

            if (
                $i + 1 < $count
                && strpos($line, '|') !== false
                && preg_match('/^\s*\|?[\s:\-]+\|[\s:\-|]+\|?\s*$/', (string)$lines[$i + 1]) === 1
            ) {
                $headerCells = self::splitTableRow($line);
                $separatorCells = self::splitTableRow((string)$lines[$i + 1]);
                if (!empty($headerCells) && !empty($separatorCells)) {
                    $validSeparator = true;
                    foreach ($separatorCells as $sepCell) {
                        $candidate = trim($sepCell);
                        if ($candidate === '' || preg_match('/^:?-{3,}:?$/', $candidate) !== 1) {
                            $validSeparator = false;
                            break;
                        }
                    }

                    if ($validSeparator) {
                        $flushParagraph();
                        $i += 2;
                        $rows = [];
                        while ($i < $count) {
                            $rowLine = (string)$lines[$i];
                            if (trim($rowLine) === '' || strpos($rowLine, '|') === false) break;
                            $rows[] = self::splitTableRow($rowLine);
                            $i++;
                        }

                        $columns = max(count($headerCells), count($separatorCells));
                        $align = [];
                        for ($c = 0; $c < $columns; $c++) {
                            $sep = trim((string)($separatorCells[$c] ?? ''));
                            if (preg_match('/^:-+:\s*$/', $sep) === 1) {
                                $align[$c] = 'center';
                            } elseif (preg_match('/^-+:\s*$/', $sep) === 1) {
                                $align[$c] = 'right';
                            } elseif (preg_match('/^:-+\s*$/', $sep) === 1) {
                                $align[$c] = 'left';
                            } else {
                                $align[$c] = '';
                            }
                        }

                        $thead = [];
                        for ($c = 0; $c < $columns; $c++) {
                            $style = $align[$c] !== '' ? ' style="text-align:' . $align[$c] . ';"' : '';
                            $thead[] = '<th' . $style . '>' . self::chatInlineToHtml((string)($headerCells[$c] ?? '')) . '</th>';
                        }

                        $tbodyRows = [];
                        foreach ($rows as $rowCells) {
                            $cellsHtml = [];
                            for ($c = 0; $c < $columns; $c++) {
                                $style = $align[$c] !== '' ? ' style="text-align:' . $align[$c] . ';"' : '';
                                $cellsHtml[] = '<td' . $style . '>' . self::chatInlineToHtml((string)($rowCells[$c] ?? '')) . '</td>';
                            }
                            $tbodyRows[] = '<tr>' . implode('', $cellsHtml) . '</tr>';
                        }

                        $tableHtml = '<table><thead><tr>' . implode('', $thead) . '</tr></thead>';
                        if (!empty($tbodyRows)) {
                            $tableHtml .= '<tbody>' . implode('', $tbodyRows) . '</tbody>';
                        }
                        $tableHtml .= '</table>';
                        $chunks[] = $tableHtml;
                        continue;
                    }
                }
            }

            if (preg_match('/^\s{0,3}[-+*]\s+(.+)$/u', $line, $m) === 1) {
                $flushParagraph();
                $items = [];
                while ($i < $count && preg_match('/^\s{0,3}[-+*]\s+(.+)$/u', (string)$lines[$i], $lm) === 1) {
                    $items[] = '<li>' . self::chatInlineToHtml((string)$lm[1]) . '</li>';
                    $i++;
                }
                $chunks[] = '<ul>' . implode('', $items) . '</ul>';
                continue;
            }

            if (preg_match('/^\s{0,3}\d+\.\s+(.+)$/u', $line, $m) === 1) {
                $flushParagraph();
                $items = [];
                while ($i < $count && preg_match('/^\s{0,3}\d+\.\s+(.+)$/u', (string)$lines[$i], $lm) === 1) {
                    $items[] = '<li>' . self::chatInlineToHtml((string)$lm[1]) . '</li>';
                    $i++;
                }
                $chunks[] = '<ol>' . implode('', $items) . '</ol>';
                continue;
            }

            $paragraphLines[] = $trimmed;
            $i++;
        }

        $flushParagraph();
        return implode('', $chunks);
    }

    /**
     * Valida y sanitiza URL para evitar esquemas inseguros.
     *
     * @param string $url URL de entrada.
     * @return string URL valida o cadena vacia.
     */
    private static function safeUrl(string $url): string
    {
        $url = trim((string)$url);
        if ($url === '') return '';
        $decoded = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
        $decoded = preg_replace('/[\x00-\x1F\x7F]/u', '', (string)$decoded);
        $decoded = trim((string)$decoded);
        if ($decoded === '') return '';

        if (preg_match('/^(javascript|vbscript|data):/i', $decoded) === 1) {
            return '';
        }

        if (
            preg_match('/^(https?:\/\/|mailto:|tel:|ftp:\/\/)/i', $decoded) === 1
            || str_starts_with($decoded, '/')
            || str_starts_with($decoded, './')
            || str_starts_with($decoded, '../')
            || str_starts_with($decoded, '#')
        ) {
            return $decoded;
        }

        return '';
    }

    /**
     * Divide una fila Markdown de tabla en celdas.
     *
     * Respeta escapes de separadores \|.
     *
     * @param string $line Linea de tabla.
     * @return array Celdas parseadas.
     */
    private static function splitTableRow(string $line): array
    {
        $line = trim((string)$line);
        if ($line === '') return [];
        if (str_starts_with($line, '|')) $line = substr($line, 1);
        if (substr($line, -1) === '|') $line = substr($line, 0, -1);

        $cells = [];
        $buffer = '';
        $len = strlen($line);
        for ($i = 0; $i < $len; $i++) {
            $char = $line[$i];
            if ($char === '\\' && $i + 1 < $len && $line[$i + 1] === '|') {
                $buffer .= '|';
                $i++;
                continue;
            }
            if ($char === '|') {
                $cells[] = trim($buffer);
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        $cells[] = trim($buffer);
        return $cells;
    }

    /**
     * Convierte sintaxis inline de Markdown de chat a HTML.
     *
     * @param string $text Texto inline en Markdown.
     * @return string HTML inline.
     */
    private static function chatInlineToHtml(string $text): string
    {
        $raw = (string)$text;
        if ($raw === '') return '';

        $codeTokens = [];
        $tokenPrefix = '__CHATMD_CODE_';
        $tokenIndex = 0;

        $raw = preg_replace_callback('/`([^`\n]+)`/u', static function ($m) use (&$codeTokens, &$tokenIndex, $tokenPrefix): string {
            $token = $tokenPrefix . $tokenIndex . '__';
            $codeTokens[$token] = '<code>' . htmlspecialchars((string)$m[1], ENT_QUOTES, 'UTF-8') . '</code>';
            $tokenIndex++;
            return $token;
        }, $raw);

        $safe = htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');

        $safe = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)/u', static function ($m): string {
            $alt = (string)($m[1] ?? '');
            $url = self::safeUrl((string)($m[2] ?? ''));
            $title = (string)($m[3] ?? '');
            if ($url === '') {
                return htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
            }
            $titleAttr = $title !== '' ? ' title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"' : '';
            return '<img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"' . $titleAttr . ' loading="lazy">';
        }, $safe);

        $safe = preg_replace_callback('/\[(.*?)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)/u', static function ($m): string {
            $label = (string)($m[1] ?? '');
            $url = self::safeUrl((string)($m[2] ?? ''));
            $title = (string)($m[3] ?? '');
            if ($url === '') return $label;
            $titleAttr = $title !== '' ? ' title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"' : '';
            return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $titleAttr . ' target="_blank" rel="noopener noreferrer">' . $label . '</a>';
        }, $safe);

        $safe = preg_replace_callback('/&lt;(https?:\/\/[^&\s]+)&gt;/ui', static function ($m): string {
            $url = self::safeUrl((string)($m[1] ?? ''));
            if ($url === '') return (string)($m[1] ?? '');
            $esc = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            return '<a href="' . $esc . '" target="_blank" rel="noopener noreferrer">' . $esc . '</a>';
        }, $safe);

        $safe = preg_replace_callback('/(?<!["\'=])(https?:\/\/[^\s<]+)/ui', static function ($m): string {
            $url = self::safeUrl((string)($m[1] ?? ''));
            if ($url === '') return (string)($m[1] ?? '');
            $esc = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            return '<a href="' . $esc . '" target="_blank" rel="noopener noreferrer">' . $esc . '</a>';
        }, $safe);

        $safe = preg_replace('/~~(?=\S)(.+?)(?<=\S)~~/u', '<del>$1</del>', $safe);
        $safe = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/u', '<strong>$1</strong>', $safe);
        $safe = preg_replace('/(?<!\*)\*(?=\S)(.+?)(?<=\S)\*(?!\*)/u', '<em>$1</em>', $safe);

        if (!empty($codeTokens)) {
            $safe = str_replace(array_keys($codeTokens), array_values($codeTokens), $safe);
        }

        return (string)$safe;
    }
}
