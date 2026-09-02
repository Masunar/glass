<?php

declare(strict_types=1);

namespace App\Mail\Trait;

trait MailSharedReplacements
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    protected function sharedReplacements(array $data): array
    {
        return [
            '{{lorem_ipsum}}' => 'Lorem Ipsum',
        ];
    }
}
