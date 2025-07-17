<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check if this is a single email send request
if (isset($_POST['singleEmail'])) {
    header('Content-Type: application/json');
    
    $fromEmail = $_POST['fromEmail'];
    $fromName = $_POST['fromName'] ?? '';
    $recipientEmail = $_POST['singleEmail'];
    $baseSubject = $_POST['subject'];
    $htmlContent = $_POST['body'] ?? '';
    $attachmentContent = $_POST['attachment'] ?? '';
    
    $response = [
        'email' => $recipientEmail,
        'status' => 'failed',
        'message' => '',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    try {
        // Validate inputs
        if (empty($fromEmail) || empty($recipientEmail) || empty($baseSubject)) {
            throw new Exception('Missing required fields');
        }
        
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid recipient email address');
        }

        // Personalize content
        $personalSubject = str_replace('email', $recipientEmail, $baseSubject);
        $personalHtml = str_replace('email', $recipientEmail, $htmlContent);
        
        // Create boundaries
        $boundary = md5(uniqid());
        $boundaryAlt = md5(uniqid());
        
        // Format From header with name
        $fromHeader = $fromName ? '"' . addslashes($fromName) . '" <' . $fromEmail . '>' : $fromEmail;
        
        // Email headers
        $headers = [
            'From: ' . $fromHeader,
            'Reply-To: ' . $fromEmail,
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
            'X-Mailer: PHP/' . phpversion()
        ];
        $headers = implode("\r\n", $headers);
        
        // Message body
        $body = "--$boundary\r\n";
        $body .= "Content-Type: multipart/alternative; boundary=\"$boundaryAlt\"\r\n\r\n";
        
        // Plain text version
        $body .= "--$boundaryAlt\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= strip_tags($personalHtml) . "\r\n\r\n";
        
        // HTML version
        $body .= "--$boundaryAlt\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        
        // Ensure proper HTML structure
        if (!preg_match('/<!DOCTYPE|<html/i', $personalHtml)) {
            $personalHtml = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>' . $personalHtml . '</body></html>';
        }
        
        $body .= $personalHtml . "\r\n\r\n";
        $body .= "--$boundaryAlt--\r\n\r\n";
        
        // Add attachment if provided
        if (!empty($attachmentContent)) {
            $encodedAttachment = chunk_split(base64_encode($attachmentContent));
            $body .= "--$boundary\r\n";
            $body .= "Content-Type: text/html; name=\"attachment.html\"\r\n";
            $body .= "Content-Disposition: attachment; filename=\"attachment.html\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= $encodedAttachment . "\r\n";
        }
        
        $body .= "--$boundary--";
        
        // Send email
        if (mail($recipientEmail, $personalSubject, $body, $headers)) {
            $response['status'] = 'sent';
            $response['message'] = 'Email sent successfully';
        } else {
            $response['message'] = 'Failed to send email - mail() function returned false';
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

// Handle bulk email send
$fromEmail = $_POST['fromEmail'];
$fromName = $_POST['fromName'] ?? '';
$toList = $_POST['toEmails'];
$baseSubject = $_POST['subject'];
$htmlContent = $_POST['body'] ?? '';
$attachmentContent = $_POST['attachment'] ?? '';

$recipients = array_unique(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $toList))));
$successCount = 0;

foreach ($recipients as $recipientEmail) {
    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        continue;
    }

    $personalSubject = str_replace('email', $recipientEmail, $baseSubject);
    $personalHtml = str_replace('email', $recipientEmail, $htmlContent);
    
    $boundary = md5(uniqid());
    $boundaryAlt = md5(uniqid());
    
    // Format From header with name
    $fromHeader = $fromName ? '"' . addslashes($fromName) . '" <' . $fromEmail . '>' : $fromEmail;
    
    $headers = [
        'From: ' . $fromHeader,
        'Reply-To: ' . $fromEmail,
        'MIME-Version: 1.0',
        'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
        'X-Mailer: PHP/' . phpversion()
    ];
    $headers = implode("\r\n", $headers);
    
    // Message body
    $body = "--$boundary\r\n";
    $body .= "Content-Type: multipart/alternative; boundary=\"$boundaryAlt\"\r\n\r\n";
    
    // Plain text version
    $body .= "--$boundaryAlt\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= strip_tags($personalHtml) . "\r\n\r\n";
    
    // HTML version
    $body .= "--$boundaryAlt\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    
    // Ensure proper HTML structure
    if (!preg_match('/<!DOCTYPE|<html/i', $personalHtml)) {
        $personalHtml = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>' . $personalHtml . '</body></html>';
    }
    
    $body .= $personalHtml . "\r\n\r\n";
    $body .= "--$boundaryAlt--\r\n\r\n";
    
    // Add attachment if provided
    if (!empty($attachmentContent)) {
        $encodedAttachment = chunk_split(base64_encode($attachmentContent));
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: text/html; name=\"attachment.html\"\r\n";
        $body .= "Content-Disposition: attachment; filename=\"attachment.html\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= $encodedAttachment . "\r\n";
    }
    
    $body .= "--$boundary--";

    if (mail($recipientEmail, $personalSubject, $body, $headers)) {
        $successCount++;
    }
}

if ($successCount > 0) {
    echo "✅ Sent $successCount emails with proper sender name and HTML display.";
} else {
    echo "❌ Failed to send any emails.";
}
?>
