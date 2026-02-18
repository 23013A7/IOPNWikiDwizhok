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
    }  elseif (is_file("Page/Pages/$NamePage.iopnwiki")) {
        $input = file_get_contents("Page/Pages/$NamePage.iopnwiki");
    } else {
        $input = file_get_contents("Page/Save/Отсутсвующия страница (Служебная).iopnwiki");
    }

    require_once("wiky.inc.php");
    $wiky=new wiky;
    $input=htmlspecialchars($input);
    
?>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ИОПН — Энциклопедия <?= htmlspecialchars($NamePage) ?></title>
    <link rel="stylesheet" href="./css/base.css">
    <link rel="shortcut icon" href="../img/Favicons/Logo.ico">
    
    <meta property="og:type" content="website">
    <meta property="og:url" content="http://iopn.ddns.net/Энциклопедия">
    <meta property="og:title" content="<?= htmlspecialchars($NamePage) ?>">
    <meta property="og:description" content="Краткое описание страницы (до 160–200 символов)">
    <meta property="og:site_name" content="ИОПН — Энциклопедия">
    <meta property="og:locale" content="ru_RU">

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
                        <li><a href="?Page=Служебная:Случаеная страница">Случаеная страница</a></li>
                        <li><a href="?Page=Редактировать">Редактировать</a></li>
                    </ul>
                </nav>
            </div>
            
        </div>
    </header>
    <main>
        <div class="content">
            <h1 id="NamePage"><?= htmlspecialchars($NamePage) ?></h1>
            <div id="Root">
                <?php echo $wiky->parse($input); ?>
            </div>
        </div>
        <div class="End"></div>
    </main>
</body>
</html>
