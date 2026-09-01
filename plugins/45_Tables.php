<?php
function plugin_manifest_45_Tables() {
    return [
        'name'        => 'Таблицы',
        'description' => 'Таблицы в синтаксисе MediaWiki: {| ... |}',
        'version'     => '1.0.0',
        'author'      => 'ИОПН',
    ];
}

RuleRegistry::add('Таблица', [
    'Тип'             => 'МногострочныйБлок',
    'Элемент'         => '{|',
    'КонечныйЭлемент' => '|}',
    'Результат'       => '',
    'Приоритет'       => 45,
    'Обработчик'      => function(array $args, $rawText, $rule) {
        return _table_parse($rawText);
    },
]);

HookManager::register('parser_allowed_tags', function($tags) {
    $extra = array('table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'col', 'colgroup');
    foreach ($extra as $tag) {
        if (!in_array($tag, $tags)) $tags[] = $tag;
    }
    return $tags;
});

HookManager::register('parser_allowed_attrs', function($attrs) {
    $attrs['table']    = array('class', 'style', 'border', 'cellpadding', 'cellspacing', 'width', 'summary');
    $attrs['th']       = array('class', 'style', 'colspan', 'rowspan', 'scope', 'width', 'align');
    $attrs['td']       = array('class', 'style', 'colspan', 'rowspan', 'width', 'align', 'valign');
    $attrs['tr']       = array('class', 'style', 'align', 'valign');
    $attrs['col']      = array('class', 'style', 'span', 'width');
    $attrs['colgroup'] = array('class', 'style', 'span');
    $attrs['caption']  = array('class', 'style');
    return $attrs;
});

function _table_parse($rawText) {
    $lines   = explode("\n", $rawText);
    $html    = '';
    $caption = '';
    $tbody   = '';
    $inRow   = false;
    $rowHtml = '';

    $tableAttrs = '';
    $firstLine  = isset($lines[0]) ? trim($lines[0]) : '';
    if ($firstLine !== '') {
        $tableAttrs = ' ' . $firstLine;
    }
    $startIdx = 1;

    for ($i = $startIdx; $i < count($lines); $i++) {
        $line = $lines[$i];
        $trimmed = ltrim($line);

        if ($trimmed === '') continue;

        $first = isset($trimmed[0]) ? $trimmed[0] : '';
        $second = isset($trimmed[1]) ? $trimmed[1] : '';

        if ($first === '|' && $second === '+') {
            $caption = trim(substr($trimmed, 2));
            continue;
        }

        if ($first === '|' && $second === '-') {
            if ($inRow) {
                $tbody .= '<tr>' . $rowHtml . '</tr>' . "\n";
                $rowHtml = '';
            }
            $rowAttrs = trim(substr($trimmed, 2));
            $inRow = true;
            continue;
        }

        if ($first === '!') {
            if (!$inRow) { $inRow = true; }
            $cellsRaw = substr($trimmed, 1);
            $cells = _table_split_cells($cellsRaw, '!!');
            foreach ($cells as $cell) {
                $rowHtml .= _table_render_cell($cell, 'th');
            }
            continue;
        }

        if ($first === '|') {
            if (!$inRow) { $inRow = true; }
            $cellsRaw = substr($trimmed, 1);
            $cells = _table_split_cells($cellsRaw, '||');
            foreach ($cells as $cell) {
                $rowHtml .= _table_render_cell($cell, 'td');
            }
            continue;
        }

        $rowHtml .= htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8');
    }

    if ($inRow && $rowHtml !== '') {
        $tbody .= '<tr>' . $rowHtml . '</tr>' . "\n";
    }

    $html = '<table' . $tableAttrs . '>' . "\n";
    if ($caption !== '') {
        $html .= '<caption>' . _table_parse_inline($caption) . '</caption>' . "\n";
    }
    if ($tbody !== '') {
        $html .= '<tbody>' . "\n" . $tbody . '</tbody>' . "\n";
    }
    $html .= '</table>' . "\n";

    return $html;
}

function _table_split_cells($text, $sep) {
    $cells   = array();
    $current = '';
    $depth   = 0;
    $len     = strlen($text);
    $sepLen  = strlen($sep);
    $i       = 0;

    while ($i < $len) {
        if ($i + 1 < $len) {
            if (($text[$i] === '[' && $text[$i+1] === '[') ||
                ($text[$i] === '{' && $text[$i+1] === '{')) {
                $depth++;
                $current .= $text[$i] . $text[$i+1];
                $i += 2;
                continue;
            }
            if (($text[$i] === ']' && $text[$i+1] === ']') ||
                ($text[$i] === '}' && $text[$i+1] === '}')) {
                if ($depth > 0) $depth--;
                $current .= $text[$i] . $text[$i+1];
                $i += 2;
                continue;
            }
        }

        if ($depth === 0 && substr($text, $i, $sepLen) === $sep) {
            $cells[] = $current;
            $current = '';
            $i += $sepLen;
            continue;
        }

        $current .= $text[$i];
        $i++;
    }
    $cells[] = $current;
    return $cells;
}

function _table_render_cell($raw, $tag) {
    $raw   = trim($raw);
    $attrs = '';
    $content = $raw;

    $pipePos = _table_find_attr_pipe($raw);
    if ($pipePos !== false) {
        $attrs   = ' ' . trim(substr($raw, 0, $pipePos));
        $content = trim(substr($raw, $pipePos + 1));
    }

    $inner = _table_parse_inline($content);
    return '<' . $tag . $attrs . '>' . $inner . '</' . $tag . '>' . "\n";
}

function _table_find_attr_pipe($text) {
    $len   = strlen($text);
    $depth = 0;
    $i     = 0;

    while ($i < $len) {
        if ($i + 1 < $len) {
            if (($text[$i] === '[' && $text[$i+1] === '[') ||
                ($text[$i] === '{' && $text[$i+1] === '{')) {
                $depth++;
                $i += 2;
                continue;
            }
            if (($text[$i] === ']' && $text[$i+1] === ']') ||
                ($text[$i] === '}' && $text[$i+1] === '}')) {
                if ($depth > 0) $depth--;
                $i += 2;
                continue;
            }
            if ($depth === 0 && $text[$i] === '|' && $text[$i+1] === '|') {
                return false;
            }
        }
        if ($depth === 0 && $text[$i] === '|') {
            return $i;
        }
        $i++;
    }
    return false;
}

function _table_parse_inline($text) {
    if (empty(trim($text))) return '';
    static $tableParser = null;
    if ($tableParser === null && class_exists('Parser')) {
        $tableParser = new Parser();
    }
    if ($tableParser !== null) {
        $html = $tableParser->parse($text);
        $html = trim($html);
        if (strncmp($html, '<p>', 3) === 0 && substr($html, -4) === '</p>') {
            $html = substr($html, 3, strlen($html) - 7);
        }
        return $html;
    }
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
