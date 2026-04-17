<!DOCTYPE html>
<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 0);
    mb_internal_encoding('UTF-8');
    mb_http_output('UTF-8');
    
    header('Content-Type: text/html; charset=utf-8');

    $settings = include 'config.php';

    $NamePage = $_GET['Page'];
    if ($NamePage == '') {
        $NamePage = "Главная страница";
    }

    $NamePage = preg_replace('/[^a-zA-Zа-яёА-ЯЁ0-9_\- —№.,()\p{Greek}]/u', '', $NamePage);
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

            // Отделяем первую строку (метаданные)
            $meta_pos = strpos($content, "\n");
            if ($meta_pos === false) {
                // Нет перевода строки – считаем файл новым или битым
                $meta_json = '';
                $body = $content;
            } else {
                $meta_json = substr($content, 0, $meta_pos);
                $body = substr($content, $meta_pos + 1);
            }

            $meta_data = json_decode($meta_json, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($meta_data)) {
                // Сделать потом
            } else {
                $meta_data['views'] = (isset($meta_data['views']) ? $meta_data['views'] : 0) + 1; //увеличения сщётчика просмотров
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

        // Возвращаем и тело, и метаданные
        return ['body' => $body, 'meta' => $meta_data];
    }

    if (is_file("Page/Save/$NamePage.iopnwiki")) {
        $file_a = "Page/Save/$NamePage.iopnwiki";
    }  elseif (is_file("Page/Pages/$NamePage.iopnwiki")) {
        $file_a = "Page/Pages/$NamePage.iopnwiki";
    } else {
        require_once("gen.php");
        $geniration = new Geniraten;
        $input = $geniration->Gen($NamePage); // получаем тело страницы
        $file_a = "Page/Pages/$NamePage.iopnwiki";
    }
    // Получение даных страницы
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
    if ($meta_data['status'] == 'ошибка') {
        $error = '<div class="error"><h2>Ошибка</h2><button>Обновить</button>';
    }

    require_once("wiky.inc.php");
    $wiky=new wiky;
    $content_wiki = $wiky->parse($input);
    $open_grab = mb_substr(strip_tags($content_wiki), 0, 160, 'UTF-8');
?>
<?php
    require_once("assets/skin/" . $settings['WikiSkin'] . "/index.php");
?>
