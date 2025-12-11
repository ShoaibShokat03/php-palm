<?php

namespace App\Core\Mail;

/**
 * SMTP Mail Driver
 */
class SmtpMailDriver implements MailDriverInterface
{
    protected string $host;
    protected int $port;
    protected string $username;
    protected string $password;
    protected string $encryption;
    protected bool $auth;

    public function __construct()
    {
        $this->host = $_ENV['MAIL_HOST'] ?? 'localhost';
        $this->port = (int)($_ENV['MAIL_PORT'] ?? 587);
        $this->username = $_ENV['MAIL_USERNAME'] ?? '';
        $this->password = $_ENV['MAIL_PASSWORD'] ?? '';
        $this->encryption = $_ENV['MAIL_ENCRYPTION'] ?? 'tls';
        $this->auth = !empty($this->username) && !empty($this->password);
    }

    public function send(MailMessage $message): bool
    {
        // Build email
        $to = $this->formatAddresses($message->getTo());
        $from = $this->formatAddress($message->getFrom());
        $subject = $message->getSubject();
        $body = $this->buildBody($message);
        $headers = $this->buildHeaders($message, $from);

        // Use PHP mail() as fallback (SMTP would require PHPMailer or similar library)
        // For production, recommend installing PHPMailer: composer require phpmailer/phpmailer
        return mail($to, $subject, $body, $headers);
    }

    protected function buildHeaders(MailMessage $message, string $from): string
    {
        $headers = [];
        $headers[] = "From: {$from}";
        $headers[] = "Reply-To: {$message->getReplyTo()}";
        $headers[] = "MIME-Version: 1.0";
        
        if (!empty($message->getCc())) {
            $headers[] = "Cc: " . $this->formatAddresses($message->getCc());
        }
        
        if (!empty($message->getBcc())) {
            $headers[] = "Bcc: " . $this->formatAddresses($message->getBcc());
        }

        if (!empty($message->getHtml())) {
            $boundary = uniqid('boundary_');
            $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
        } else {
            $headers[] = "Content-Type: text/plain; charset=UTF-8";
        }

        // Add custom headers
        foreach ($message->getHeaders() as $name => $value) {
            $headers[] = "{$name}: {$value}";
        }

        return implode("\r\n", $headers);
    }

    protected function buildBody(MailMessage $message): string
    {
        if (!empty($message->getHtml()) && !empty($message->getText())) {
            $boundary = uniqid('boundary_');
            $body = "--{$boundary}\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
            $body .= $message->getText() . "\r\n";
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
            $body .= $message->getHtml() . "\r\n";
            $body .= "--{$boundary}--";
            return $body;
        } elseif (!empty($message->getHtml())) {
            return $message->getHtml();
        } else {
            return $message->getText();
        }
    }

    protected function formatAddress(array $address): string
    {
        if (empty($address['name'])) {
            return $address['email'];
        }
        return "{$address['name']} <{$address['email']}>";
    }

    protected function formatAddresses(array $addresses): string
    {
        $formatted = [];
        foreach ($addresses as $address) {
            $formatted[] = $this->formatAddress($address);
        }
        return implode(', ', $formatted);
    }
}

