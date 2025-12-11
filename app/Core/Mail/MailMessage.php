<?php

namespace App\Core\Mail;

/**
 * Mail Message
 * 
 * Fluent interface for building email messages
 */
class MailMessage
{
    protected Mail $mail;
    protected array $to = [];
    protected array $cc = [];
    protected array $bcc = [];
    protected array $from = [];
    protected string $replyTo = '';
    protected string $subject = '';
    protected string $html = '';
    protected string $text = '';
    protected array $attachments = [];
    protected array $headers = [];

    public function __construct(Mail $mail)
    {
        $this->mail = $mail;
    }

    /**
     * Set recipient
     */
    public function to(string $email, ?string $name = null): self
    {
        $this->to[] = ['email' => $email, 'name' => $name];
        return $this;
    }

    /**
     * Set CC recipient
     */
    public function cc(string $email, ?string $name = null): self
    {
        $this->cc[] = ['email' => $email, 'name' => $name];
        return $this;
    }

    /**
     * Set BCC recipient
     */
    public function bcc(string $email, ?string $name = null): self
    {
        $this->bcc[] = ['email' => $email, 'name' => $name];
        return $this;
    }

    /**
     * Set from address
     */
    public function from(string $email, ?string $name = null): self
    {
        $this->from = ['email' => $email, 'name' => $name];
        return $this;
    }

    /**
     * Set reply-to address
     */
    public function replyTo(string $email): self
    {
        $this->replyTo = $email;
        return $this;
    }

    /**
     * Set subject
     */
    public function subject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Set HTML body
     */
    public function html(string $html): self
    {
        $this->html = $html;
        return $this;
    }

    /**
     * Set text body
     */
    public function text(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    /**
     * Set view as HTML body
     */
    public function view(string $view, array $data = []): self
    {
        $viewPath = __DIR__ . '/../../../src/views/emails/' . str_replace('.', '/', $view) . '.palm.php';
        
        if (file_exists($viewPath)) {
            ob_start();
            extract($data);
            require $viewPath;
            $this->html = ob_get_clean();
        }
        
        return $this;
    }

    /**
     * Add attachment
     */
    public function attach(string $path, ?string $name = null): self
    {
        if (file_exists($path)) {
            $this->attachments[] = [
                'path' => $path,
                'name' => $name ?? basename($path),
            ];
        }
        return $this;
    }

    /**
     * Add header
     */
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Send email
     */
    public function send(): bool
    {
        return $this->mail->send($this);
    }

    // Getters for Mail class
    public function getTo(): array { return $this->to; }
    public function getCc(): array { return $this->cc; }
    public function getBcc(): array { return $this->bcc; }
    public function getFrom(): array { return $this->from; }
    public function getReplyTo(): string { return $this->replyTo; }
    public function getSubject(): string { return $this->subject; }
    public function getHtml(): string { return $this->html; }
    public function getText(): string { return $this->text; }
    public function getAttachments(): array { return $this->attachments; }
    public function getHeaders(): array { return $this->headers; }
}

