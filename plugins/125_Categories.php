<?php
function plugin_manifest_125_Categories() {
    return array(
        'name'        => 'Категории',
        'description' => 'Категории страниц в стиле MediaWiki с индексированием в metadata',
        'version'     => '1.0.0',
        'author'      => 'ИОПН',
        'priority'    => 125,
    );
}

NamespaceRegistry::register('Категория', array(
    'aliases' => array('Category', 'Кат'),
));

HookManager::register('wiki_init', function($data) {
    PageIndex::refreshNamespaces();
}, 125);

HookManager::register('page_content', function($input, $context) {
    if (!is_array($context)) return $input;
    if (isset($context['namespace']) && $context['namespace'] === 'Категория'
        && isset($context['pagename']) && trim((string)$context['pagename']) !== ''
        && trim((string)$input) === '') {
        return ' ';
    }
    return $input;
}, 125);

function iopn_category_normalize($name) {
    $name = trim((string)$name);
    $name = preg_replace('/\\s+/u', ' ', $name);
    return trim($name);
}

function iopn_category_split_target($target) {
    $parts = explode('|', (string)$target, 2);
    $name = iopn_category_normalize(isset($parts[0]) ? $parts[0] : '');
    $sort = isset($parts[1]) ? trim($parts[1]) : '';
    return array($name, $sort);
}

function iopn_categories_extract($text) {
    $result = array();
    $pattern = '/\\[\\[\\s*(?:Категория|Category|Кат)\\s*:\\s*([^\\]]+)\\]\\]/ui';

    if (!preg_match_all($pattern, (string)$text, $matches)) {
        return $result;
    }

    foreach ($matches[1] as $raw) {
        list($name, $sort) = iopn_category_split_target($raw);
        if ($name === '') continue;

        $key = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
        if (!isset($result[$key])) {
            $result[$key] = array(
                'name' => $name,
                'sort' => $sort,
            );
        } elseif ($result[$key]['sort'] === '' && $sort !== '') {
            $result[$key]['sort'] = $sort;
        }
    }

    return array_values($result);
}

function iopn_category_page_name($page) {
    $parts = PageIndex::splitPage($page);
    if ($parts['namespace'] !== 'Категория') return '';
    return $parts['name'];
}

function iopn_category_read_page_body($page, $data = array()) {
    $parts = PageIndex::splitPage($page);
    if ($parts['full'] === '') return '';

    $pagesDir = dirname(PageIndex::getFile());
    $filename = $parts['name'] . '.iopnwiki';
    $file = $parts['namespace'] !== ''
        ? $pagesDir . '/' . $parts['namespace'] . '/' . $filename
        : $pagesDir . '/' . $filename;

    if (!is_file($file)) return '';
    $body = @file($file, FILE_IGNORE_NEW_LINES);
    if (!is_array($body) || count($body) < 2) return '';
    array_shift($body);
    return implode("\n", $body);
}

function iopn_category_members($category) {
    $category = iopn_category_normalize($category);
    if ($category === '') return array();

    $needle = function_exists('mb_strtolower') ? mb_strtolower($category, 'UTF-8') : strtolower($category);
    $result = array();
    $pages = PageIndex::all();

    foreach ($pages as $page => $data) {
        $entries = (isset($data['meta']['categories']) && is_array($data['meta']['categories']))
            ? $data['meta']['categories'] : array();

        if (empty($entries)) {
            $body = iopn_category_read_page_body($page, $data);
            if ($body !== '') $entries = iopn_categories_extract($body);
        }

        foreach ($entries as $entry) {
            $name = is_array($entry) && isset($entry['name']) ? $entry['name'] : $entry;
            $name = iopn_category_normalize($name);
            $key = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
            if ($key === $needle) {
                $sort = is_array($entry) && isset($entry['sort']) ? trim($entry['sort']) : '';
                $result[$page] = array(
                    'page' => $page,
                    'name' => isset($data['name']) ? $data['name'] : $page,
                    'namespace' => isset($data['namespace']) ? $data['namespace'] : '',
                    'sort' => $sort,
                );
                break;
            }
        }
    }

    uasort($result, function($a, $b) {
        $as = $a['sort'] !== '' ? $a['sort'] : $a['name'];
        $bs = $b['sort'] !== '' ? $b['sort'] : $b['name'];
        $cmp = strcasecmp($as, $bs);
        if ($cmp !== 0) return $cmp;
        return strcasecmp($a['page'], $b['page']);
    });

    return $result;
}

