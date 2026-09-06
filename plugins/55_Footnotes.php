<?php
function plugin_manifest_55_Footnotes() {
    return array(
        'name'        => 'Примечания',
        'description' => 'MediaWiki-подобные <ref> и <references /> с автоматической нумерацией, именованными примечаниями и группами',
        'version'     => '1.0.0',
        'author'      => 'ИОПН',
        'priority'    => 55,
    );
}

$_iopn_footnotes_stack = array();

function _iopn_footnotes_new_context() {
    return array(
        'groups'       => array(),
        'order'        => array(),
        'definitions'  => array(),
        'references'   => array(),
        'marker'       => 0,
        'group_counts' => array(),
        'placeholder'  => '__IOPN_FOOTNOTE_%d__',
    );
}

function _iopn_footnotes_key($group, $name) {
    return $group . "\x1F" . $name;
}

function _iopn_footnotes_add_reference(&$ctx, $group, $name, $body) {
    $group = trim((string)$group);
    $name  = trim((string)$name);

    if ($name !== '') {
        $key = _iopn_footnotes_key($group, $name);

        if (isset($ctx['definitions'][$key])) {
            if ($ctx['definitions'][$key]['body'] === '' && $body !== '') {
                $ctx['definitions'][$key]['body'] = (string)$body;
            }
            $number = $ctx['definitions'][$key]['number'];
        } else {
            if (!isset($ctx['group_counts'][$group])) $ctx['group_counts'][$group] = 0;
            $ctx['group_counts'][$group]++;
            $number = $ctx['group_counts'][$group];
            $ctx['definitions'][$key] = array(
                'number' => $number,
                'body'   => (string)$body,
                'group'  => $group,
                'name'   => $name,
            );
            $ctx['order'][] = $key;
        }
    } else {
        if (!isset($ctx['group_counts'][$group])) $ctx['group_counts'][$group] = 0;
        $ctx['group_counts'][$group]++;
        $number = $ctx['group_counts'][$group];
        $key = '__anonymous_' . $group . '_' . $number;
        $ctx['definitions'][$key] = array(
            'number' => $number,
            'body'   => (string)$body,
            'group'  => $group,
            'name'   => '',
        );
        $ctx['order'][] = $key;
    }

    if (!isset($ctx['references'][$key])) {
        $ctx['references'][$key] = array();
    }

    $occurrence = count($ctx['references'][$key]) + 1;
    $ctx['references'][$key][] = $occurrence;

    return array($key, $number, $occurrence);
}

function _iopn_footnotes_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function _iopn_footnotes_ref_html($number, $key, $occurrence) {
    $safeKey = 'fn-' . md5((string)$key);

    $refId  = 'cite_ref-' . $safeKey . '_' . $occurrence;
    $noteId = 'cite_note-' . $safeKey;

    return '<sup class="reference"><a href="#' . _iopn_footnotes_escape($noteId) . '" id="' . _iopn_footnotes_escape($refId) . '">' . (int)$number . '</a></sup>';
}

function _iopn_footnotes_parse_body($body) {
    $body = trim((string)$body);
    if ($body === '') return '';

    if (class_exists('Parser')) {
        $parser = new Parser();
        $html = $parser->parse($body);
        if ($html === false || $html === null) return '';
        $html = trim($html);

        if (strncmp($html, '<p>', 3) === 0 && substr($html, -4) === '</p>') {
            $html = substr($html, 3, strlen($html) - 7);
        }
        return $html;
    }

    return _iopn_footnotes_escape($body);
}

function _iopn_footnotes_render_list($ctx, $group = '') {
    $group = trim((string)$group);
    $items = array();

    foreach ($ctx['order'] as $key) {
        if (!isset($ctx['definitions'][$key])) continue;
        $def = $ctx['definitions'][$key];
        if ($def['group'] !== $group) continue;

        $number = $def['number'];
        $safeKey = 'fn-' . md5((string)$key);
        $noteId = 'cite_note-' . $safeKey;

        $body = _iopn_footnotes_parse_body($def['body']);
        if ($body === '') $body = '<span class="reference-empty">Пустое примечание</span>';

        $backlinks = '';
        if (isset($ctx['references'][$key])) {
            foreach ($ctx['references'][$key] as $occurrence) {
                $refId = 'cite_ref-' . $safeKey . '_' . $occurrence;
                $backlinks .= ' <a class="mw-cite-backlink" href="#' . _iopn_footnotes_escape($refId) . '">↑</a>';
            }
        }

        $items[] = '<li id="' . _iopn_footnotes_escape($noteId) . '">' . $body . $backlinks . '</li>';
    }

    if (empty($items)) return '';

    return "<ol class=\"references\">\n" . implode("\n", $items) . "\n</ol>\n";
}

