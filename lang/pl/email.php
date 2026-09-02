<?php

declare(strict_types=1);

return [
    'welcome' => 'Witaj',
    'click' => 'Kliknij',
    'here' => 'Tutaj',
    'footer_text' => 'by Synteco',
    'reset_password' => [
        'subject' => 'Resetowanie Hasła',
        'heading' => 'Otrzymaliśmy prośbę o zresetowanie Twojego hasła, naciśnij przycisk poniżej, aby rozpocząć.',
        'button_text' => 'Resetuj Hasło',
        'button_dont_work' => 'Przycisk nie działa?',
        'bottom_text' => 'Jeżeli prośba o zresetowanie hasła nie pochodzi od Ciebie, możesz usunąć tę wiadomość. Twoje hasło nie zostanie zmienione.',
    ],
    'password_changed' => [
        'subject' => 'Potwierdzenie zmiany hasła',
        'heading' => 'Otrzymujesz tę wiadomość, ponieważ hasło do Twojego konta zostało zmienione.',
        'bottom_text' => 'Jeżeli hasło zostało zmienione przez Ciebie, możesz usunąć tę wiadomość.',
        'date' => 'Data modyfikacji',
        'ip_address' => 'Adres IP',
        'country' => 'Kraj',
        'region' => 'Region',
        'city' => 'Miasto',
        'geolocation_info' => 'Dane geolokalizacjne są szacunkowe i zostały uzyskane na podstawie adresu IP. Korzystanie z narzędzi takich jak VPN, może skutkować wskazaniem zupełnie innych danych niż te realnie odpowiadające lokalizacji użytkownika.',
    ],
    'user_mfa' => [
        'subject' => 'Uwierzytelnianie dwuskładnikowe',
        'heading' => 'Otrzymujesz tę wiadomość, ponieważ logowanie do Twojego konta wymaga kodu potwierdzającego',
        'bottom_text' => 'Jeżeli to nie Ty logujesz się na swoje konto, Twoje hasło mogło zostać wykradzione. Prosimy o natychmiastową zmianę hasła.',
    ],
    'user_mfa_setup' => [
        'subject' => 'Aktywacja weryfikacji e-mail',
        'heading' => 'Otrzymujesz tę wiadomość, ponieważ rozpocząłeś aktywacji weryfikacji dwuskładnikowej przez e-mail. Wprowadź poniższy kod, aby potwierdzić.',
        'bottom_text' => 'Jeżeli to nie Ty inicjowałeś aktywację weryfikacji dwuskładnikowej, zignoruj tę wiadomość.',
    ],
];
