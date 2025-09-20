<?php
// SMS API function for attendance and other modules
if (!function_exists('send_sms')) {
function send_sms($to, $message) {
    global $pdo;
    // Load API settings
    $settings = $pdo->query("SELECT `key`, `value` FROM settings WHERE `key` IN ('sms_api_url','sms_api_key','sms_sender_id','sms_masking')")->fetchAll(PDO::FETCH_KEY_PAIR);
    $api_url = $settings['sms_api_url'] ?? '';
    $api_key = $settings['sms_api_key'] ?? '';
    $senderid = $settings['sms_sender_id'] ?? '';
    $masking = $settings['sms_masking'] ?? '';
    if (!$api_url || !$api_key || !$senderid) return false;
    $params = [
        'api_key' => $api_key,
        'senderid' => $senderid,
        'number' => $to,
        'message' => $message,
    ];
    // If masking is set, add it (if your provider supports it)
    if ($masking) $params['masking'] = $masking;
    $url = $api_url . (strpos($api_url, '?') === false ? '?' : '&') . http_build_query($params);
    // Use cURL to send GET request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    // Optionally, log $response or $err
    return $response && !$err;
}
}