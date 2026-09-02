<?php

declare(strict_types=1);

namespace App\Mail;

use Salvon\Mail\Mailer;
use Illuminate\Mail\Message;
use App\Models\EmailTemplate;
use App\Enum\EmailTemplateCode;

class DatabaseMailer extends Mailer
{
    use Trait\MailSharedReplacements;

    protected string $template = 'emails.db-template';
    protected EmailTemplateCode $templateCode;

    public function __construct(array $data = [], ?string $template = null)
    {
        parent::__construct($data, $template);

        if (!isset($this->templateCode)) {
            throw new \Exception('Database template code was not set');
        }

        $emailTemplate = EmailTemplate::findByCode($this->templateCode);

        $content = $emailTemplate->content;
        $subject = $emailTemplate->name;

        $content = strtr($content, $this->replacements());
        $subject = strtr($subject, $this->replacements());

        $content = strtr($content, $this->contentReplacements());
        $subject = strtr($subject, $this->subjectReplacements());

        $this->data['content'] = $content;
        $this->data['subject'] = $subject;
    }

    protected function replacements(): array
    {
        return [];
    }

    protected function contentReplacements(): array
    {
        return [];
    }

    protected function subjectReplacements(): array
    {
        return [];
    }
}
