<?php
// SMS API function for attendance and other modules
if (!function_exists('send_sms')) {
function send_sms($to, $message) {
    global $pdo;
    // Load API settings
    $settings = $pdo->query("SELECT `key`, `value` FROM settings WHERE `key` IN ('sms_api_url','sms_api_key','sms_sender_id')")->fetchAll(PDO::FETCH_KEY_PAIR);
    $api_url = $settings['sms_api_url'] ?? '';
    $api_key = $settings['sms_api_key'] ?? '';
    $senderid = $settings['sms_sender_id'] ?? '';
    if (!$api_url || !$api_key || !$senderid) return false;
    // bulksmsbd.net: senderid must be numeric (880...)
    // API param order: api_key, type, number, senderid, message
    $params = [
        'api_key' => $api_key,
        'type' => 'text',
        'number' => $to,
        'senderid' => $senderid,
        'message' => $message,
    ];
    $url = $api_url . (strpos($api_url, '?') === false ? '?' : '&') . http_build_query($params);
    // Use cURL to send GET request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    // Log API response for debugging
    $logfile = __DIR__ . '/sms_api.log';
    $logdata = date('Y-m-d H:i:s') . " | To: $to | Msg: $message | URL: $url | Response: $response | Error: $err\n";
    // Try to create the log file if not exists
    if (!file_exists($logfile)) {
        @touch($logfile);
        @chmod($logfile, 0666);
    }
    // Write log, suppress error if unable
    if (@file_put_contents($logfile, $logdata, FILE_APPEND) === false) {
        // Optionally, you can handle logging failure here (e.g., send to error_log)
        error_log('Unable to write to sms_api.log in ' . __DIR__);
    }
    // Check API response for success (bulksmsbd returns JSON with 'response_code' or 'success')
    $success = false;
    if ($err) {
        $success = false;
    } else {
        // Try to decode JSON
        $json = json_decode($response, true);
        if (is_array($json)) {
            // bulksmsbd: {"response_code":"202","success":"true", ...}
            if ((isset($json['success']) && $json['success'] === 'true') || (isset($json['response_code']) && $json['response_code'] == '202')) {
                $success = true;
            }
        } else {
            // fallback: check for "success" in plain text
            if (stripos($response, 'success') !== false || stripos($response, '202') !== false) {
                $success = true;
            }
        }
    }
    return $success;
}
}