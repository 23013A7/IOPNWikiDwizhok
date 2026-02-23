<!DOCTYPE html>
<?php
    mb_internal_encoding('UTF-8');
    mb_http_output('UTF-8');
    
    header('Content-Type: text/html; charset=utf-8');
    
    $NamePage = $_GET['Page'];
    if ($NamePage == '') {
        $NamePage = "Главная страница";
    }

    $NamePage = preg_replace('/[^a-zA-Zа-яА-Я0-9_\- —()]/u', '', $NamePage);
    if (empty($NamePage)) {
        $NamePage = "Некорректное имя страницы (Служебная)";
    }

    if (is_file("Page/Save/$NamePage.iopnwiki")) {
        $input = file_get_contents("Page/Save/$NamePage.iopnwiki");
        $file_a = "Page/Save/$NamePage.iopnwiki";
    }  elseif (is_file("Page/Pages/$NamePage.iopnwiki")) {
        $input = file_get_contents("Page/Pages/$NamePage.iopnwiki");
        $file_a = "Page/Pages/$NamePage.iopnwiki";
    } else {
        $input = file_get_contents("Page/Save/Отсутсвующия страница (Служебная).iopnwiki");
        $file_a = "Page/Save/Отсутсвующия страница (Служебная).iopnwiki";
    }
    // БЛОК ЧТЕНИЯ МЕТАДАННЫХ
    $meta_pos = strpos($input, "\n");

    $meta_json = substr($input, 0, $meta_pos);
    $input_save = $input;
    $input = substr($input, $meta_pos + 1);


    $meta_data = json_decode($meta_json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        //echo "Ошибка чтения метаданных: " . json_last_error_msg();
        $input = $input_save;
    } else {
        if (isset($meta_data['views'])) {
            $meta_data['views']++;
        } else {
            $meta_data['views'] = 1;
        }
        // Создания первой строки с мета данными
        $new_meta_json = json_encode($meta_data, JSON_UNESCAPED_UNICODE);
        $new_content = $new_meta_json . "\n" . $input;
        file_put_contents($file_a, $new_content);
    }

    require_once("wiky.inc.php");
    $wiky=new wiky;
    $input=htmlspecialchars($input);
    $open_grab = mb_substr($input, 0, 160, 'UTF-8');
?>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ИОПН — Энциклопедия <?= htmlspecialchars($NamePage) ?></title>
    <link rel="stylesheet" href="./css/style.css">
    <link rel="shortcut icon" href="../img/Favicons/Logo.ico">
<!-- Опен граф -->
    <meta name="theme-color" content="#FECC6D">
    <meta property="og:type" content="website">
    <meta property="og:url" content="http://iopn.ddns.net/Энциклопедия">
    <meta property="og:title" content="<?= htmlspecialchars($NamePage) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($open_grab) ?>…">
    <meta property="og:site_name" content="ИОПН — Энциклопедия">
    <meta property="og:locale" content="ru_RU">

    <script>
        MathJax = {
            tex: {
                inlineMath: [['$', '$'], ['\\(', '\\)']], // включает одиночные доллары
                displayMath: [['$$', '$$'], ['\\[', '\\]']]
            },
            options: {
                enableMenu: false
            },
            svg: { fontCache: 'global' }
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js"></script>

</head>
<body>
    <header>
        <div class="HeadOgranitschiten">
            <div class="HeaderBorder">
                <a href="/ИОПН — Энциклопедия/" class="HeaderLogo">
                    <picture>
                        <source srcset="../img/Logo/FullLogoWeiss.png" media="(prefers-color-scheme: dark)">
                        <img class="Logo" src="../img/Logo/FullLogoSchwarz.png" alt="ИОПН — Энциклопедия">
                    </picture>
                </a>
                <nav>
                    <ul>
                        <li><a href="?Page=Главная страница">Главная страница</a></li>
                        <li><a href="?Page=Служебная:Поиск">Поиск</a></li>
                        <li><a href="Random.php">Случаеная страница</a></li>
                        <li><a href="?Page=Редактировать">Редактировать</a></li>
                    </ul>
                </nav>
            </div>
            
        </div>
    </header>
    <main>
        <div class="content">
            <h1 id="NamePage">
                <?= htmlspecialchars($NamePage) ?>
                
                <span class="info">
            <svg class="icon" width=30px height="100%" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 16V12M12 8H12.01M22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            <div class="info-panel right" style="min-width: 250px; padding: 15px;">
                <div style="font-size:16px; position:relative; margin: -10px 10px;">
                    <?php
                        echo "<h2>Мета информация страницы:</h2>
                        <p id=\"mate_data_create\"><strong>Просмотры: </strong>" . htmlspecialchars($meta_data['views']) . "</p>
                        <p id=\"mate_data_create\"><strong>Дата создания: </strong>" . htmlspecialchars($meta_data['data_create']) . "</p>
                        <p id=\"mate_data_create\"><strong>Дата обновления: </strong>" . htmlspecialchars($meta_data['data_update']) . "</p>
                        <p id=\"mate_data_create\"><strong>Автор: </strong>" . htmlspecialchars($meta_data['author']) . "</p>
                        <p id=\"mate_data_create\"><strong>Статус: </strong>" . htmlspecialchars($meta_data['status']) . "</p>
                        <p id=\"mate_data_create\"><strong>Дата назначения статуса: </strong>" . htmlspecialchars($meta_data['data_status']) . "</p>
                        <p id=\"mate_data_create\"><strong>Версия файла iopnwiki: </strong>" . htmlspecialchars($meta_data['version']) . "</p>";
                    ?>
                </div>
            </div>
            </span>
            </h1>
            <div id="Root">
                <?php echo $wiky->parse($input); ?>
            </div>
        </div>
        <div class="End"></div>
    </main>
</body>
</html>
