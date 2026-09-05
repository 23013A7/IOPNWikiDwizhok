<?php
function plugin_manifest_40_Links_Images() {
    return [
        'name'        => 'Ссылки и изображения',
        'description' => 'Внутренние [[ссылки]] и [[File:изображения]]',
        'version'     => '1.0.0',
        'author'      => 'ИОПН',
    ];
}

RuleRegistry::add('ВнешняяСсылка', [
    'Тип'             => 'Строка',
    'Элемент'         => '[',
    'КонечныйЭлемент' => ']',
    'Приоритет'       => 60,
    'Обработчик'      => function(array $args, $rawText, $rule) {
        $parts = _li_split($rawText, ' ', 2);
        $url   = isset($parts[0]) ? trim($parts[0]) : '';
        $text  = isset($parts[1]) ? trim($parts[1]) : $url;
        
        if (!_li_is_url($url)) {
            return '[' . htmlspecialchars($rawText, ENT_QUOTES, 'UTF-8') . ']';
        }
        if ($text === '') $text = $url;
        
        $safeUrl  = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $safeText = _li_parse_inline($text);
        return '<a href="' . $safeUrl . '">' . $safeText . '</a>';
    },
]);

RuleRegistry::add('ВнутренняяСсылкаИзображение', [
    'Тип'             => 'Строка',
    'Элемент'         => '[[',
    'КонечныйЭлемент' => ']]',
    'Приоритет'       => 40,
    'Обработчик'      => function(array $args, $rawText, $rule) {
        $parts = _li_split($rawText, '|', -1);
        $first = isset($parts[0]) ? trim($parts[0]) : '';

        $imgPrefixes = ['File:', 'file:', 'Файл:', 'файл:', 'img:', 'Img:', 'IMAGE:', 'image:'];
        $isImage = false;
        $imgSrc  = '';
        
        foreach ($imgPrefixes as $p) {
            if (strncasecmp($first, $p, strlen($p)) === 0) {
                $imgSrc  = substr($first, strlen($p));
                $isImage = true;
                break;
            }
        }

        if ($isImage) {
            return _li_render_image($imgSrc, array_slice($parts, 1));
        } else {
            return _li_render_link($first, array_slice($parts, 1));
        }
    },
]);

function _li_split($text, $sep, $max) {
    $parts   = [];
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

        if ($depth === 0 && $sepLen > 0 && substr($text, $i, $sepLen) === $sep) {
            $parts[] = $current;
            $current = '';
            $i += $sepLen;
            
            if ($max !== -1 && count($parts) >= $max) {
                $current = substr($text, $i);
                $i = $len;
            }
            continue;
        }

        $current .= $text[$i];
        $i++;
    }
    $parts[] = $current;
    return $parts;
}

function _li_is_url($str) {
    $str = ltrim($str);
    $protocols = ['http://', 'https://', 'ftp://', 'ftps://', 'news://', 'irc://'];
    foreach ($protocols as $p) {
        if (strncasecmp($str, $p, strlen($p)) === 0) return true;
    }
    return false;
}

function _li_parse_inline($text) {
    if (trim($text) === '') return '';
    static $p = null;
    if ($p === null && class_exists('Parser')) $p = new Parser();
    if ($p !== null) {
        $html = $p->parse($text);
        $html = trim($html);
        if (strncmp($html, '<p>', 3) === 0 && substr($html, -4) === '</p>') {
            $html = substr($html, 3, strlen($html) - 7);
        }
        return $html;
    }
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function _li_render_link($page, $restParts) {
    $page = trim($page);
    $text = isset($restParts[0]) ? trim($restParts[0]) : '';
    if ($text === '') $text = $page;

    $safeHref = '?Page=' . rawurlencode($page);
    $safeText = _li_parse_inline($text);
    return '<a href="' . $safeHref . '">' . $safeText . '</a>';
}

function _li_render_image($src, $params) {
    $src = trim($src);

    $alignment = '';
    $width     = null;
    $isMini    = false;
    $caption   = '';

    $alignMap  = [
        'слева' => 'left',  'left'   => 'left',
        'справа' => 'right', 'right'  => 'right',
        'центр' => 'center', 'center' => 'center',
    ];
    $miniWords = ['мини', 'mini', 'thumb'];

    foreach ($params as $param) {
        $p = strtolower(trim($param));
        if (isset($alignMap[$p])) {
            $alignment = $alignMap[$p];
        } elseif (in_array($p, $miniWords)) {
            $isMini = true;
        } elseif (strlen($p) > 2 && substr($p, -2) === 'px' && is_numeric(substr($p, 0, -2))) {
            $width = (int) substr($p, 0, -2);
        } else {
            $caption = trim($param);
        }
    }

    $safeUrl  = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
    $altClean = $caption;
    $altClean = preg_replace('/\[\[([^\]|]*\|)?([^\]]*)\]\]/', '$2', $altClean);
    $altClean = preg_replace('/\{\{[^}]*\}\}/', '', $altClean);
    $altText  = htmlspecialchars(trim(strip_tags($altClean)), ENT_QUOTES, 'UTF-8');
    $imgAttrs = 'src="' . $safeUrl . '" alt="' . $altText . '"';
    
    if ($width) $imgAttrs .= ' width="' . $width . '"';

    if (!$alignment && !$isMini && $caption === '') {
        return '<img ' . $imgAttrs . '/>';
    }

    $classes  = ['image'];
    if ($alignment) $classes[] = 'image-' . $alignment;
    if ($isMini)    $classes[] = 'image-mini';

    $html  = '<div class="' . implode(' ', $classes) . '">';
    $html .= '<img ' . $imgAttrs . '/>';
    if ($caption !== '') {
        $html .= '<p class="image-description">' . _li_parse_inline($caption) . '</p>';
    }
    $html .= '</div>';
    return $html;
}

HookManager::register('editor_buttons', function($buttons, $context) {
    $buttons[] = array('id' => 'internal-link', 'label' => 'Ссылка', 'title' => 'Вставить внутреннюю ссылку', 'insert' => '[[{{selection}}]]');
    $buttons[] = array('id' => 'image', 'label' => 'Изображение', 'title' => 'Вставить изображение', 'insert' => '[[File:имя-файла.jpg]]');
    return $buttons;
}, 40);
