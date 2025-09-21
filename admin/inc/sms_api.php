<?php
// inc/sms_api.php
function send_sms($number, $message) {
    $api_key = 'W6uYRXsPj2nLHfPJ3YCC';
    $senderid = '8809617625226';
    $url = "http://bulksmsbd.net/api/smsapi?api_key=$api_key&type=text&number=$number&senderid=$senderid&message=" . urlencode($message);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);

    // Check if SMS was sent successfully
    if (strpos($response, 'SMS Submitted') !== false) {
        return true;
    } else {
        error_log("SMS sending failed: " . $response);
        return false;
    }
}
?>