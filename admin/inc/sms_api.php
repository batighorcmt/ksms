<?php
// SMS API helper. Defines send_sms() only. No top-level code.

if (!function_exists('send_sms')) {
    function send_sms($to, $message)
    {
        // Uses global PDO from config.php
        global $pdo;

        // Normalize inputs
        $to = trim($to);
        $message = trim($message);
        if ($to === '' || $message === '') {
            return false;
        }

        // Load settings
        $settings = [];
        try {
            $stmt = $pdo->query("SELECT `key`, `value` FROM settings WHERE `key` IN ('sms_api_url','sms_api_key','sms_sender_id','sms_masking')");
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $e) {
            // Fall through; will use defaults
        }

        $api_url    = isset($settings['sms_api_url']) && $settings['sms_api_url'] !== ''
                    ? $settings['sms_api_url']
                    : 'http://bulksmsbd.net/api/smsapi';
        $api_key    = isset($settings['sms_api_key']) ? $settings['sms_api_key'] : '';
        $senderid   = isset($settings['sms_sender_id']) ? $settings['sms_sender_id'] : '';

        // Prepare request (bulksmsbd compatible)
        $params = [
            'api_key'  => $api_key,
            'type'     => 'text',
            'number'   => $to,
            'senderid' => $senderid,
            'message'  => $message,
        ];

        // Build final URL
        $url = rtrim($api_url, '?&');
        $query = http_build_query($params);
        $finalUrl = $url . (strpos($url, '?') === false ? '?' : '&') . $query;

        // Send request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $finalUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        // Parse response
        $success = false;
        if ($response !== false) {
            $json = json_decode($response, true);
            if (is_array($json)) {
                // Common bulksmsbd success patterns
                if (
                    (isset($json['success']) && $json['success']) ||
                    (isset($json['response_code']) && in_array(intval($json['response_code']), [200, 202]))
                ) {
                    $success = true;
                }
            } else {
                // Fallback: look for keywords in plain text
                $plain = strtolower($response);
                if (strpos($plain, 'success') !== false || strpos($plain, 'queued') !== false) {
                    $success = true;
                }
            }
        }

        // Log locally for audit
        $logLine = date('Y-m-d H:i:s') . " | to={$to} | http={$httpCode} | ok=" . ($success ? '1' : '0') . " | url=" . $finalUrl . " | resp=" . substr((string)$response, 0, 500) . ($curlErr ? " | err={$curlErr}" : '') . "\n";
        $logFile = __DIR__ . DIRECTORY_SEPARATOR . 'sms_api.log';
        @file_put_contents($logFile, $logLine, FILE_APPEND);

        return $success;
    }
}

if (!function_exists('send_sms_many')) {
    // Send many personalized SMS in one API call (bulksmsbd smsapimany)
    // $pairs = [ [ 'to' => '8801XXXXXXXXX', 'message' => 'text' ], ... ]
    function send_sms_many(array $pairs)
    {
        global $pdo;

        if (empty($pairs)) { return false; }

        // Load settings
        $settings = [];
        try {
            $stmt = $pdo->query("SELECT `key`, `value` FROM settings WHERE `key` IN ('sms_api_url','sms_api_key','sms_sender_id')");
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $e) {}

        $base_url = isset($settings['sms_api_url']) && $settings['sms_api_url'] !== ''
            ? $settings['sms_api_url']
            : 'http://bulksmsbd.net/api/smsapi';
        // Derive smsapimany endpoint if base looks like smsapi
        if (stripos($base_url, 'smsapi') !== false) {
            $api_url = preg_replace('/smsapi(\/?$)/i', 'smsapimany', rtrim($base_url, '/'));
        } else {
            // Fallback to documented endpoint
            $api_url = 'http://bulksmsbd.net/api/smsapimany';
        }

        $api_key  = isset($settings['sms_api_key']) ? $settings['sms_api_key'] : '';
        $senderid = isset($settings['sms_sender_id']) ? $settings['sms_sender_id'] : '';

        $postFields = [
            'api_key'  => $api_key,
            'senderid' => $senderid,
            'messages' => json_encode($pairs),
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // In case of https issues on some hosts
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        $success = false;
        if ($response !== false) {
            $json = json_decode($response, true);
            if (is_array($json)) {
                if ((isset($json['success']) && $json['success']) ||
                    (isset($json['response_code']) && in_array(intval($json['response_code']), [200,202])) ||
                    (isset($json['status']) && strtolower($json['status']) === 'success')) {
                    $success = true;
                }
            } else {
                $plain = strtolower($response);
                if (strpos($plain, 'success') !== false || strpos($plain, 'queued') !== false) {
                    $success = true;
                }
            }
        }

        $logLine = date('Y-m-d H:i:s') . " | bulk | count=" . count($pairs) . " | http={$httpCode} | ok=" . ($success ? '1' : '0') . " | url={$api_url} | resp=" . substr((string)$response, 0, 500) . ($curlErr ? " | err={$curlErr}" : '') . "\n";
        $logFile = __DIR__ . DIRECTORY_SEPARATOR . 'sms_api.log';
        @file_put_contents($logFile, $logLine, FILE_APPEND);

        return $success;
    }
}
?>