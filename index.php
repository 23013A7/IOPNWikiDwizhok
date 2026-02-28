<!DOCTYPE html>
<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 0);
    mb_internal_encoding('UTF-8');
    mb_http_output('UTF-8');
    
    header('Content-Type: text/html; charset=utf-8');

    $NamePage = $_GET['Page'];
    if ($NamePage == '') {
        $NamePage = "Главная страница";
    }

    $NamePage = preg_replace('/[^a-zA-Zа-яёА-ЯЁ0-9_\- —()\p{Greek}]/u', '', $NamePage);
    if (empty($NamePage)) {
        $NamePage = "Некорректное имя страницы (Служебная)";
    }

    //функция чтения файла
    function tschtenija($filePath) {
        $fp = fopen($filePath, 'c+');
        if (!$fp) {
            throw new Exception("Не удалось открыть файл: $filePath");
        }

        if (flock($fp, LOCK_EX)) {
            $filesize = filesize($filePath);
            $content = $filesize > 0 ? fread($fp, $filesize) : '';

            $meta_pos = strpos($content, "\n");
            if ($meta_pos === false) {
                $meta_json = '';
                $body = $content;
            } else {
                $meta_json = substr($content, 0, $meta_pos);
                $body = substr($content, $meta_pos + 1);
            }

            $meta_data = json_decode($meta_json, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($meta_data)) {
                $meta_data = [
                    'views' => 1,
                    'data_create' => date('Y.m.d H:i:s'),
                    'data_update' => date('Y.m.d H:i:s'),
                    'author' => '',
                    'status' => 'ошибка',
                    'data_status' => date('Y.m.d H:i:s'),
                    'version' => '2'
                ];
                $body = $content . "\nПри накрутке просмотров страница удалилась";
            } else {
                $meta_data['views'] = (isset($meta_data['views']) ? $meta_data['views'] : 0) + 1;
            }
            
            $new_meta_json = json_encode($meta_data, JSON_UNESCAPED_UNICODE);
            $new_content = $new_meta_json . "\n" . $body;

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $new_content);
            fflush($fp);

            flock($fp, LOCK_UN);
        } else {
            fclose($fp);
            throw new Exception('Не удалось получить блокировку файла');
        }
        fclose($fp);

        return ['body' => $body, 'meta' => $meta_data];
    }

    if (is_file("Page/Save/$NamePage.iopnwiki")) {
        $file_a = "Page/Save/$NamePage.iopnwiki";
    }  elseif (is_file("Page/Pages/$NamePage.iopnwiki")) {
        $file_a = "Page/Pages/$NamePage.iopnwiki";
    } else {
        $file_a = "Page/Save/Отсутсвующия страница (Служебная).iopnwiki";
    }

    try {
        $pageData = tschtenija($file_a);
    } catch (Exception $e) {
        error_log("Ошибка чтения файла: " . $e->getMessage());
        $input = "== Ошибка загрузки страницы ==";
        $meta_data = ['views' => 0, 'data_create' => '', 'data_update' => '', 'author' => '', 'status' => 'error', 'data_status' => '', 'version' => ''];
    }
    $input = $pageData['body'];
    $meta_data = $pageData['meta'];

    // Обработка специальных статусов
    if ($meta_data['status'] == "генирация" && isset($meta_data['data']) && $meta_data['data'] == "0") {
        echo "ddssss";
        $input = "== sss == [[File:https://upload.wikimedia.org/wikipedia/commons/thumb/f/f4/Wikipedia_Portal_Screenshot_%282022%29.svg/960px-Wikipedia_Portal_Screenshot_%282022%29.svg.png?20220125143641]]";
    } elseif ($meta_data['status'] == "генирация") {
        $input = "== ЗАГРУЗКА == [[File:https://upload.wikimedia.org/wikipedia/commons/thumb/f/f4/Wikipedia_Portal_Screenshot_%282022%29.svg/960px-Wikipedia_Portal_Screenshot_%282022%29.svg.png?20220125143641]]";
    } elseif ($meta_data['status'] == 'ошибка') {
        $error = '<div class="error"><h2>Ошибка</h2><button>Пересоздать</button>';
    }

    require_once("wiky.inc.php");
    $wiky=new wiky;
    $input=htmlspecialchars($input);
    $open_grab = mb_substr($wiky->parse($input), 0, 160, 'UTF-8');
