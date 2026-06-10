<?php
if (!function_exists('app_base_path')) {
    function app_base_path()
    {
        return dirname(__DIR__, 2);
    }
}

if (!function_exists('app_env_file_candidates')) {
    function app_env_file_candidates()
    {
        return array(
            app_base_path() . '/.env',
            dirname(__DIR__) . '/.env',
        );
    }
}

if (!function_exists('app_env_file_path')) {
    function app_env_file_path()
    {
        foreach (app_env_file_candidates() as $envFile) {
            if (is_readable($envFile)) {
                return $envFile;
            }
        }

        return app_base_path() . '/.env';
    }
}

if (!function_exists('app_load_env')) {
    function app_load_env()
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $envFile = app_env_file_path();
        if (!is_readable($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key === '') {
                continue;
            }

            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}

if (!function_exists('app_env')) {
    function app_env($key, $default = null)
    {
        app_load_env();
        $value = getenv($key);
        if ($value === false && array_key_exists($key, $_ENV)) {
            $value = $_ENV[$key];
        }
        if ($value === false && array_key_exists($key, $_SERVER)) {
            $value = $_SERVER[$key];
        }
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return $value;
    }
}

if (!function_exists('app_env_bool')) {
    function app_env_bool($key, $default = false)
    {
        $value = app_env($key, $default ? 'true' : 'false');
        return in_array(strtolower((string) $value), array('1', 'true', 'yes', 'on'), true);
    }
}

if (!function_exists('app_env_int')) {
    function app_env_int($key, $default = 0)
    {
        return (int) app_env($key, $default);
    }
}

if (!function_exists('app_url')) {
    function app_url($path = '')
    {
        $base = rtrim((string) app_env('APP_BASE_URL', ''), '/');
        if ($base === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
            $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
            $base = $scheme . '://' . $host . rtrim($scriptDir, '/');
        }

        return $base . '/' . ltrim($path, '/');
    }
}
