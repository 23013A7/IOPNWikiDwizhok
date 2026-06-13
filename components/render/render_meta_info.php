<?php
    echo "<h2>Мета информация страницы:</h2>
    <p><strong>Просмотры: </strong>" . htmlspecialchars(isset($meta_data['views']) ? $meta_data['views'] : '') . "</p>
    <p><strong>Дата создания: </strong>" . htmlspecialchars(isset($meta_data['data_create']) ? $meta_data['data_create'] : '') . "</p>
    <p><strong>Дата обновления: </strong>" . htmlspecialchars(isset($meta_data['data_update']) ? $meta_data['data_update'] : '') . "</p>
    <p><strong>Автор: </strong>" . htmlspecialchars(isset($meta_data['author']) ? $meta_data['author'] : '') . "</p>
    <p><strong>Статус: </strong>" . htmlspecialchars(isset($meta_data['status']) ? $meta_data['status'] : '') . "</p>
    <p><strong>Дата назначения статуса: </strong>" . htmlspecialchars(isset($meta_data['data_status']) ? $meta_data['data_status'] : '') . "</p>
    <p><strong>Версия файла iopnwiki: </strong>" . htmlspecialchars(isset($meta_data['version']) ? $meta_data['version'] : '') . "</p>";
?>