?>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ИОПН — Энциклопедия <?= htmlspecialchars($NamePage) ?></title>
    <link rel="stylesheet" href="./css/style.css">
    <link rel="shortcut icon" href="../img/Favicons/Enziclopedia.ico">
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
                <nav class="pc">
                    <ul>
                        <li><a href="?Page=Главная страница">Главная страница</a></li>
                        <li><a href="?Page=Служебная:Поиск">Поиск</a></li>
                        <li><a href="Random.php">Случаеная страница</a></li>
                        <li><a href="?Page=Редактировать">Редактировать</a></li>
                    </ul>
                </nav>
                <nav class="mobile">
                    <ul>
                        <li><a href="?Page=Главная страница"><svg width="40px" height="80%" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
 <path d="M12.9823 2.764C12.631 2.49075 12.4553 2.35412 12.2613 2.3016C12.0902 2.25526 11.9098 2.25526 11.7387 2.3016C11.5447 2.35412 11.369 2.49075 11.0177 2.764L4.23539 8.03912C3.78202 8.39175 3.55534 8.56806 3.39203 8.78886C3.24737 8.98444 3.1396 9.20478 3.07403 9.43905C3 9.70352 3 9.9907 3 10.5651V17.8C3 18.9201 3 19.4801 3.21799 19.908C3.40973 20.2843 3.71569 20.5903 4.09202 20.782C4.51984 21 5.0799 21 6.2 21H8.2C8.48003 21 8.62004 21 8.727 20.9455C8.82108 20.8976 8.89757 20.8211 8.9455 20.727C9 20.62 9 20.48 9 20.2V13.6C9 13.0399 9 12.7599 9.10899 12.546C9.20487 12.3578 9.35785 12.2049 9.54601 12.109C9.75992 12 10.0399 12 10.6 12H13.4C13.9601 12 14.2401 12 14.454 12.109C14.6422 12.2049 14.7951 12.3578 14.891 12.546C15 12.7599 15 13.0399 15 13.6V20.2C15 20.48 15 20.62 15.0545 20.727C15.1024 20.8211 15.1789 20.8976 15.273 20.9455C15.38 21 15.52 21 15.8 21H17.8C18.9201 21 19.4802 21 19.908 20.782C20.2843 20.5903 20.5903 20.2843 20.782 19.908C21 19.4801 21 18.9201 21 17.8V10.5651C21 9.9907 21 9.70352 20.926 9.43905C20.8604 9.20478 20.7526 8.98444 20.608 8.78886C20.4447 8.56806 20.218 8.39175 19.7646 8.03913L12.9823 2.764Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
 </svg></a></li>
                        <li><a href="?Page=Служебная:Поиск"><svg width="40px" height="80%" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
 <path d="M21 21L15.0001 15M17 10C17 13.866 13.866 17 10 17C6.13401 17 3 13.866 3 10C3 6.13401 6.13401 3 10 3C13.866 3 17 6.13401 17 10Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
 </svg></a></li>
                        <li><a href="Random.php"><svg width="40px" height="80%" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
 <path d="M9.09 9C9.3251 8.33167 9.78915 7.76811 10.4 7.40913C11.0108 7.05016 11.7289 6.91894 12.4272 7.03871C13.1255 7.15849 13.7588 7.52152 14.2151 8.06353C14.6713 8.60553 14.9211 9.29152 14.92 10C14.92 12 11.92 13 11.92 13M12 17H12.01M7.8 21H16.2C17.8802 21 18.7202 21 19.362 20.673C19.9265 20.3854 20.3854 19.9265 20.673 19.362C21 18.7202 21 17.8802 21 16.2V7.8C21 6.11984 21 5.27976 20.673 4.63803C20.3854 4.07354 19.9265 3.6146 19.362 3.32698C18.7202 3 17.8802 3 16.2 3H7.8C6.11984 3 5.27976 3 4.63803 3.32698C4.07354 3.6146 3.6146 4.07354 3.32698 4.63803C3 5.27976 3 6.11984 3 7.8V16.2C3 17.8802 3 18.7202 3.32698 19.362C3.6146 19.9265 4.07354 20.3854 4.63803 20.673C5.27976 21 6.11984 21 7.8 21Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
 </svg></a></li>
                        <li><a href="?Page=Редактировать"><svg width="40px" height="80%" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
 <path d="M12 20H21M3.00003 20H4.67457C5.16376 20 5.40835 20 5.63852 19.9447C5.84259 19.8957 6.03768 19.8149 6.21663 19.7053C6.41846 19.5816 6.59141 19.4086 6.93732 19.0627L19.5001 6.49998C20.3285 5.67156 20.3285 4.32841 19.5001 3.49998C18.6716 2.67156 17.3285 2.67156 16.5001 3.49998L3.93729 16.0627C3.59139 16.4086 3.41843 16.5816 3.29475 16.7834C3.18509 16.9624 3.10428 17.1574 3.05529 17.3615C3.00003 17.5917 3.00003 17.8363 3.00003 18.3255V20Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 </svg></a></li>
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
<p id=\"mate_data_create\"><strong>Просмотры: </strong>" . htmlspecialchars(isset($meta_data['views']) ? $meta_data['views'] : '') . "</p>
<p id=\"mate_data_create\"><strong>Дата создания: </strong>" . htmlspecialchars(isset($meta_data['data_create']) ? $meta_data['data_create'] : '') . "</p>
<p id=\"mate_data_create\"><strong>Дата обновления: </strong>" . htmlspecialchars(isset($meta_data['data_update']) ? $meta_data['data_update'] : '') . "</p>
<p id=\"mate_data_create\"><strong>Автор: </strong>" . htmlspecialchars(isset($meta_data['author']) ? $meta_data['author'] : '') . "</p>
<p id=\"mate_data_create\"><strong>Статус: </strong>" . htmlspecialchars(isset($meta_data['status']) ? $meta_data['status'] : '') . "</p>
<p id=\"mate_data_create\"><strong>Дата назначения статуса: </strong>" . htmlspecialchars(isset($meta_data['data_status']) ? $meta_data['data_status'] : '') . "</p>
<p id=\"mate_data_create\"><strong>Версия файла iopnwiki: </strong>" . htmlspecialchars(isset($meta_data['version']) ? $meta_data['version'] : '') . "</p>";
                    ?>
                </div>
            </div>
            </span>
            </h1>
            <div id="Root">
                <?php echo isset($error) ? $error : ''; echo $wiky->parse($input); ?>
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
                    }
                ?>
            </div>
        </div>
        <div class="End"></div>
    </main>
</body>
</html>
