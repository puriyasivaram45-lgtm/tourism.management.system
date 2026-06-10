<?php
require_once __DIR__ . '/security.php';

if (!function_exists('phonepe_enabled')) {
    function phonepe_enabled()
    {
        return app_env_bool('PHONEPE_ENABLED', false);
    }
}

if (!function_exists('phonepe_base_url')) {
    function phonepe_base_url()
    {
        $env = strtolower((string) app_env('PHONEPE_ENV', 'sandbox'));
        $default = $env === 'production'
            ? app_env('PHONEPE_BASE_URL_PRODUCTION', 'https://api.phonepe.com/apis/pg')
            : app_env('PHONEPE_BASE_URL_SANDBOX', 'https://api-preprod.phonepe.com/apis/pg-sandbox');

        return rtrim((string) app_env('PHONEPE_BASE_URL', $default), '/');
    }
}

if (!function_exists('ensure_payment_table')) {
    function ensure_payment_table(PDO $dbh)
    {
        static $done = false;
        if ($done) {
            return;
        }

        $dbh->exec("CREATE TABLE IF NOT EXISTS tblpayments (
            id int(11) NOT NULL AUTO_INCREMENT,
            BookingId int(11) NOT NULL,
            UserEmail varchar(100) NOT NULL,
            MerchantOrderId varchar(100) NOT NULL,
            Provider varchar(30) NOT NULL DEFAULT 'phonepe',
            AmountPaise int(11) NOT NULL,
            Currency char(3) NOT NULL DEFAULT 'INR',
            Status varchar(40) NOT NULL DEFAULT 'created',
            GatewayOrderId varchar(100) DEFAULT NULL,
            RedirectUrl text DEFAULT NULL,
            RequestPayload mediumtext DEFAULT NULL,
            ResponsePayload mediumtext DEFAULT NULL,
            StatusPayload mediumtext DEFAULT NULL,
            CreatedAt timestamp NULL DEFAULT current_timestamp(),
            UpdatedAt timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
            PRIMARY KEY (id),
            UNIQUE KEY MerchantOrderId (MerchantOrderId),
            KEY BookingId (BookingId),
            KEY UserEmail (UserEmail)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci");

        $done = true;
    }
}

if (!function_exists('payment_http_request')) {
    function payment_http_request($method, $url, array $headers, $body = null)
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for PhonePe payments.');
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Payment gateway request failed: ' . $error);
        }

        $decoded = json_decode($raw, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            $decoded = array('raw' => $raw);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException('Payment gateway returned HTTP ' . $statusCode . ': ' . substr($raw, 0, 300));
        }

        return $decoded;
    }
}

if (!function_exists('phonepe_access_token')) {
    function phonepe_access_token()
    {
        $clientId = app_env('PHONEPE_CLIENT_ID');
        $clientSecret = app_env('PHONEPE_CLIENT_SECRET');
        $clientVersion = app_env('PHONEPE_CLIENT_VERSION', '1');
        $grantType = app_env('PHONEPE_OAUTH_GRANT_TYPE', 'client_credentials');

        if (!$clientId || !$clientSecret) {
            throw new RuntimeException('PhonePe credentials are missing. Update PHONEPE_CLIENT_ID and PHONEPE_CLIENT_SECRET in .env.');
        }

        $body = http_build_query(array(
            'client_id' => $clientId,
            'client_version' => $clientVersion,
            'client_secret' => $clientSecret,
            'grant_type' => $grantType,
        ));

        $response = payment_http_request(
            'POST',
            phonepe_base_url() . '/v1/oauth/token',
            array('Content-Type: application/x-www-form-urlencoded'),
            $body
        );

        $token = $response['access_token'] ?? $response['accessToken'] ?? $response['encryptedAccessToken'] ?? $response['token'] ??
            ($response['data']['access_token'] ?? $response['data']['accessToken'] ?? $response['data']['encryptedAccessToken'] ?? null);

        if (!$token) {
            throw new RuntimeException('PhonePe auth token was not present in the gateway response.');
        }

        return $token;
    }
}

