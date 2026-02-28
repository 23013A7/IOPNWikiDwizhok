<?php
function renderArticleTable($jsonFile, $baseUrl = '') {
    if (file_exists($jsonFile)) {
        $jsonContent = file_get_contents($jsonFile);
        $articles = json_decode($jsonContent, true);
    } else {
        $articles = [];
    }

    $sortKey = $_GET['sort'] ?? 'data_create';
    $sortOrder = $_GET['order'] ?? 'desc';

    uasort($articles, function($a, $b) use ($sortKey, $sortOrder, &$articles) {
        $keyA = array_search($a, $articles, true);
        $keyB = array_search($b, $articles, true);
        
        if ($sortKey === 'title') {
            $valA = $keyA;
            $valB = $keyB;
        } else {
            $valA = $a[$sortKey] ?? '';
            $valB = $b[$sortKey] ?? '';
        }

        if (is_numeric($valA) && is_numeric($valB)) {
            $result = $valA <=> $valB;
        } else {
            $result = strcmp($valA, $valB);
        }

        return ($sortOrder === 'desc') ? -$result : $result;
    });

    $getSortLink = function($key, $currentKey, $currentOrder) use ($baseUrl) {
        $newOrder = ($key === $currentKey && $currentOrder === 'asc') ? 'desc' : 'asc';
        $params = $_GET;
        unset($params['sort'], $params['order']);
        $params['sort'] = $key;
        $params['order'] = $newOrder;
        return $baseUrl . '?' . http_build_query($params);
    };
    ?>
    
    <style>
        .article-table { border-collapse: collapse; width: 100%; font-family: sans-serif; }
        .article-table th, .article-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .article-table th { background-color: #f2f2f2; cursor: pointer; }
        .article-table th:hover { background-color: #ddd; }
        .article-table tr:nth-child(even) { background-color: #f9f9f9; }
        .status-error { color: red; font-weight: bold; }
        .status-ok { color: green; }
    </style>

    <table class="article-table">
        <thead>
            <tr>
                <th><a href="<?= $getSortLink('title', $sortKey, $sortOrder) ?>">Название ⇅</a></th>
                <th><a href="<?= $getSortLink('views', $sortKey, $sortOrder) ?>">Просмотры ⇅</a></th>
                <th><a href="<?= $getSortLink('data_create', $sortKey, $sortOrder) ?>">Дата создания ⇅</a></th>
                <th><a href="<?= $getSortLink('data_update', $sortKey, $sortOrder) ?>">Дата обновления ⇅</a></th>
                <th><a href="<?= $getSortLink('author', $sortKey, $sortOrder) ?>">Автор ⇅</a></th>
                <th><a href="<?= $getSortLink('status', $sortKey, $sortOrder) ?>">Статус ⇅</a></th>
                <th><a href="<?= $getSortLink('version', $sortKey, $sortOrder) ?>">Версия ⇅</a></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($articles)): ?>
                <tr><td colspan="7">Нет данных</td></tr>
            <?php else: ?>
                <?php foreach ($articles as $articleName => $data): ?>
                    <tr>
                        <td>
                            <a href="?Page=<?= urlencode($articleName) ?>">
                                <?= htmlspecialchars($articleName) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($data['views'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($data['data_create'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($data['data_update'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($data['author'] ?? '-') ?></td>
                        <td>
                            <span class="<?= ($data['status'] === 'ошибка') ? 'status-error' : 'status-ok' ?>">
                                <?= htmlspecialchars($data['status'] ?? '-') ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($data['version'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
}
?>