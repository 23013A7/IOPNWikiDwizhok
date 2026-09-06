<?php
function plugin_manifest_120_SpecialPages() {
    return array(
        'name'        => 'Специальные страницы',
        'description' => 'Все страницы, плагины, поиск и обслуживание индекса',
        'version'     => '1.0.0',
        'author'      => 'ИОПН',
        'priority'    => 120,
    );
}

function iopn_special_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function iopn_special_page_url($page) {
    return '?Page=' . rawurlencode('Служебная:' . $page);
}

function iopn_special_page_link($page, $label = null) {
    if ($label === null) $label = $page;
    return '<a href="' . iopn_special_h(iopn_special_page_url($page)) . '">' . iopn_special_h($label) . '</a>';
}

SpecialPageRegistry::register('Все страницы', function($context) {
    $pages = PageIndex::all();
    $rows = array();

    foreach ($pages as $full => $data) {
        $name = isset($data['name']) ? $data['name'] : $full;
        $namespace = isset($data['namespace']) ? $data['namespace'] : '';
        $meta = isset($data['meta']) && is_array($data['meta']) ? $data['meta'] : array();
        $rows[] = array($full, $name, $namespace, $meta);
    }

    usort($rows, function($a, $b) {
        return strcasecmp($a[0], $b[0]);
    });

    $html  = '<h2>Все страницы</h2>';
    $html .= '<p>Всего страниц: <strong>' . count($rows) . '</strong></p>';
    $html .= '<table class="article-table">';
    $html .= '<thead><tr><th>Страница</th><th>Имя</th><th>Пространство имён</th></tr></thead><tbody>';

    foreach ($rows as $row) {
        $full = $row[0];
        $namespace = $row[2] === '' ? 'Основное' : $row[2];
        $href = '?Page=' . rawurlencode($full);
        $html .= '<tr>';
        $html .= '<td><a href="' . iopn_special_h($href) . '">' . iopn_special_h($full) . '</a></td>';
        $html .= '<td>' . iopn_special_h($row[1]) . '</td>';
        $html .= '<td>' . iopn_special_h($namespace) . '</td>';
        $html .= '</tr>';
    }

    if (empty($rows)) {
        $html .= '<tr><td colspan="3">В индексе пока нет страниц.</td></tr>';
    }

    $html .= '</tbody></table>';
    return $html;
}, array(
    'description' => 'Список всех страниц, сохранённых в глобальном индексе',
    'priority' => 10,
));

