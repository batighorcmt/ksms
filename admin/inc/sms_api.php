<?php
// In your attendance.php file, after marking attendance:

// Check if SMS should be sent for this status
if (should_send_attendance_sms($status)) {
    // Get student mobile number from database
    $student_mobile = $student_map[$student_id]['mobile_number'] ?? '';
    
    if (!empty($student_mobile)) {
        // Get SMS template for this status
        $template = get_sms_template($status);
        
        if ($template) {
            // Prepare data for template
            $template_data = [
                'student_name' => $student_map[$student_id]['first_name'] . ' ' . $student_map[$student_id]['last_name'],
                'roll' => $student_map[$student_id]['roll_number'],
                'date' => $date,
                'status' => $status,
                'class' => $class_name,
                'section' => $section_name
            ];
            
            // Process template
            $message = process_sms_template($template, $template_data);
            
            // Send SMS
            $sms_sent = send_sms($student_mobile, $message);
            
            if ($sms_sent) {
                // Log successful SMS sending
                error_log("SMS sent to $student_mobile for $status status");
            } else {
                error_log("Failed to send SMS to $student_mobile");
            }
        }
    }
}
?>