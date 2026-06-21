<?php
/**
 * PluginLoader — загружает все плагины из папки plugins/
 *
 * Каждый файл .php в папке plugins/ автоматически подключается.
 * Плагин внутри себя вызывает HookManager::register() чтобы подписаться на хуки.
 *
 * Использование в index.php:
 *   require_once 'core/HookManager.php';
 *   require_once 'core/PluginLoader.php';
 *   PluginLoader::load(__DIR__ . '/plugins');
 */
class PluginLoader {

    // Список успешно загруженных плагинов
    private static $loaded = array();

    // Список плагинов которые сломались при загрузке
    private static $failed = array();

    /**
     * Загрузить все плагины из указанной папки.
     *
     * @param string $plugins_dir  Абсолютный путь до папки с плагинами
     */
    public static function load($plugins_dir) {
        if (!is_dir($plugins_dir)) {
            // Папки нет — не страшно, просто нет плагинов
            return;
        }

        $files = glob($plugins_dir . '/*.php');
        if (empty($files)) {
            return;
        }

        // Сортируем файлы по имени — чтобы порядок загрузки был предсказуемым
        sort($files);

        foreach ($files as $file) {
            self::loadFile($file);
        }
    }

    /**
     * Загрузить один конкретный файл плагина.
     * Полезно если нужно подключить плагин вручную из нестандартного места.
     *
     * @param string $file  Абсолютный путь до файла плагина
     */
    public static function loadFile($file) {
        $name = basename($file, '.php');

        try {
            require_once $file;
            self::$loaded[] = $name;
        } catch (Exception $e) {
            self::$failed[$name] = $e->getMessage();
        } catch (Error $e) {
            self::$failed[$name] = $e->getMessage();
        }
    }

    /**
     * Получить список успешно загруженных плагинов.
     *
     * @return array  Например: ['views_counter', 'black_text']
     */
    public static function getLoaded() {
        return self::$loaded;
    }

    /**
     * Получить список плагинов которые не загрузились и причины.
     *
     * @return array  Например: ['broken_plugin' => 'Parse error: ...']
     */
    public static function getFailed() {
        return self::$failed;
    }

    /**
     * Есть ли плагины которые не загрузились.
     *
     * @return bool
     */
    public static function hasFailed() {
        return !empty(self::$failed);
    }
}
