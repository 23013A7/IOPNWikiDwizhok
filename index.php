<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 0);
    mb_internal_encoding('UTF-8');
    mb_http_output('UTF-8');

    header('Content-Type: text/html; charset=utf-8');

    $settings = include 'config.php';
    if (!is_array($settings)) $settings = array();
    $settings = array_merge(array(
        'EngineVersion' => '0.0.0',
        'WikiName'      => 'Wiki',
        'WikiSkin'      => 'iopnwiki',
        'WikiLanguage'  => 'ru_RU',
        'AllowEditing'  => true,
        'AllowSource'   => true,
    ), $settings);
    $current_skin = $settings['WikiSkin'];

    if (session_status() !== PHP_SESSION_ACTIVE) session_start();

    require_once 'core/HookManager.php';
    require_once 'core/ASTNode.php';
    require_once 'core/RuleRegistry.php';
    require_once 'core/Tokenizer.php';
    require_once 'core/TreeParser.php';
    require_once 'core/Renderer.php';
    require_once 'core/Parser.php';
    require_once 'core/Editor.php';
    require_once 'core/PluginLoader.php';

    PluginLoader::load(__DIR__ . '/plugins');

    $NamePage = isset($_GET['Page']) ? $_GET['Page'] : '';
    if ($NamePage === '') $NamePage = 'Главная страница';

    if (strpos($NamePage, ':') !== false) {
        list($namespace, $pagename) = explode(':', $NamePage, 2);
    } else {
        $namespace = '';
        $pagename  = $NamePage;
    }

    $namespace = preg_replace('/[^a-zA-Zа-яёА-ЯЁ0-9_\- \p{Greek}]/u', '', $namespace);
    $pagename  = preg_replace('/[^a-zA-Zа-яёА-ЯЁ0-9_\- —№.,() \p{Greek}]/u', '', $pagename);

    if (empty($pagename)) {
        $namespace = 'Служебная';
        $pagename  = 'Некорректное имя страницы';
    }

    $base_dir = __DIR__ . '/Pages';
    $file_candidate = $namespace !== ''
        ? $base_dir . '/' . $namespace . '/' . $pagename . '.iopnwiki'
        : $base_dir . '/' . $pagename . '.iopnwiki';

    $real_base = realpath($base_dir);
    $real_file = realpath($file_candidate);
    $file_a    = ($real_file && $real_base && strpos($real_file, $real_base . DIRECTORY_SEPARATOR) === 0)
        ? $real_file
        : null;

    if (!$file_a || !is_file($file_a)) {
        $missing = $base_dir . '/Служебная/Отсутствующая страница.iopnwiki';
        $file_a  = is_file($missing) ? $missing : null;
    }

    $FullPageName = $namespace !== '' ? $namespace . ':' . $pagename : $pagename;
    $machen = isset($_GET['machen']) ? $_GET['machen'] : '';

    $target_file = $namespace !== ''
        ? $base_dir . '/' . $namespace . '/' . $pagename . '.iopnwiki'
        : $base_dir . '/' . $pagename . '.iopnwiki';

    if ($machen === 'source') {
        if (empty($settings['AllowSource'])) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Просмотр исходного кода отключён в конфигурации Wiki.';
            exit;
        }

        $sourceData = is_file($target_file) ? Editor::readPage($target_file) : null;
        if ($sourceData === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Страница не существует.';
            exit;
        }
        if (Editor::metaBool(isset($sourceData['meta']['source_protected']) ? $sourceData['meta']['source_protected'] : false)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Просмотр исходного кода этой страницы запрещён.';
            exit;
        }

        header('Content-Type: text/plain; charset=utf-8');
        echo $sourceData['body'];
        exit;
    }

    $editor_mode = ($machen === 'edit');
    $editor_error = '';
    $editor_saved = false;
    $editor_can_edit = false;
    $editor_data = array('body' => '', 'meta' => array());

    if ($editor_mode) {
        if (empty($settings['AllowEditing'])) {
            http_response_code(403);
            $editor_error = 'Редактирование страниц отключено в конфигурации Wiki.';
        } else {
            $editor_can_edit = true;
            if (is_file($target_file)) {
                try {
                    $editor_data = Editor::readPage($target_file);
                } catch (Exception $e) {
                    $editor_error = $e->getMessage();
                }
            }

            if (empty($editor_error) && Editor::metaBool(isset($editor_data['meta']['protected']) ? $editor_data['meta']['protected'] : false)) {
                http_response_code(403);
                $editor_error = 'Эта страница защищена от редактирования.';
                $editor_can_edit = false;
            }

            if (empty($editor_error) && $_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!Editor::checkCsrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
                    http_response_code(400);
                    $editor_error = 'Недействительный токен формы. Обновите страницу и попробуйте снова.';
                } else {
                    $body = isset($_POST['wiki_text']) ? (string)$_POST['wiki_text'] : '';
                    $oldMeta = $editor_data['meta'];
                    $meta = array();

                    if (isset($_POST['meta']) && is_array($_POST['meta'])) {
                        foreach ($_POST['meta'] as $row) {
                            if (!is_array($row)) continue;
                            $key = isset($row['key']) ? trim((string)$row['key']) : '';
                            if ($key === '' || !preg_match('/^[^\x00-\x1F\x7F]+$/u', $key)) continue;
                            if (!empty($row['delete'])) continue;
                            if ($key === 'protected' || $key === 'source_protected') {
                                $meta[$key] = !empty($row['bool']);
                            } else {
                                $meta[$key] = isset($row['value']) ? (string)$row['value'] : '';
                            }
                        }
                    }

                    $saveData = array(
                        'file'       => $target_file,
                        'page'       => $FullPageName,
                        'pagename'   => $pagename,
                        'namespace'  => $namespace,
                        'body'       => $body,
                        'meta'       => $meta,
                        'old_meta'   => $oldMeta,
                        'settings'   => $settings,
                    );
                    $saveData = Editor::beforeSave($saveData);

                    if (!is_array($saveData) || !isset($saveData['meta']) || !isset($saveData['body'])) {
                        $editor_error = 'Плагин редактора вернул некорректные данные.';
                    } else {
                        try {
                            Editor::savePage($target_file, (string)$saveData['body'], $saveData['meta']);
                            Editor::afterSave($saveData);
                            $editor_saved = true;
                            $editor_data = array('body' => (string)$saveData['body'], 'meta' => $saveData['meta']);
                        } catch (Exception $e) {
                            $editor_error = $e->getMessage();
                        }
                    }
                }
            }
        }
    }

    function tschtenija($filePath) {
        $fp = fopen($filePath, 'r');
        if (!$fp) throw new Exception("Не удалось открыть файл: $filePath");
        if (flock($fp, LOCK_SH)) {
            $content = filesize($filePath) > 0 ? fread($fp, filesize($filePath)) : '';
            flock($fp, LOCK_UN);
        } else {
            fclose($fp);
            throw new Exception('Не удалось получить блокировку');
        }
        fclose($fp);

        $meta_pos  = strpos($content, "\n");
        $meta_json = $meta_pos !== false ? substr($content, 0, $meta_pos) : '';
        $body      = $meta_pos !== false ? substr($content, $meta_pos + 1) : $content;
        $meta_data = json_decode($meta_json, true);
        if (!is_array($meta_data)) $meta_data = array();

        return array('body' => $body, 'meta' => $meta_data);
    }

    $meta_data = array();
    $input = '';

    if ($editor_mode) {
        $input = $editor_data['body'];
        $meta_data = $editor_data['meta'];
    } elseif ($file_a) {
        try {
            $pageData  = tschtenija($file_a);
            $input     = $pageData['body'];
            $meta_data = $pageData['meta'];
        } catch (Exception $e) {
            $input     = "== Ошибка загрузки страницы ==";
            $meta_data = array('views' => 0, 'status' => 'error');
        }
    }

    if (!$editor_mode) {
        HookManager::fire('page_view', array(
            'file'      => $file_a,
            'meta'      => $meta_data,
            'meta_ref'  => &$meta_data,
            'page'      => $FullPageName,
            'pagename'  => $pagename,
            'namespace' => $namespace,
        ));
    }

    $parser       = new Parser();
    $content_wiki = $editor_mode ? '' : $parser->parse($input);
    $open_grab    = $editor_mode ? '' : mb_substr(strip_tags($content_wiki), 0, 160, 'UTF-8');
?>
<?php require_once("assets/skin/" . $current_skin . "/index.php"); ?>
