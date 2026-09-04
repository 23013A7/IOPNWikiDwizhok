<?php
class PluginLoader {

    private static $loaded   = array();
    private static $skipped  = array();
    private static $failed   = array();
    private static $warnings = array();

    public static function loadAll($global_dir, $site_dir, $settings = array()) {
        $disabled           = isset($settings['disabled_plugins'])       ? $settings['disabled_plugins']       : array();
        $disable_global_all = isset($settings['disable_global_plugins']) ? $settings['disable_global_plugins'] : false;

        $site_names = self::collectNames($site_dir);

        if (!$disable_global_all) {
            $global_files = self::collectFiles($global_dir);
            foreach ($global_files as $file) {
                $name = basename($file, '.php');
                if (in_array($name, $disabled)) {
                    self::$skipped[$name] = 'отключён в config.php';
                    continue;
                }
                if (in_array($name, $site_names)) {
                    self::$warnings[] = "Глобальный плагин '$name' переопределён локальным";
                    self::$skipped[$name] = 'переопределён локальным плагином';
                    continue;
                }
                self::loadFile($file);
            }
        }

        $site_files = self::collectFiles($site_dir);
        foreach ($site_files as $file) {
            $name = basename($file, '.php');
            if (in_array($name, $disabled)) {
                self::$skipped[$name] = 'отключён в config.php';
                continue;
            }
            self::loadFile($file);
        }
    }

    public static function load($plugins_dir) {
        self::loadAll($plugins_dir, '', array());
    }

    public static function loadFile($file) {
        $name = basename($file, '.php');

        try {
            require_once $file;

            $manifest_fn = 'plugin_manifest_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
            $manifest    = array();
            if (function_exists($manifest_fn)) {
                $manifest = call_user_func($manifest_fn);
            }

            $manifest = array_merge(array(
                'name'        => $name,
                'description' => '',
                'version'     => '1.0.0',
                'author'      => '',
                'priority'    => 50,
            ), $manifest);

            self::$loaded[$name] = array(
                'manifest' => $manifest,
                'file'     => $file,
            );

        } catch (Exception $e) {
            self::$failed[$name] = $e->getMessage();
        } catch (Error $e) {
            self::$failed[$name] = $e->getMessage();
        }
    }

    private static function collectFiles($dir) {
        if (!is_dir($dir)) return array();
        $files = glob($dir . '/*.php');
        if (empty($files)) return array();
        sort($files);
        return $files;
    }

    private static function collectNames($dir) {
        $files = self::collectFiles($dir);
        return array_map(function($f) { return basename($f, '.php'); }, $files);
    }

    public static function getLoaded()   { return self::$loaded;   }
    public static function getSkipped()  { return self::$skipped;  }
    public static function getFailed()   { return self::$failed;   }
    public static function getWarnings() { return self::$warnings; }
    public static function hasFailed()   { return !empty(self::$failed);   }
    public static function hasWarnings() { return !empty(self::$warnings); }
}
