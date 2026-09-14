<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * Was mit einem verschwundenen Benutzer passiert (`013-005-0002`).
 *
 * Wer aus dem Verzeichnis verschwindet, kommt nicht mehr herein — das ergibt sich von selbst.
 * Sein Konto bleibt aber, und mit ihm ein Refresh-Token, das bis zu seinem Zeitlimit weiter
 * frische Access-JWT holt. Ein Benutzer, den die Personalabteilung entfernt hat, arbeitet also
 * weiter, bis ein Zeitlimit abläuft, das niemand dafür gewählt hat.
 *
 * Gemessen wird über den `ExampleProvider`: Er liest seine Liste aus der Umgebung, und der
 * Console-Aufruf bekommt eine andere Liste als der Testserver — damit verschwindet ein Benutzer
 * aus Sicht des Abgleichs, ohne dass irgendwo ein Verzeichnis laufen müsste.
 */
class ProviderSyncApiTest extends IntegrationTestCase
{
    private function eintrag(): array
    {
        $roh = getenv('CONTENTFLY_TEST_PROVIDER') ?: '';

        if ($roh === '') {
            $this->markTestSkipped('CONTENTFLY_TEST_PROVIDER nicht gesetzt — siehe tests/README.md.');
        }

        $teile = explode(':', explode(',', $roh)[0]);

        return array('kennung' => $teile[0], 'geheimnis' => $teile[1] ?? '');
    }

    /** Meldet sich über den Provider an und räumt die angelegte Zeile nach dem Test weg. */
    private function anmelden(): array
    {
        $eintrag = $this->eintrag();

        [$status, $body] = $this->postJson('/auth/login', array(
            'alias'        => $eintrag['kennung'],
            'pass'         => $eintrag['geheimnis'],
            'loginManager' => 'example',
            'tokenType'    => 'jwt',
        ));

        $zeile = $this->pdo()->prepare('SELECT id FROM pim_user WHERE loginManager = :lm AND externalId = :ext');
        $zeile->execute(array('lm' => 'example', 'ext' => $eintrag['kennung']));

        if ($id = $zeile->fetchColumn()) {
            $this->deleteAfterTest('pim_user', (string) $id);
        }

        if ($status !== 200) {
            $this->fail('Anmeldung ueber den Provider fehlgeschlagen: '.json_encode($body));
        }

        return $body;
    }

    /**
     * Ruft den Abgleich mit einer selbst gewählten Provider-Liste auf.
     *
     * **Der Unterschied, auf den es ankommt:** Eine Liste, die den Benutzer nicht enthält,
     * heisst „aus dem Verzeichnis entfernt". Eine **leere** Liste heisst „keine Auskunft" — der
     * Provider kann nichts sagen, und dann wird niemand angefasst.
     */
    private function abgleich(string $liste, bool $trocken = false): string
    {
        $ausgabe = array();

        exec(sprintf(
            'CONTENTFLY_EXAMPLE_PROVIDER=%s %s %s appcms:provider:sync %s 2>&1',
            escapeshellarg($liste),
            escapeshellarg(PHP_BINARY),
            escapeshellarg(self::console()),
            $trocken ? '--dry-run' : ''
        ), $ausgabe);

        return implode("\n", $ausgabe);
    }

    private function istAktiv(string $kennung): bool
    {
        $zeile = $this->pdo()->prepare('SELECT isActive FROM pim_user WHERE loginManager = :lm AND externalId = :ext');
        $zeile->execute(array('lm' => 'example', 'ext' => $kennung));

        return (string) $zeile->fetchColumn() === '1';
    }

    // ── Der Kern ───────────────────────────────────────────────────────────────────────

    /**
     * **Der Nachweis, um den es geht.** Ein noch gültiges Refresh-Token nützt nichts mehr,
     * sobald das Fremdsystem den Benutzer nicht mehr kennt.
     */
    public function testWerAusDemFremdsystemVerschwindetVerliertSeinenZugang(): void
    {
        $eintrag   = $this->eintrag();
        $anmeldung = $this->anmelden();

        // Vorher: Das Refresh-Token loest ein.
        [$vorher] = $this->postJson('/auth/refresh', array('refreshToken' => $anmeldung['refreshToken']));
        $this->assertSame(200, $vorher);

        [, $zweites] = $this->postJson('/auth/refresh', array('refreshToken' => $anmeldung['refreshToken']));

        // Eine Liste OHNE diesen Benutzer — er ist aus dem Verzeichnis entfernt.
        $ausgabe = $this->abgleich('jemand-anderes:egal');

        $this->assertFalse($this->istAktiv($eintrag['kennung']), 'Gesperrt — '.$ausgabe);

        [$nachher] = $this->postJson('/auth/refresh', array('refreshToken' => $zweites['refreshToken'] ?? 'x'));
        $this->assertSame(401, $nachher, 'Das Refresh-Token loest nicht mehr ein');
    }

    /**
     * **Ein Ausfall darf nicht wie ein gelöschter Benutzer aussehen.**
     *
     * Das ist der Fehler, der eine ganze Belegschaft aussperrt: Wenn „Fremdsystem antwortet
     * nicht" als „Benutzer gibt es nicht mehr" gelesen wird, sperrt ein Netzwerkfehler alle.
     * Deshalb hat `knowsIdentifier()` drei Antworten statt zwei, und `null` fasst niemanden an.
     *
     * Beim `ExampleProvider` ist eine **leere** Liste genau dieser Fall — sie heisst „keine
     * Auskunft" und nicht „kennt niemanden". Würde eine fehlende Konfiguration als `false`
     * gelesen, sperrte der erste Abgleich nach einem vergessenen Umgebungseintrag jeden aus.
     */
    public function testOhneAuskunftWirdNiemandGesperrt(): void
    {
        $eintrag = $this->eintrag();
        $this->anmelden();

        $ausgabe = $this->abgleich('');

        $this->assertTrue($this->istAktiv($eintrag['kennung']), 'Unangetastet — '.$ausgabe);
        $this->assertStringContainsString('could not give an answer', $ausgabe);
    }

    public function testWerNochImFremdsystemStehtBleibtAktiv(): void
    {
        $eintrag = $this->eintrag();
        $this->anmelden();

        $ausgabe = $this->abgleich(getenv('CONTENTFLY_TEST_PROVIDER'));

        $this->assertTrue($this->istAktiv($eintrag['kennung']), 'Unangetastet — '.$ausgabe);
    }

    /**
     * `--dry-run` zählt und sperrt nicht. Ein Abgleich, den man nicht vorher ansehen kann, wird
     * nicht ausgeführt.
     */
    public function testDryRunZaehltUndSperrtNicht(): void
    {
        $eintrag = $this->eintrag();
        $this->anmelden();

        $ausgabe = $this->abgleich('jemand-anderes:egal', true);

        $this->assertStringContainsString('--dry-run', $ausgabe);
        $this->assertTrue($this->istAktiv($eintrag['kennung']), 'Noch aktiv — '.$ausgabe);
    }

    /**
     * Ein Benutzer mit lokalem Passwort geht kein Fremdsystem etwas an.
     */
    public function testEinBenutzerOhneProviderWirdNichtAngefasst(): void
    {
        $this->abgleich('jemand-anderes:egal');

        $zeile = $this->pdo()->query("SELECT isActive FROM pim_user WHERE alias = 'admin'");

        $this->assertSame('1', (string) $zeile->fetchColumn(), 'admin hat keinen loginManager');
    }
}
