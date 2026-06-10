<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/payments.php';

$checks = array();

function debug_status($name, $ok, $detail)
{
    global $checks;
    $checks[] = array(
        'name' => $name,
        'ok' => $ok,
        'detail' => $detail,
    );
}

debug_status('PHP version', version_compare(PHP_VERSION, '7.0.0', '>='), PHP_VERSION);
debug_status('Loaded env file', is_readable(app_env_file_path()), app_env_file_path());
debug_status('APP_DEBUG', true, app_env('APP_DEBUG', 'not set'));
debug_status('APP_BASE_URL', app_env('APP_BASE_URL', '') !== '', app_env('APP_BASE_URL', 'not set'));
debug_status('DB host/port', true, DB_HOST . ':' . DB_PORT);

try {
    $count = $dbh->query('SELECT COUNT(*) FROM tbltourpackages')->fetchColumn();
    debug_status('Database connection', true, 'Connected. Package count: ' . $count);
} catch (Exception $ex) {
    debug_status('Database connection', false, $ex->getMessage());
}

debug_status('PHP PDO MySQL', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? 'enabled' : 'missing');
debug_status('PHP cURL', extension_loaded('curl'), extension_loaded('curl') ? 'enabled' : 'missing');
debug_status('PHONEPE_ENABLED', phonepe_enabled(), app_env('PHONEPE_ENABLED', 'not set'));
debug_status('PHONEPE_ENV', true, app_env('PHONEPE_ENV', 'not set'));
debug_status('PHONEPE_CLIENT_ID', app_env('PHONEPE_CLIENT_ID', '') !== '', app_env('PHONEPE_CLIENT_ID', '') !== '' ? 'set' : 'missing');
debug_status('PHONEPE_CLIENT_SECRET', app_env('PHONEPE_CLIENT_SECRET', '') !== '', app_env('PHONEPE_CLIENT_SECRET', '') !== '' ? 'set' : 'missing');
debug_status('PhonePe base URL', true, phonepe_base_url());
?>
<!DOCTYPE html>
<html>
<head>
    <title>TMS Debug Check</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{font-family:Arial,sans-serif;margin:24px;background:#f7f7f7;color:#222}
        table{border-collapse:collapse;width:100%;background:#fff}
        th,td{border:1px solid #ddd;padding:10px;text-align:left}
        th{background:#eee}
        .ok{color:#1b7f36;font-weight:bold}
        .fail{color:#b42318;font-weight:bold}
        code{background:#eee;padding:2px 4px}
    </style>
</head>
<body>
    <h1>TMS Debug Check</h1>
    <p>Use this page only for local setup. Delete or disable it before public hosting.</p>
    <table>
        <tr><th>Check</th><th>Status</th><th>Detail</th></tr>
        <?php foreach($checks as $check) { ?>
            <tr>
                <td><?php echo e($check['name']); ?></td>
                <td class="<?php echo $check['ok'] ? 'ok' : 'fail'; ?>"><?php echo $check['ok'] ? 'OK' : 'FAIL'; ?></td>
                <td><code><?php echo e($check['detail']); ?></code></td>
            </tr>
        <?php } ?>
    </table>
</body>
</html>