SpecialPageRegistry::register('Плагины', function($context) {
    $loaded  = PluginLoader::getLoaded();
    $skipped = PluginLoader::getSkipped();
    $failed  = PluginLoader::getFailed();
    $warnings = PluginLoader::getWarnings();

    $html  = '<h2>Плагины</h2>';
    $html .= '<p>Загружено: <strong>' . count($loaded) . '</strong>';
    $html .= ' &nbsp; Ошибок: <strong>' . count($failed) . '</strong></p>';

    $html .= '<table class="article-table">';
    $html .= '<thead><tr><th>Плагин</th><th>Версия</th><th>Автор</th><th>Приоритет</th><th>Описание</th></tr></thead><tbody>';

    foreach ($loaded as $fileName => $item) {
        $m = isset($item['manifest']) && is_array($item['manifest']) ? $item['manifest'] : array();
        $html .= '<tr>';
        $html .= '<td>' . iopn_special_h(isset($m['name']) ? $m['name'] : $fileName) . '</td>';
        $html .= '<td>' . iopn_special_h(isset($m['version']) ? $m['version'] : '') . '</td>';
        $html .= '<td>' . iopn_special_h(isset($m['author']) ? $m['author'] : '') . '</td>';
        $html .= '<td>' . (int)(isset($m['priority']) ? $m['priority'] : 50) . '</td>';
        $html .= '<td>' . iopn_special_h(isset($m['description']) ? $m['description'] : '') . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    if (!empty($failed)) {
        $html .= '<h3>Ошибки загрузки</h3><ul>';
        foreach ($failed as $name => $error) {
            $html .= '<li><strong>' . iopn_special_h($name) . '</strong>: ' . iopn_special_h($error) . '</li>';
        }
        $html .= '</ul>';
    }

    if (!empty($skipped)) {
        $html .= '<h3>Пропущенные плагины</h3><ul>';
        foreach ($skipped as $name => $reason) {
            $html .= '<li><strong>' . iopn_special_h($name) . '</strong>: ' . iopn_special_h($reason) . '</li>';
        }
        $html .= '</ul>';
    }

    if (!empty($warnings)) {
        $html .= '<h3>Предупреждения</h3><ul>';
        foreach ($warnings as $warning) {
            $html .= '<li>' . iopn_special_h($warning) . '</li>';
        }
        $html .= '</ul>';
    }

    return $html;
}, array(
    'description' => 'Список загруженных, пропущенных и неисправных плагинов',
    'priority' => 20,
));

SpecialPageRegistry::register('Обновление индекса', function($context) {
    $message = '';
    $error = '';

    if (!empty($context['request_method']) && $context['request_method'] === 'POST') {
        if (!Editor::checkCsrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
            $error = 'Недействительный токен формы. Обновите страницу и попробуйте снова.';
        } else {
            try {
                $index = iopn_page_index_rebuild();
                $message = 'Индекс успешно пересобран. Страниц: ' . count($index['pages']);
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }

    $index = PageIndex::getIndex();
    $updated = isset($index['updated']) ? $index['updated'] : '';
    $count = isset($index['pages']) && is_array($index['pages']) ? count($index['pages']) : 0;

    $html = '<h2>Обновление индекса</h2>';
    $html .= '<p>Индекс содержит <strong>' . $count . '</strong> страниц.</p>';
    $html .= '<p>Последнее обновление: <strong>' . iopn_special_h($updated !== '' ? $updated : 'неизвестно') . '</strong></p>';

    if ($message !== '') $html .= '<p><strong>' . iopn_special_h($message) . '</strong></p>';
    if ($error !== '') $html .= '<p><strong>' . iopn_special_h($error) . '</strong></p>';

    $html .= '<form method="post" action="' . iopn_special_h(iopn_special_page_url('Обновление индекса')) . '">';
    $html .= '<input type="hidden" name="csrf_token" value="' . iopn_special_h(Editor::csrfToken()) . '">';
    $html .= '<button type="submit">Пересобрать индекс</button>';
    $html .= '</form>';
    return $html;
}, array(
    'description' => 'Полная пересборка Pages/index.json по файлам страниц',
    'priority' => 30,
));

SpecialPageRegistry::register('Поиск', function($context) {
    $query = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $pages = PageIndex::all();
    $matches = array();

    if ($query !== '') {
        foreach ($pages as $full => $data) {
            $name = isset($data['name']) ? (string)$data['name'] : $full;
            $namespace = isset($data['namespace']) ? (string)$data['namespace'] : '';
            $haystack = $full . ' ' . $name . ' ' . $namespace;
            if (function_exists('mb_stripos')) {
                $found = mb_stripos($haystack, $query, 0, 'UTF-8') !== false;
            } else {
                $found = stripos($haystack, $query) !== false;
            }
            if ($found) $matches[$full] = $data;
        }
    }

    uasort($matches, function($a, $b) {
        $aa = isset($a['name']) ? $a['name'] : '';
        $bb = isset($b['name']) ? $b['name'] : '';
        return strcasecmp($aa, $bb);
    });

    $action = iopn_special_page_url('Поиск');
    $html  = '<h2>Поиск</h2>';
    $html .= '<form method="get" action="">';
    $html .= '<input type="hidden" name="Page" value="Служебная:Поиск">';
    $html .= '<input type="text" name="q" value="' . iopn_special_h($query) . '" placeholder="Название страницы"> ';
    $html .= '<button type="submit">Найти</button>';
    $html .= '</form>';

    if ($query === '') {
        $html .= '<p>Введите название или его часть.</p>';
        return $html;
    }

    $html .= '<p>Найдено: <strong>' . count($matches) . '</strong></p>';
    $html .= '<ul>';
    foreach ($matches as $full => $data) {
        $href = '?Page=' . rawurlencode($full);
        $html .= '<li><a href="' . iopn_special_h($href) . '">' . iopn_special_h($full) . '</a></li>';
    }
    if (empty($matches)) $html .= '<li>Ничего не найдено.</li>';
    $html .= '</ul>';
    return $html;
}, array(
    'aliases' => array('Поиск страниц'),
    'description' => 'Поиск страниц по имени через глобальный индекс',
    'priority' => 40,
));
