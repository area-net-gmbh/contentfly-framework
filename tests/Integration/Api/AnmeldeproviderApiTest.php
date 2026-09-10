<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * Der ganze Weg über einen Anmeldeprovider, end-to-end (`013-004-0004`).
 *
 * `013-004-0001` bis `0003` haben Vertrag, Provisionierung und Gruppenabbildung je für sich
 * gemessen. **Erst ein Provider, der wirklich läuft, zeigt, dass sie zusammenpassen** — und den
 * gab es bis hierhin nicht: `custom/Classes/` enthielt zwei Service-Klassen und keinen Provider.
 *
 * Die Vorlage prüft gegen `CONTENTFLY_BEISPIEL_PROVIDER`. Der Testserver bekommt die Variable
 * aus `CONTENTFLY_TEST_PROVIDER` (siehe `tools/ci/prepare-test-environment.sh`); dieselbe
 * Zeichenkette liegt hier vor, damit der Test weiss, welche Kennung er vorzeigen darf.
 */
class AnmeldeproviderApiTest extends IntegrationTestCase
{
    private function eintrag(): array
    {
        $roh = getenv('CONTENTFLY_TEST_PROVIDER') ?: '';

        if ($roh === '') {
            $this->markTestSkipped('CONTENTFLY_TEST_PROVIDER nicht gesetzt — siehe tests/README.md.');
        }

        $teile = explode(':', explode(',', $roh)[0]);

        return array(
            'kennung'   => $teile[0],
            'geheimnis' => $teile[1] ?? '',
            'gruppen'   => isset($teile[2]) ? explode('|', $teile[2]) : array(),
        );
    }

    /** Räumt den über den Provider angelegten Benutzer nach dem Test weg. */
    private function anmelden(?string $kennung = null, ?string $geheimnis = null): array
    {
        $eintrag = $this->eintrag();

        [$status, $body] = $this->postJson('/auth/login', array(
            'alias'        => $kennung ?? $eintrag['kennung'],
            'pass'         => $geheimnis ?? $eintrag['geheimnis'],
            'loginManager' => 'beispiel',
        ));

        $zeile = $this->pdo()->prepare('SELECT id FROM pim_user WHERE loginManager = :lm AND externalId = :ext');
        $zeile->execute(array('lm' => 'beispiel', 'ext' => $kennung ?? $eintrag['kennung']));

        if ($id = $zeile->fetchColumn()) {
            $this->nachTestLoeschen('pim_user', (string) $id);
        }

        return array($status, $body);
    }

    // ── Der volle Weg ──────────────────────────────────────────────────────────────────

    public function testEineAnmeldungUeberDenProviderLiefertEinenToken(): void
    {
        [$status, $body] = $this->anmelden();

        $this->assertSame(200, $status, 'Antwort: '.json_encode($body));
        $this->assertArrayHasKey('token', $body);

        $this->assertSame(200, $this->getMitToken('/api/schema', $body['token']),
            'Der Token oeffnet eine geschuetzte Route — derselbe Weg wie bei einer Passwortanmeldung');
    }

    /**
     * **Der Benutzer entsteht beim ersten Mal, und sein Passwort ist gesperrt.**
     *
     * Befund A-6 am laufenden System: Vorher setzte `createManagedUser()` den Benutzernamen als
     * Passwort.
     */
    public function testDerBenutzerEntstehtMitGesperrtemPasswort(): void
    {
        $eintrag = $this->eintrag();

        $this->anmelden();

        $zeile = $this->pdo()->prepare(
            'SELECT alias, pass, externalId, loginManager FROM pim_user
             WHERE loginManager = :lm AND externalId = :ext'
        );
        $zeile->execute(array('lm' => 'beispiel', 'ext' => $eintrag['kennung']));
        $gefunden = $zeile->fetch(\PDO::FETCH_ASSOC);

        $this->assertIsArray($gefunden, 'Die Zeile ist angelegt worden');
        $this->assertSame('*', $gefunden['pass'], 'Gesperrt, nicht der Benutzername');
        $this->assertSame($eintrag['kennung'], $gefunden['externalId'], 'Die Kennung steht lesbar da');
        $this->assertSame('beispiel:'.$eintrag['kennung'], $gefunden['alias'], 'Kein MD5-Praefix');
    }

    /**
     * Und die Probe darauf: Mit dem Alias oder der Kennung als Passwort kommt niemand herein.
     */
    public function testDerAngelegteBenutzerHatKeinErratbaresPasswort(): void
    {
        $eintrag = $this->eintrag();
        $this->anmelden();

        foreach (array($eintrag['kennung'], 'beispiel:'.$eintrag['kennung'], '*') as $versuch) {
            [$status] = $this->postJson('/auth/login', array(
                'alias' => 'beispiel:'.$eintrag['kennung'],
                'pass'  => $versuch,
            ));

            $this->assertSame(401, $status, 'Versuch mit "'.$versuch.'"');
        }
    }

