<?php
// /var/www/html/gensan-car-rental-system/classes/SMSNotifier.php

/**
 * SMS Notification Service
 * Integrated with local gateways (Semasender, iSMS, or Twilio)
 */

class SMSNotifier
{
    /**
     * Send SMS to a phone number
     */
    public static function send($phone, $message)
    {
        // Normalize phone number (e.g., +63 for PH)
        $phone = self::normalizePhone($phone);

        // Fetch API credentials from system_settings via DB
        $db = Database::getInstance();
        $settings = $db->fetchOne("SELECT value FROM system_settings WHERE setting_key = 'sms_gateway_api_key'");

        if (!$settings || empty($settings['value'])) {
            error_log("SMSNotifier: No API Key found. SMS to $phone suppressed: $message");
            return false;
        }

        // Typical REST API call (e.g., Twilio or local PH gateway)
        // $response = file_get_contents("https://gateway-url.ph/api?key={$settings['value']}&to={$phone}&msg=" . urlencode($message));

        // Mock success for now
        return true;
    }

    private static function normalizePhone($phone)
    {
        // Remove non-numeric
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Convert local (0917...) to international (63917...)
        if (strlen($phone) === 11 && strpos($phone, '0') === 0) {
            $phone = '63' . substr($phone, 1);
        }

        return $phone;
    }
}
