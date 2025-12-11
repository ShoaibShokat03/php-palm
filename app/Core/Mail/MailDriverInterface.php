<?php

namespace App\Core\Mail;

/**
 * Mail Driver Interface
 */
interface MailDriverInterface
{
    public function send(MailMessage $message): bool;
}