    public function testEinZweiterLoginLegtKeinenZweitenBenutzerAn(): void
    {
        $eintrag = $this->eintrag();

        $this->anmelden();
        $this->anmelden();

        $zaehlen = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM pim_user WHERE loginManager = :lm AND externalId = :ext'
        );
        $zaehlen->execute(array('lm' => 'beispiel', 'ext' => $eintrag['kennung']));

        $this->assertSame('1', (string) $zaehlen->fetchColumn());
    }

    // ── Abweisungen ────────────────────────────────────────────────────────────────────

    public function testEinFalschesGeheimnisWirdAbgewiesen(): void
    {
        [$status, $body] = $this->anmelden(null, 'falsch');

        $this->assertSame(401, $status);
        $this->assertArrayNotHasKey('token', $body);
    }

    public function testEineUnbekannteKennungWirdAbgewiesen(): void
    {
        [$status, $body] = $this->anmelden('gibtesnicht-'.bin2hex(random_bytes(4)), 'egal');

        $this->assertSame(401, $status);
        $this->assertArrayNotHasKey('token', $body);
    }

    /**
     * **Ohne Konfiguration lässt die Vorlage niemanden herein.**
     *
     * Gemessen an der Klasse selbst und nicht über HTTP: Der Testserver hat die Variable
     * gesetzt, und ihn dafür neu zu starten hiesse, die halbe Suite anzuhalten. Die Klasse ist
     * dieselbe, die dort läuft, und die Liste kommt aus derselben Zeile.
     */
    public function testOhneKonfigurationLaesstDieVorlageNiemandenHerein(): void
    {
        $vorher = getenv(\Custom\Classes\Anmeldung\BeispielProvider::UMGEBUNGSVARIABLE);

        putenv(\Custom\Classes\Anmeldung\BeispielProvider::UMGEBUNGSVARIABLE);
        unset($_ENV[\Custom\Classes\Anmeldung\BeispielProvider::UMGEBUNGSVARIABLE]);

        try {
            $provider = new \Custom\Classes\Anmeldung\BeispielProvider();
            $eintrag  = $this->eintrag();

            $request = new \Symfony\Component\HttpFoundation\Request(
                array(), array('alias' => $eintrag['kennung'], 'pass' => $eintrag['geheimnis'])
            );

            $this->assertNull($provider->pruefen($request));
        } finally {
            if ($vorher !== false) {
                putenv(\Custom\Classes\Anmeldung\BeispielProvider::UMGEBUNGSVARIABLE.'='.$vorher);
                $_ENV[\Custom\Classes\Anmeldung\BeispielProvider::UMGEBUNGSVARIABLE] = $vorher;
            }
        }
    }

    // ── Die Gruppenabbildung, end-to-end ───────────────────────────────────────────────

    /**
     * Was der Provider an Gruppen liefert, landet über `SECURITY_PROVIDER_GRUPPEN` in
     * `pim_user.group_id` — oder eben nicht.
     *
     * **Die Testinstallation konfiguriert keine Abbildung**, und genau das ist hier die
     * Zusicherung: Ohne Eintrag bekommt ein über ein Fremdsystem angelegter Benutzer **keine**
     * Gruppe und **keine** Adminrechte. Eine Abbildung, die im Zweifel Rechte vergibt, wäre die
     * falsche Richtung.
     */
    public function testOhneAbbildungGibtEsWederGruppeNochAdminrechte(): void
    {
        $eintrag = $this->eintrag();
        $this->assertNotSame(array(), $eintrag['gruppen'], 'Der Provider liefert Gruppen mit');

        $this->anmelden();

        $zeile = $this->pdo()->prepare(
            'SELECT group_id, isAdmin FROM pim_user WHERE loginManager = :lm AND externalId = :ext'
        );
        $zeile->execute(array('lm' => 'beispiel', 'ext' => $eintrag['kennung']));
        $gefunden = $zeile->fetch(\PDO::FETCH_ASSOC);

        $this->assertNull($gefunden['group_id'], 'Ohne Abbildung keine Gruppe');
        $this->assertSame('0', (string) $gefunden['isAdmin'], 'Und erst recht keine Adminrechte');
    }

    /** Ein GET mit dem Token als `appcms-token`, nur der Statuscode. */
    private function getMitToken(string $pfad, string $token): int
    {
        [$status] = $this->get($pfad, $token);

        return $status;
    }
}