if (!function_exists('phonepe_create_payment_url')) {
    function phonepe_create_payment_url($merchantOrderId, $amountPaise, $redirectUrl, array $metaInfo)
    {
        $payload = array(
            'merchantOrderId' => $merchantOrderId,
            'amount' => (int) $amountPaise,
            'expireAfter' => app_env_int('PHONEPE_PAYMENT_EXPIRE_AFTER', 1200),
            'metaInfo' => array(
                'udf1' => substr((string) ($metaInfo['booking_id'] ?? ''), 0, 256),
                'udf2' => substr((string) ($metaInfo['package_name'] ?? ''), 0, 256),
                'udf3' => substr((string) ($metaInfo['user_email'] ?? ''), 0, 256),
                'udf4' => substr('Days: ' . (string) ($metaInfo['travel_days'] ?? '1') . ', Adults: ' . (string) ($metaInfo['adults'] ?? '1') . ', Children: ' . (string) ($metaInfo['children'] ?? '0') . ', Rooms: ' . (string) ($metaInfo['rooms'] ?? '1'), 0, 256),
                'udf5' => substr('Estimate INR: ' . (string) ($metaInfo['estimated_total'] ?? ''), 0, 256),
            ),
            'paymentFlow' => array(
                'type' => 'PG_CHECKOUT',
                'message' => 'Tour package booking payment',
                'merchantUrls' => array(
                    'redirectUrl' => $redirectUrl,
                ),
            ),
        );

        $response = payment_http_request(
            'POST',
            phonepe_base_url() . '/checkout/v2/pay',
            array(
                'Content-Type: application/json',
                'Authorization: O-Bearer ' . phonepe_access_token(),
            ),
            json_encode($payload)
        );

        $paymentUrl = $response['redirectUrl'] ?? $response['paymentUrl'] ?? $response['url'] ??
            ($response['data']['redirectUrl'] ?? $response['data']['paymentUrl'] ?? $response['data']['url'] ??
            ($response['data']['instrumentResponse']['redirectInfo']['url'] ?? null));

        if (!$paymentUrl) {
            throw new RuntimeException('PhonePe payment URL was not present in the gateway response.');
        }

        return array($paymentUrl, $payload, $response);
    }
}

if (!function_exists('phonepe_order_status')) {
    function phonepe_order_status($merchantOrderId)
    {
        return payment_http_request(
            'GET',
            phonepe_base_url() . '/checkout/v2/order/' . rawurlencode($merchantOrderId) . '/status',
            array(
                'Content-Type: application/json',
                'Authorization: O-Bearer ' . phonepe_access_token(),
            )
        );
    }
}

if (!function_exists('normalize_payment_status')) {
    function normalize_payment_status(array $payload)
    {
        $state = strtoupper((string) (
            $payload['state'] ?? $payload['code'] ?? $payload['status'] ??
            ($payload['data']['state'] ?? $payload['data']['code'] ?? $payload['data']['status'] ?? '')
        ));

        if (in_array($state, array('COMPLETED', 'SUCCESS', 'PAYMENT_SUCCESS', 'TRANSACTION_SUCCESS'), true)) {
            return 'success';
        }
        if (in_array($state, array('FAILED', 'PAYMENT_ERROR', 'TRANSACTION_FAILED', 'DECLINED'), true)) {
            return 'failed';
        }
        if (in_array($state, array('PENDING', 'INITIATED', 'CREATED'), true)) {
            return 'pending';
        }
        return $state ? strtolower($state) : 'unknown';
    }
}

if (!function_exists('payment_latest_for_booking')) {
    function payment_latest_for_booking(PDO $dbh, $bookingId, $userEmail = null)
    {
        ensure_payment_table($dbh);
        $sql = 'SELECT * FROM tblpayments WHERE BookingId=:bookingId';
        if ($userEmail !== null) {
            $sql .= ' AND UserEmail=:userEmail';
        }
        $sql .= ' ORDER BY id DESC LIMIT 1';
        $query = $dbh->prepare($sql);
        $query->bindValue(':bookingId', (int) $bookingId, PDO::PARAM_INT);
        if ($userEmail !== null) {
            $query->bindValue(':userEmail', $userEmail, PDO::PARAM_STR);
        }
        $query->execute();
        return $query->fetch(PDO::FETCH_OBJ);
    }
}