function iopn_categories_all() {
    $categories = array();
    $pages = PageIndex::all();

    foreach ($pages as $page => $data) {
        if (!isset($data['meta']['categories']) || !is_array($data['meta']['categories'])) continue;
        foreach ($data['meta']['categories'] as $entry) {
            $name = is_array($entry) && isset($entry['name']) ? $entry['name'] : $entry;
            $name = iopn_category_normalize($name);
            if ($name === '') continue;
            $key = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
            if (!isset($categories[$key])) {
                $categories[$key] = array('name' => $name, 'count' => 0);
            }
            $categories[$key]['count']++;
        }
    }

    uasort($categories, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    return $categories;
}

HookManager::register('editor_before_save', function($data) {
    if (!is_array($data)) return $data;
    $body = isset($data['body']) ? (string)$data['body'] : '';
    $categories = iopn_categories_extract($body);
    if (empty($categories)) {
        unset($data['meta']['categories']);
    } else {
        $data['meta']['categories'] = $categories;
    }
    return $data;
}, 20);

HookManager::register('parse_before', function($text) {
    if (!isset($GLOBALS['_iopn_category_parse_depth'])) $GLOBALS['_iopn_category_parse_depth'] = 0;
    $GLOBALS['_iopn_category_parse_depth']++;

    $pattern = '/\\[\\[\\s*(?:Категория|Category|Кат)\\s*:\\s*([^\\]]+)\\]\\]/ui';
    return preg_replace_callback($pattern, function($m) {
        static $counter = 0;
        $counter++;
        return 'IOPNCATMARK' . $counter . 'X';
    }, (string)$text);
}, 15);

HookManager::register('parse_after', function($html) {
    if (!isset($GLOBALS['_iopn_category_parse_depth'])) $GLOBALS['_iopn_category_parse_depth'] = 1;
    $depth = $GLOBALS['_iopn_category_parse_depth'];
    $GLOBALS['_iopn_category_parse_depth'] = max(0, $depth - 1);

    $html = preg_replace('/IOPNCATMARK[0-9]+X/', '', (string)$html);

    if ($depth > 1) return $html;

    $page = isset($_GET['Page']) ? (string)$_GET['Page'] : '';
    $parts = PageIndex::splitPage($page);

    if ($parts['namespace'] === 'Категория' && $parts['name'] !== '') {
        $members = iopn_category_members($parts['name']);
        $block = '<p>Количество: <strong>' . count($members) . '</strong></p>';
        if (!empty($members)) {
            $block .= '<ul>';
            foreach ($members as $member) {
                $href = '?Page=' . rawurlencode($member['page']);
                $label = $member['page'];
                $block .= '<li><a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a></li>';
            }
            $block .= '</ul>';
        } else {
            $block .= '<p>В этой категории пока нет страниц.</p>';
        }
        return (string)$html . "\n" . $block;
    }

    $data = PageIndex::get($parts['full']);
    $entries = ($data && isset($data['meta']['categories']) && is_array($data['meta']['categories']))
        ? $data['meta']['categories'] : array();

    if (empty($entries)) return $html;

    $links = array();
    foreach ($entries as $entry) {
        $name = is_array($entry) && isset($entry['name']) ? $entry['name'] : $entry;
        $name = iopn_category_normalize($name);
        if ($name === '') continue;
        $href = '?Page=' . rawurlencode('Категория:' . $name);
        $links[] = '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</a>';
    }

    if (empty($links)) return $html;

    $block = '<div class="iopn-page-categories"><span class="iopn-page-categories-title">Категории:</span> ' . implode(', ', $links) . '</div>';
    return (string)$html . "\n" . $block;
}, 90);

if (class_exists('SpecialPageRegistry')) {
    SpecialPageRegistry::register('Категории', function($context) {
        $categories = iopn_categories_all();
        $html = '<p>Всего категорий: <strong>' . count($categories) . '</strong></p>';

        if (empty($categories)) {
            return $html . '<p>Категорий пока нет.</p>';
        }

        $html .= '<table class="wikitable">';
        $html .= '<thead><tr><th>Категория</th><th>Страниц</th></tr></thead><tbody>';
        foreach ($categories as $category) {
            $href = '?Page=' . rawurlencode('Категория:' . $category['name']);
            $html .= '<tr><td><a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') . '</a></td>';
            $html .= '<td>' . (int)$category['count'] . '</td></tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }, array(
        'aliases' => array('Список категорий', 'Categories'),
        'description' => 'Список всех категорий и количества страниц в них',
        'priority' => 50,
    ));
}

HookManager::register('editor_buttons', function($buttons, $context) {
    $buttons[] = array(
        'id' => 'category',
        'label' => 'Категория',
        'title' => 'Добавить категорию',
        'insert' => '[[Категория:Название]]',
    );
    return $buttons;
}, 125);
