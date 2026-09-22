<?php

declare(strict_types=1);

namespace App\Mail;

use Salvon\Mail\Mailer;
use Illuminate\Mail\Message;

/**
 * Oferta do klienta, z PDF-em w załączniku.
 *
 * W odróżnieniu od pozostałych maili w systemie treść **nie jest
 * renderowana tutaj**: handlowiec widzi ją gotową przed wysłaniem
 * i może dopisać zdanie, więc do wysyłki trafia tekst już ostateczny.
 * Szablon ze słownika daje tylko punkt wyjścia.
 *
 * Nadawcą jest adres firmowy, bo SMTP jest jeden i serwery pocztowe
 * odrzucają wiadomość wysłaną „w imieniu" innego adresu niż
 * uwierzytelniony. `Reply-To` wskazuje na handlowca — klient odpisuje
 * do osoby, która ofertę wystawiła, a nie na skrzynkę ogólną.
 */
class OfferEmail extends Mailer
{
    protected string $template = 'emails.db-template';

    protected function message(Message $message): void
    {
        /** @var array<string, mixed> $data */
        $data = $this->data;

        $message->subject((string) $data['subject']);
        $message->to((string) $data['to']);
        $message->from(config('mail.from.address'), (string) ($data['from_name'] ?? config('mail.from.name')));

        if (!empty($data['reply_to'])) {
            $message->replyTo((string) $data['reply_to'], (string) ($data['reply_to_name'] ?? ''));
        }

        $message->attachData(
            (string) $data['pdf'],
            (string) $data['pdf_name'],
            ['mime' => 'application/pdf'],
        );
    }
}
