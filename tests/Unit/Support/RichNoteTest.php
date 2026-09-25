<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\RichNote;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Pogrubienie w komentarzu: gwiazdki w bazie, `<strong>` na wydruku,
 * nic więcej — tekst z telefonu nie wstawi znacznika do PDF-u.
 */
class RichNoteTest extends TestCase
{
    #[Test]
    public function gwiazdki_staja_sie_pogrubieniem(): void
    {
        $this->assertSame(
            'Montaż <strong>tylko rano</strong>, dzwonić <strong>przed</strong>',
            RichNote::html('Montaż **tylko rano**, dzwonić **przed**'),
        );
    }

    #[Test]
    public function html_z_tresci_jest_zabezpieczony(): void
    {
        $this->assertSame(
            '&lt;b&gt;x&lt;/b&gt; <strong>&lt;i&gt;</strong>',
            RichNote::html('<b>x</b> **<i>**'),
        );
    }

    #[Test]
    public function pogrubienie_nie_przechodzi_przez_nowa_linie(): void
    {
        $this->assertSame("**raz<br>\ndwa**", RichNote::html("**raz\ndwa**"));
    }

    #[Test]
    public function pojedyncze_gwiazdki_zostaja_tekstem(): void
    {
        $this->assertSame('2*3 = 6 **', RichNote::html('2*3 = 6 **'));
        $this->assertSame('2*3 = 6 **', RichNote::plain('2*3 = 6 **'));
    }

    #[Test]
    public function tekst_bez_znacznikow_do_podpowiedzi(): void
    {
        $this->assertSame('Montaż tylko rano', RichNote::plain('Montaż **tylko rano**'));
        $this->assertSame('', RichNote::plain(null));
        $this->assertSame('', RichNote::html(''));
    }
}
