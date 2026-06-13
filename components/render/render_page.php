<?php echo isset($error) ? $error : ''; echo $content_wiki;?>
<?php
    switch($NamePage) {
        case 'Индексация страниц (служебная)':
            echo "<p>Привет</p>";
            require 'indexator.php';
            break;
        case 'Все страницы (служебная)':
            require_once "Page/All_page_table.php";
            renderArticleTable('Page/index.json');
            break;
        case 'Главная страница':
            require_once "Page/Save/g.php";
            break;
    }
?>
