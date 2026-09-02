<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enum\EmailTemplateCode;
use Illuminate\Mail\Message;

class PasswordChangedEmail extends DatabaseMailer
{
    protected EmailTemplateCode $templateCode = EmailTemplateCode::PASSWORD_CHANGED;

    protected function message(Message $message): void
    {
        $message->subject($this->data['subject']);
        $message->to($this->data['user']['email']);
        $message->from(config('mail.from.address'), config('mail.from.name'));
    }

    protected function contentReplacements(): array
    {
        return $this->sharedReplacements($this->data);
    }

    protected function subjectReplacements(): array
    {
        return $this->sharedReplacements($this->data);
    }
}
