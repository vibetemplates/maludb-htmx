<?php
/**
 * Notification Service — transactional email
 *
 * sendEmail() delivers via the company's MailerSend API key when configured
 * (settings: mailersend_api_key), otherwise falls back to PHP mail().
 * SMS lives in helpers/twilio.php (twilioSend / sendSMS).
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/company.php';   // getCompanySetting()
require_once __DIR__ . '/twilio.php';

use MailerSend\MailerSend;
use MailerSend\Helpers\Builder\Recipient;
use MailerSend\Helpers\Builder\EmailParams;

/**
 * Send an email via MailerSend API
 */
function sendEmail(int $companyId, string $to, string $subject, string $htmlBody, string $textBody = ''): bool
{
    // Get company info
    $stmt = db()->prepare("SELECT name, email FROM companies WHERE id = ?");
    $stmt->execute([$companyId]);
    $company = $stmt->fetch();

    $fromEmail = getCompanySetting($companyId, 'notification_from_email', $company['email'] ?? 'noreply@example.com');
    $fromName  = $company['name'] ?? 'Company';

    // Get MailerSend API key
    $apiKey = getCompanySetting($companyId, 'mailersend_api_key', '');

    // Fallback to PHP mail() if no API key configured
    if ($apiKey === '') {
        $boundary = md5(time());
        $headers = [
            "From: {$fromName} <{$fromEmail}>",
            "Reply-To: {$fromEmail}",
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
        ];
        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $body .= ($textBody ?: strip_tags($htmlBody)) . "\r\n\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $body .= $htmlBody . "\r\n\r\n";
        $body .= "--{$boundary}--";
        return @mail($to, $subject, $body, implode("\r\n", $headers));
    }

    try {
        $mailersend = new MailerSend(['api_key' => $apiKey]);

        $recipients = [new Recipient($to, '')];

        $emailParams = (new EmailParams())
            ->setFrom($fromEmail)
            ->setFromName($fromName)
            ->setReplyTo($fromEmail)
            ->setRecipients($recipients)
            ->setSubject($subject)
            ->setHtml($htmlBody)
            ->setText($textBody ?: strip_tags($htmlBody));

        $mailersend->email->send($emailParams);
        return true;
    } catch (\Exception $e) {
        error_log("MailerSend error for company {$companyId}: " . $e->getMessage());
        return false;
    }
}
