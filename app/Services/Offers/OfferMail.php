<?php

declare(strict_types=1);

namespace App\Services\Offers;

use Throwable;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Offer;
use App\Models\Order;
use App\Mail\OfferEmail;
use App\Enum\OfferStatus;
use App\Support\Normalize;
use App\Models\EmailTemplate;
use App\Services\AuditTrail;
use App\Models\GlobalParameter;
use App\Enum\EmailTemplateCode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Wysyłka oferty mailem.
 *
 * Trzy decyzje, które warto znać, zanim ktoś to zmieni:
 *
 * **Szablon jest punktem wyjścia, nie ostatecznym tekstem.** Handlowiec
 * dostaje gotową treść z podstawionym numerem i terminem ważności,
 * i może ją poprawić — po rozmowie telefonicznej zwykle chce dopisać
 * zdanie. Do wysyłki idzie to, co widział na ekranie.
 *
 * **Wysyłamy synchronicznie, nie kolejką.** Kolejka bez uruchomionego
 * workera zapisałaby ofertę jako wysłaną i nie wysłała jej — a to jest
 * dokładnie ta cicha awaria, przed którą się tu bronimy. Kilka ofert
 * dziennie nie potrzebuje kolejki, a błąd ma trafić do człowieka, który
 * właśnie kliknął.
 *
 * **Status zmienia się dopiero po udanej wysyłce.** Oznaczenie przed
 * próbą dałoby ofertę „wysłaną", której klient nigdy nie dostał.
 */
final readonly class OfferMail
{
    public function __construct(
        private OfferDocument $document = new OfferDocument(),
        private AuditTrail $audit = new AuditTrail(),
    ) {
    }

    /**
     * Gotowa treść do pokazania przed wysłaniem.
     *
     * @return array{errors: array<string, list<string>>, data: array<string, mixed>|null}
     */
    public function preview(int $orderId, int $offerId): array
    {
        $offer = $this->offerOf($orderId, $offerId);

        if ($offer === null) {
            return ['errors' => ['offer' => ['Ta oferta nie należy do tego zlecenia.']], 'data' => null];
        }

        $template = EmailTemplate::findByCode(EmailTemplateCode::OFFER);

        if ($template === null) {
            return [
                'errors' => ['offer' => ['Brak szablonu wiadomości w słowniku szablonów e-mail.']],
                'data' => null,
            ];
        }

        $replacements = $this->replacements($offer);

        return [
            'errors' => [],
            'data' => [
                // Adres z kartoteki jako podpowiedz, nie jako wyrok:
                // oferta czesto idzie do konkretnej osoby, nie na adres
                // firmowy. Uzyty adres ladnie w dzienniku.
                'to' => $offer->order->contractor?->email,
                'subject' => strtr($template->name, $replacements),
                'body' => strtr($template->content, $replacements),
                'attachment' => $this->document->fileName($offer),
                'reply_to' => $this->sender()?->email,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>}
     */
    public function send(int $orderId, int $offerId, array $input): array
    {
        $offer = $this->offerOf($orderId, $offerId);

        if ($offer === null) {
            return ['errors' => ['offer' => ['Ta oferta nie należy do tego zlecenia.']]];
        }

        $validator = Validator::make($input, [
            'to' => ['required', 'email', 'max:190'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
        ], [
            'to.required' => 'Podaj adres, na który ma pójść oferta — w kartotece kontrahenta go nie ma.',
            'to.email' => 'To nie wygląda na adres e-mail.',
            'subject.required' => 'Temat nie może być pusty.',
            'body.required' => 'Treść nie może być pusta.',
        ]);

        if ($validator->fails()) {
            /** @var array<string, list<string>> $messages */
            $messages = $validator->errors()->messages();

            return ['errors' => $messages];
        }

        $to = (string) Normalize::text($input['to']);
        $sender = $this->sender();

        try {
            OfferEmail::send(
                data: [
                    'to' => $to,
                    'subject' => (string) Normalize::text($input['subject']),
                    'content' => (string) $input['body'],
                    'from_name' => GlobalParameter::value('company_name') ?? config('mail.from.name'),
                    'reply_to' => $sender?->email,
                    'reply_to_name' => $sender === null ? '' : $this->name($sender),
                    'pdf' => $this->document->render($offer),
                    'pdf_name' => $this->document->fileName($offer),
                ],
                // Synchronicznie: patrz komentarz klasy.
                enqueue: false,
            );
        } catch (Throwable $exception) {
            // Status zostaje nietkniety. Oferta „wyslana", ktorej klient
            // nie dostal, jest gorsza niz blad na ekranie.
            return ['errors' => ['offer' => [
                'Nie udało się wysłać wiadomości: ' . $exception->getMessage(),
            ]]];
        }

        $wasOpen = $offer->status === OfferStatus::ISSUED;

        if ($wasOpen) {
            $offer->update(['status' => OfferStatus::SENT->value, 'sent_at' => Carbon::now()]);
        }

        // Adres w dzienniku, bo za pol roku pytanie brzmi „gdzie to
        // poszlo", a nie „czy poszlo".
        $this->audit->write(
            Order::class,
            $orderId,
            [[
                'field' => 'oferta ' . $offer->number(),
                'before' => null,
                'after' => 'wysłana na ' . $to,
            ]],
            'offer_mailed',
        );

        return ['errors' => []];
    }

    /**
     * @return array<string, string>
     */
    private function replacements(Offer $offer): array
    {
        $sender = $this->sender();

        return [
            '{{numer_oferty}}' => $offer->number(),
            '{{kontrahent}}' => $offer->order->contractor?->displayName() ?? '',
            // Brak terminu waznosci zostaje pusty, a nie „bezterminowo":
            // to dwie rozne rzeczy i druga jest zobowiazaniem.
            '{{wazna_do}}' => $offer->valid_until?->toDateString() ?? '',
            '{{handlowiec}}' => $sender === null ? '' : $this->name($sender),
            '{{firma}}' => GlobalParameter::value('company_name') ?? '',
        ];
    }

    private function sender(): ?User
    {
        /** @var User|null */
        return Auth::user();
    }

    private function name(User $user): string
    {
        return trim((string) $user->first_name . ' ' . (string) $user->last_name);
    }

    private function offerOf(int $orderId, int $offerId): ?Offer
    {
        /** @var Offer|null */
        return Offer::query()
            ->with(['order.contractor'])
            ->where('order_id', $orderId)
            ->find($offerId);
    }
}
