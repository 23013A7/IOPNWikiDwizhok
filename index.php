<!DOCTYPE html>
<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 0);
    mb_internal_encoding('UTF-8');
    mb_http_output('UTF-8');

    header('Content-Type: text/html; charset=utf-8');

    $settings = include 'config.php';
    $current_skin = $settings['WikiSkin'];

    // Система плагинов
    require_once 'core/HookManager.php';
    require_once 'core/PluginLoader.php';
    require_once 'core/Parser.php';
    PluginLoader::load(__DIR__ . '/plugins');

    // --- Парсинг имени страницы и пространства имён ---
    // URL: ?Page=Название          → файл Pages/Название.iopnwiki
    // URL: ?Page=Шаблон:Название   → файл Pages/Шаблон/Название.iopnwiki

    $NamePage = isset($_GET['Page']) ? $_GET['Page'] : '';
    if ($NamePage === '') {
        $NamePage = 'Главная страница';
    }

    // Разбиваем на пространство имён и имя страницы
    if (strpos($NamePage, ':') !== false) {
        list($namespace, $pagename) = explode(':', $NamePage, 2);
    } else {
        $namespace = '';
        $pagename  = $NamePage;
    }

    // Очистка пространства имён — только буквы, цифры, пробел, дефис
    $namespace = preg_replace('/[^a-zA-Zа-яёА-ЯЁ0-9_\- \p{Greek}]/u', '', $namespace);

    // Очистка имени страницы — буквы, цифры, пробел, дефис, знаки препинания
    $pagename = preg_replace('/[^a-zA-Zа-яёА-ЯЁ0-9_\- —№.,()\p{Greek}]/u', '', $pagename);

    if (empty($pagename)) {
        $namespace = 'Служебная';
        $pagename  = 'Некорректное имя страницы';
    }

    // Собираем путь к файлу
    // Pages/Название.iopnwiki  или  Pages/Пространство/Название.iopnwiki
    $base_dir = __DIR__ . '/Pages';
    if ($namespace !== '') {
        $file_a_candidate = $base_dir . '/' . $namespace . '/' . $pagename . '.iopnwiki';
    } else {
        $file_a_candidate = $base_dir . '/' . $pagename . '.iopnwiki';
    }

    // Защита от path traversal — убеждаемся что путь внутри Pages/
    $real_base = realpath($base_dir);
    $real_file = realpath($file_a_candidate);

    if ($real_file && $real_base && strpos($real_file, $real_base . DIRECTORY_SEPARATOR) === 0) {
        $file_a = $real_file;
    } else {
        $file_a = null;
    }

    // Если файл не найден — страница отсутствует
    if (!$file_a || !is_file($file_a)) {
        $missing = $base_dir . '/Служебная/Отсутствующая страница.iopnwiki';
        $file_a  = is_file($missing) ? $missing : null;
    }

    // Полное имя страницы для передачи в хуки и скин
    $FullPageName = $namespace !== '' ? $namespace . ':' . $pagename : $pagename;

    // --------------------------------------------------

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
    } else {
        $input = "тест сообщения для проверки";
    }

    // Событие просмотра страницы
    HookManager::fire('page_view', array(
        'file'      => $file_a,
        'meta'      => $meta_data,
        'meta_ref'  => &$meta_data,
        'page'      => $FullPageName,
        'pagename'  => $pagename,
        'namespace' => $namespace,
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
    require_once("assets/skin/" . $current_skin . "/index.php");
?>
