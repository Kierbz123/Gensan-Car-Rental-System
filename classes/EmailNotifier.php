<?php
// /var/www/html/gensan-car-rental-system/classes/EmailNotifier.php

/**
 * Email Notification Service
 */

class EmailNotifier
{
    /**
     * Send email using SMTP settings from config
     */
    public static function send($to, $subject, $message, $attachments = [])
    {
        // Integration with PHPMailer or swiftmailer (common in vendor)
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            // $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            // ... SMTP setup from SMTP_HOST, SMTP_PORT ...
            // return $mail->send();
        }

        // Native PHP mail fallback
        $headers = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
        $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        // For local development on XAMPP, this usually just logs to /temp/
        $success = @mail($to, $subject, $message, $headers);

        if (!$success) {
            error_log("EmailNotifier: Failed to send email to $to. (Native mail returned false)");
        }

        return $success;
    }
}
