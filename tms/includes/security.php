<?php
require_once __DIR__ . '/env.php';

if (!function_exists('e')) {
    function e($value)
    {
        return htmlentities((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('app_password_hash')) {
    function app_password_hash($plainText)
    {
        return password_hash($plainText, PASSWORD_BCRYPT);
    }
}

if (!function_exists('app_password_verify')) {
    function app_password_verify($plainText, $storedHash)
    {
        $storedHash = (string) $storedHash;
        if ($storedHash === '') {
            return false;
        }

        if (password_verify($plainText, $storedHash)) {
            return true;
        }

        if (strlen($storedHash) === 32 && ctype_xdigit($storedHash)) {
            return hash_equals(strtolower($storedHash), md5($plainText));
        }

        return false;
    }
}

if (!function_exists('app_password_needs_rehash')) {
    function app_password_needs_rehash($storedHash)
    {
        return password_get_info((string) $storedHash)['algo'] === 0 ||
            password_needs_rehash((string) $storedHash, PASSWORD_BCRYPT);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field()
    {
        return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify($token = null)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        $token = $token === null && isset($_POST['csrf_token']) ? $_POST['csrf_token'] : $token;
        return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('valid_iso_date')) {
    function valid_iso_date($value)
    {
        $date = DateTime::createFromFormat('Y-m-d', (string) $value);
        return $date && $date->format('Y-m-d') === $value;
    }
}

if (!function_exists('booking_status_label')) {
    function booking_status_label($status, $cancelledBy, $updatedAt, $adminView = false)
    {
        if ((int) $status === 0) {
            return 'Pending';
        }
        if ((int) $status === 1) {
            return 'Confirmed';
        }
        if ((int) $status === 2) {
            if ($cancelledBy === 'u') {
                return $adminView ? 'Canceled by User at ' . $updatedAt : 'Canceled by you at ' . $updatedAt;
            }
            if ($cancelledBy === 'a') {
                return $adminView ? 'Canceled by admin at ' . $updatedAt : 'Canceled by admin at ' . $updatedAt;
            }
            return 'Canceled at ' . $updatedAt;
        }
        return 'Unknown';
    }
}

if (!function_exists('save_package_image_upload')) {
    function save_package_image_upload($file, $destinationDir)
    {
        if (empty($file) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Please upload a valid package image.');
        }

        $maxBytes = app_env_int('APP_MAX_UPLOAD_BYTES', 2097152);
        if ((int) $file['size'] > $maxBytes) {
            throw new RuntimeException('Package image is too large.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowed = array(
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        );
        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Only JPG, PNG, GIF, and WEBP images are allowed.');
        }

        if (!is_dir($destinationDir)) {
            throw new RuntimeException('Package image directory is missing.');
        }

        $filename = 'package_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
        $target = rtrim($destinationDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new RuntimeException('Unable to save package image.');
        }

        return $filename;
    }
}
