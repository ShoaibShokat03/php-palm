<?php

namespace App\Core\Mail;

/**
 * PHP Mail Driver (uses mail() function)
 */
class PhpMailDriver implements MailDriverInterface
{
    public function send(MailMessage $message): bool
    {
        $to = $this->formatAddresses($message->getTo());
        $from = $this->formatAddress($message->getFrom());
        $subject = $message->getSubject();
        $body = $message->getHtml() ?: $message->getText();
        $headers = $this->buildHeaders($message, $from);

        return mail($to, $subject, $body, $headers);
    }

    protected function buildHeaders(MailMessage $message, string $from): string
    {
        $headers = [];
        $headers[] = "From: {$from}";
        $headers[] = "Reply-To: {$message->getReplyTo()}";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/html; charset=UTF-8";

        return implode("\r\n", $headers);
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

