<?php
class HookManager {

    private static $hooks = array();
    private static $errors = array();

    public static function register($hook_name, $callback, $priority = 10) {
        if (!is_callable($callback)) {
            self::$errors[] = "HookManager: попытка зарегистрировать не-callable на хук '$hook_name'";
            return;
        }
        self::$hooks[$hook_name][$priority][] = $callback;
    }

    public static function apply($hook_name, $value, $data = array()) {
        if (empty(self::$hooks[$hook_name])) {
            return $value;
        }

        ksort(self::$hooks[$hook_name]);

        foreach (self::$hooks[$hook_name] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                try {
                    $result = call_user_func($callback, $value, $data);
                    if ($result !== null) {
                        $value = $result;
                    }
                } catch (Exception $e) {
                    self::$errors[] = "Хук '$hook_name' (приоритет $priority): " . $e->getMessage();
                } catch (Error $e) {
                    self::$errors[] = "Хук '$hook_name' (приоритет $priority): " . $e->getMessage();
                }
            }
        }

        return $value;
    }

    public static function fire($hook_name, $data = array()) {
        if (empty(self::$hooks[$hook_name])) {
            return;
        }

        ksort(self::$hooks[$hook_name]);

        foreach (self::$hooks[$hook_name] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                try {
                    call_user_func($callback, $data);
                } catch (Exception $e) {
                    self::$errors[] = "Хук '$hook_name' (приоритет $priority): " . $e->getMessage();
                } catch (Error $e) {
                    self::$errors[] = "Хук '$hook_name' (приоритет $priority): " . $e->getMessage();
                }
            }
        }
    }

    public static function has($hook_name) {
        return !empty(self::$hooks[$hook_name]);
    }

    public static function getErrors() {
        return self::$errors;
    }

    public static function hasErrors() {
        return !empty(self::$errors);
    }
}