HookManager::register('parse_before', function($text) {
    global $_iopn_footnotes_stack;

    $ctx = _iopn_footnotes_new_context();
    $_iopn_footnotes_stack[] = $ctx;
    $index = count($_iopn_footnotes_stack) - 1;

    $text = preg_replace_callback(
        '~<ref\b([^>]*)/\s*>~isu',
        function($m) use (&$index) {
            global $_iopn_footnotes_stack;
            $attrs = isset($m[1]) ? $m[1] : '';
            $name = '';
            $group = '';

            if (preg_match('~\bname\s*=\s*["\']([^"\']+)["\']~iu', $attrs, $a)) $name = trim($a[1]);
            if (preg_match('~\bgroup\s*=\s*["\']([^"\']+)["\']~iu', $attrs, $a)) $group = trim($a[1]);

            $result = _iopn_footnotes_add_reference($_iopn_footnotes_stack[$index], $group, $name, '');
            return sprintf($_iopn_footnotes_stack[$index]['placeholder'], $result[1]) . ' ';
        },
        $text
    );

    $text = preg_replace_callback(
        '~<ref\b([^>]*)>(.*?)</ref\s*>~isu',
        function($m) use (&$index) {
            global $_iopn_footnotes_stack;
            $attrs = isset($m[1]) ? $m[1] : '';
            $body = isset($m[2]) ? $m[2] : '';
            $name = '';
            $group = '';

            if (preg_match('~\bname\s*=\s*["\']([^"\']+)["\']~iu', $attrs, $a)) $name = trim($a[1]);
            if (preg_match('~\bgroup\s*=\s*["\']([^"\']+)["\']~iu', $attrs, $a)) $group = trim($a[1]);

            $result = _iopn_footnotes_add_reference($_iopn_footnotes_stack[$index], $group, $name, $body);
            return sprintf($_iopn_footnotes_stack[$index]['placeholder'], $result[1]) . ' ';
        },
        $text
    );

    $text = preg_replace_callback(
        '~<references\b([^>]*)>(?:.*?)</references\s*>|<references\b([^>]*)/\s*>~isu',
        function($m) use (&$index) {
            global $_iopn_footnotes_stack;
            $attrs = '';
            if (isset($m[1]) && $m[1] !== '') $attrs = $m[1];
            elseif (isset($m[2])) $attrs = $m[2];

            $group = '';
            if (preg_match('~\bgroup\s*=\s*["\']([^"\']+)["\']~iu', $attrs, $a)) $group = trim($a[1]);

            $marker = '__IOPN_REFERENCES_' . md5($group . '|' . count($_iopn_footnotes_stack[$index]['order']) . '|' . count($_iopn_footnotes_stack[$index]['references'])) . '__';
            $_iopn_footnotes_stack[$index]['groups'][$marker] = $group;
            return "\n\n" . $marker . "\n\n";
        },
        $text
    );

    return $text;
}, 5);

HookManager::register('parse_after', function($html) {
    global $_iopn_footnotes_stack;

    if (empty($_iopn_footnotes_stack)) return $html;
    $ctx = array_pop($_iopn_footnotes_stack);

    foreach ($ctx['order'] as $key) {
        if (!isset($ctx['definitions'][$key])) continue;
        $def = $ctx['definitions'][$key];
        $number = $def['number'];

        $safeKey = 'fn-' . md5((string)$key);
        $placeholder = sprintf($ctx['placeholder'], $number);

        $occurrenceCount = isset($ctx['references'][$key]) ? count($ctx['references'][$key]) : 1;
        $replacement = '';
        for ($i = 1; $i <= $occurrenceCount; $i++) {
            $pos = strpos($html, $placeholder);
            if ($pos === false) break;
            $replacement = _iopn_footnotes_ref_html($number, $key, $i);
            $html = substr_replace($html, $replacement, $pos, strlen($placeholder));
        }
    }

    foreach ($ctx['groups'] as $marker => $group) {
        $list = _iopn_footnotes_render_list($ctx, $group);

        $html = str_replace('<p>' . $marker . '</p>', $list, $html);
        $html = str_replace($marker, $list, $html);
    }

    return $html;
}, 90);

HookManager::register('parser_allowed_attrs', function($attrs) {
    foreach (array('a', 'sup', 'li') as $tag) {
        if (!isset($attrs[$tag])) $attrs[$tag] = array();
        if (!in_array('id', $attrs[$tag])) $attrs[$tag][] = 'id';
    }
    return $attrs;
}, 55);

HookManager::register('editor_buttons', function($buttons, $context) {
    $buttons[] = array(
        'id'     => 'footnote',
        'label'  => 'Примечание',
        'title'  => 'Вставить примечание <ref>',
        'insert' => '<ref>{{selection}}</ref>',
    );
    $buttons[] = array(
        'id'     => 'references',
        'label'  => 'Примечания',
        'title'  => 'Вставить список примечаний',
        'insert' => '<references />',
    );
    return $buttons;
}, 55);
