<?php

namespace App\Core\Mail;

use App\Core\Logger;

/**
 * Mail Class
 * 
 * Provides email sending functionality
 */
class Mail
{
    protected static ?Mail $instance = null;
    protected MailDriverInterface $driver;
    
    protected string $fromEmail = '';
    protected string $fromName = '';
    protected string $replyTo = '';

    public function __construct()
    {
        // Use SMTP driver by default
        $driver = $_ENV['MAIL_DRIVER'] ?? 'smtp';
        
        switch ($driver) {
            case 'smtp':
                $this->driver = new SmtpMailDriver();
                break;
            case 'sendmail':
                $this->driver = new SendmailMailDriver();
                break;
            case 'mail':
                $this->driver = new PhpMailDriver();
                break;
            default:
                $this->driver = new SmtpMailDriver();
        }
        
        // Set default from address
        $this->fromEmail = $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@example.com';
        $this->fromName = $_ENV['MAIL_FROM_NAME'] ?? 'PHP Palm';
        $this->replyTo = $_ENV['MAIL_REPLY_TO'] ?? $this->fromEmail;
    }

    public static function getInstance(): Mail
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Create new mail message
     */
    public static function to(string $email, ?string $name = null): MailMessage
    {
        $instance = self::getInstance();
        $message = new MailMessage($instance);
        return $message->to($email, $name);
    }

    /**
     * Send email
     */
    public function send(MailMessage $message): bool
    {
        try {
            // Set defaults if not set
            if (empty($message->getFrom())) {
                $message->from($this->fromEmail, $this->fromName);
            }
            
            if (empty($message->getReplyTo())) {
                $message->replyTo($this->replyTo);
            }

            $result = $this->driver->send($message);
            
            if ($result) {
                Logger::infoStatic('Email sent', [
                    'to' => $message->getTo(),
                    'subject' => $message->getSubject(),
                ]);
            }
            
            return $result;
        } catch (\Throwable $e) {
            Logger::errorStatic('Email send failed', [
                'to' => $message->getTo(),
                'subject' => $message->getSubject(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Static send helper
     */
    public static function sendStatic(MailMessage $message): bool
    {
        return self::getInstance()->send($message);
    }
}

