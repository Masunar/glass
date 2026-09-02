<?php

declare(strict_types=1);

namespace Database\Seeders\Core;

use Salvon\Database\Seeder;
use App\Models\EmailTemplate;
use App\Enum\EmailTemplateCode;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as [$code, $name, $content, $variables]) {
            EmailTemplate::query()->firstOrCreate(
                ['code' => $code->value],
                ['variables' => $variables, 'name' => $name, 'content' => $content],
            );
        }
    }

    /**
     * @return array<int, array{0: EmailTemplateCode, 1: string, 2: string, 3: string[]}>
     */
    private function templates(): array
    {
        return [
            [
                EmailTemplateCode::RESET_PASSWORD,
                'Resetowanie hasła',
                $this->resetPassword(),
                ['imie', 'url'],
            ],
            [
                EmailTemplateCode::PASSWORD_CHANGED,
                'Potwierdzenie zmiany hasła',
                $this->passwordChanged(),
                ['imie', 'data_zmiany_hasla', 'adres_ip'],
            ],
            [
                EmailTemplateCode::USER_MFA,
                'Uwierzytelnianie dwuskładnikowe',
                $this->userMfa(),
                ['imie', 'kod'],
            ],
            [
                EmailTemplateCode::USER_MFA_SETUP,
                'Aktywacja weryfikacji e-mail',
                $this->userMfaSetup(),
                ['imie', 'kod'],
            ],
        ];
    }

    private function resetPassword(): string
    {
        return <<<'HTML'
<div class="wrapper-container">
    <h4 class="header">Witaj, {{imie}}!</h4>
    <div class="main-text">
        Otrzymaliśmy prośbę o zresetowanie Twojego hasła, naciśnij przycisk poniżej, aby rozpocząć.
    </div>
    <div class="btn-container">
        <a class="btn" href="{{url}}" style="width: 140px;">Resetuj hasło</a>
    </div>
    <div class="main-text" style="margin-top: 40px;">
        <div>Przycisk nie działa? Kliknij <a href="{{url}}">tutaj</a></div>
    </div>
    <div class="gray-text" style="margin-top: 40px;">
        Jeżeli prośba o zresetowanie hasła nie pochodzi od Ciebie, możesz usunąć tę wiadomość. Twoje hasło nie zostanie zmienione.
    </div>
</div>
HTML;
    }

    private function passwordChanged(): string
    {
        return <<<'HTML'
<div class="wrapper-container">
    <h4 class="header">Witaj, {{imie}}!</h4>
    <div class="main-text">
        Otrzymujesz tę wiadomość, ponieważ hasło do Twojego konta zostało zmienione.
    </div>
    <div class="main-text" style="margin-top: 20px;">
        Data modyfikacji: {{data_zmiany_hasla}}
    </div>
    <div class="main-text" style="margin-top: 5px;">
        Adres IP: {{adres_ip}}
    </div>
    <div class="gray-text" style="margin-top: 40px;">
        Jeżeli hasło zostało zmienione przez Ciebie, możesz usunąć tę wiadomość.
    </div>
</div>
HTML;
    }

    private function userMfa(): string
    {
        return <<<'HTML'
<div class="wrapper-container">
    <h4 class="header">Witaj, {{imie}}!</h4>
    <div class="main-text">
        Otrzymujesz tę wiadomość, ponieważ logowanie do Twojego konta wymaga kodu potwierdzającego.
    </div>
    <div class="btn-container">
        {{kod}}
    </div>
    <div class="gray-text" style="margin-top: 40px;">
        Jeżeli to nie Ty logujesz się na swoje konto, Twoje hasło mogło zostać wykradzione. Prosimy o natychmiastową zmianę hasła.
    </div>
</div>
HTML;
    }

    private function userMfaSetup(): string
    {
        return <<<'HTML'
<div class="wrapper-container">
    <h4 class="header">Witaj, {{imie}}!</h4>
    <div class="main-text">
        Otrzymujesz tę wiadomość, ponieważ rozpocząłeś aktywację weryfikacji dwuskładnikowej przez e-mail. Wprowadź poniższy kod, aby potwierdzić.
    </div>
    <div class="btn-container">
        {{kod}}
    </div>
    <div class="gray-text" style="margin-top: 40px;">
        Jeżeli to nie Ty inicjowałeś aktywację weryfikacji dwuskładnikowej, zignoruj tę wiadomość.
    </div>
</div>
HTML;
    }
}
