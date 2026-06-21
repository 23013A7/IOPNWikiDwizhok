<!DOCTYPE html>
<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 0);
    mb_internal_encoding('UTF-8');
    mb_http_output('UTF-8');

    header('Content-Type: text/html; charset=utf-8');

    $settings = include 'config.php';

    // Система плагинов
    require_once 'core/HookManager.php';
    require_once 'core/PluginLoader.php';
    require_once 'core/Parser.php';
    PluginLoader::load(__DIR__ . '/plugins');

    $NamePage = $_GET['Page'];
    if ($NamePage == '') {
        $NamePage = "Главная страница";
    }

    $NamePage = preg_replace('/[^a-zA-Zа-яёА-ЯЁ0-9_\- —№.,()\p{Greek}]/u', '', $NamePage);
    if (empty($NamePage)) {
        $NamePage = "Некорректное имя страницы (Служебная)";
    }

    // Функция чтения файла
    function tschtenija($filePath) {
        $fp = fopen($filePath, 'r');
        if (!$fp) {
            throw new Exception("Не удалось открыть файл: $filePath");
        }

        if (flock($fp, LOCK_SH)) {
            $filesize = filesize($filePath);
            $content  = $filesize > 0 ? fread($fp, $filesize) : '';
            flock($fp, LOCK_UN);
        } else {
            fclose($fp);
            throw new Exception('Не удалось получить блокировку файла');
        }
        fclose($fp);

        // Отделения строки метаданых
        $meta_pos = strpos($content, "\n");
        if ($meta_pos === false) {
            $meta_json = '';
            $body      = $content;
        } else {
            $meta_json = substr($content, 0, $meta_pos);
            $body      = substr($content, $meta_pos + 1);
        }

        $meta_data = json_decode($meta_json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($meta_data)) {
            $meta_data = array();
        }

        return array('body' => $body, 'meta' => $meta_data);
    }

    $file_a = null;
    if (is_file("Page/Save/$NamePage.iopnwiki")) {
        $file_a = "Page/Save/$NamePage.iopnwiki";
    } elseif (is_file("Page/Pages/$NamePage.iopnwiki")) {
        $file_a = "Page/Pages/$NamePage.iopnwiki";
    } else {
        if (file_exists("Page/Pages/Отсутсвующия страница (Служебная).iopnwiki")) {
            $file_a = "Page/Pages/Отсутсвующия страница (Служебная).iopnwiki";
        } else {
            $input = "тест сообщения для проверки";
        }
    }

    // Получение данных страницы
    $meta_data = array();
    if ($file_a) {
        try {
            $pageData  = tschtenija($file_a);
            $input     = $pageData['body'];
            $meta_data = $pageData['meta'];
        } catch (Exception $e) {
            error_log("Ошибка чтения файла: " . $e->getMessage());
            $input     = "== Ошибка загрузки страницы ==";
            $meta_data = array(
                'views' => 0, 'data_create' => '', 'data_update' => '',
                'author' => '', 'status' => 'error', 'data_status' => '', 'version' => ''
            );
        }
    }

    // Событие просмотра страницы
    HookManager::fire('page_view', array(
        'file'     => $file_a,
        'meta'     => $meta_data,
        'meta_ref' => &$meta_data,   // ссылка что бы плагин мог обновить данные для отображения
        'page'     => $NamePage,
    ));

    // Обработка специальных статусов
    if (isset($meta_data['status']) && $meta_data['status'] == 'ошибка') {
        $error = '<div class="error"><h2>Ошибка</h2><button>Обновить</button>';
    }

    // Парсинг
    $parser       = new Parser();
    $content_wiki = $parser->parse($input);
    $open_grab    = mb_substr(strip_tags($content_wiki), 0, 160, 'UTF-8');
?>

<?php
    // Скин
    require_once("assets/skin/" . $settings['WikiSkin'] . "/index.php");
?>
