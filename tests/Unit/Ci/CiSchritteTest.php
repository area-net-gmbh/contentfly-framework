<?php
namespace Tests\Unit\Ci;

use PHPUnit\Framework\TestCase;

/**
 * Der Waechter gegen einen Job, der stirbt, ohne zu sagen woran (000-000-0029).
 *
 * ## Der Anlass
 * Bei `013-005-0004` brach der PHP-8.4-Lauf mit **Exit 2 und null Zeilen Ausgabe** ab. Die
 * Ursache war ein echter Fehler und stand woertlich in der Composer-Ausgabe — nur schob der
 * Schritt sie nach `/dev/null`, und `set -eu` beendete das Skript, bevor irgendwer sie lesen
 * konnte. Sichtbar wurde sie erst, als die Schritte von Hand und ohne Umleitung gefahren
 * wurden.
 *
 * ## Was hier geprueft wird
 * Zwei Dinge, und sie haengen zusammen:
 *
 *  1. `tools/ci/schritt.sh` tut, was es verspricht — im Fehlerfall laut, im Erfolgsfall still.
 *     Das ist der eigentliche Test: Er faehrt einen absichtlich scheiternden Schritt und
 *     sieht nach, ob dessen Meldung in der Ausgabe steht.
 *  2. Kein Schritt in `tools/ci/` schweigt an der Funktion vorbei. Ohne diese zweite Haelfte
 *     waere die erste in dem Moment wertlos, in dem jemand die naechste Zeile mit
 *     `> /dev/null` schreibt — und genau so ist der Befund entstanden.
 *
 * ## Warum die Ausnahmen hier stehen und nicht als Kommentar im Skript
 * Damit sie sich selbst ueberwachen. Eine Ausnahme, die nichts mehr trifft, macht diesen Lauf
 * **rot** — dieselbe Regel, an der die drei Gates aus `006-005` haengen und die im Epic `010`
 * fuenfmal angeschlagen hat. Sonst waere die Liste nach der naechsten Umstellung eine
 * Erlaubnis ins Leere, die stillschweigend weiterwirkt.
 */
class CiSchritteTest extends TestCase
{
    /**
     * Was in `tools/ci/` still sein darf, und warum.
     *
     * Schluessel ist der Dateiname, Wert eine Liste aus [Textstueck der Zeile, Begruendung].
     * Jeder Eintrag muss noch zutreffen — siehe `testJedeAusnahmeWirdNochGebraucht()`.
     *
     * @var array<string,array<int,array{0:string,1:string}>>
     */
    private const AUSNAHMEN = array(
        'audit.sh' => array(
            array(
                'composer --version --no-ansi 2>/dev/null',
                'Eine Abfrage, keine Arbeit: Sie stellt fest, OB es composer gibt. Ihr Fehlschlag '
                .'ist das Ergebnis und wird drei Zeilen weiter mit eigener Meldung behandelt.',
            ),
        ),
        'prepare-test-environment.sh' => array(
            array(
                '\' 2>/dev/null; do',
                'Zwei Warteschleifen fragen im Sekundentakt, ob Datenbank bzw. Testserver schon '
                .'antworten. Ein Fehlschlag ist dort der Normalfall und die Abbruchbedingung — '
                .'nach Ablauf der Frist meldet die Schleife ihn, beim Testserver mitsamt '
                .'Serverlog. Sie war das Vorbild fuer 000-000-0029.',
            ),
            array(
                '-S "$ADRESSE" tests/router.php >',
                'Der Testserver laeuft im Hintergrund weiter; seine Ausgabe gehoert in eine '
                .'Datei und nicht ins Joblog. Die Datei ist zugleich die Quelle des '
                .'Deprecation-Gates aus 006-005 — und die Warteschleife darunter gibt sie aus, '
                .'wenn der Server nicht antwortet. `schritt` passt hier nicht: Die Funktion '
                .'wartet auf ihren Befehl, dieser Befehl aber soll gerade nicht enden.',
            ),
        ),
        'audit-ausnahmen-pruefen.sh' => array(
            array(
                'composer --version --no-ansi 2>/dev/null',
                'Dieselbe Abfrage, derselbe Grund.',
            ),
            array(
                '2>"$FEHLERLOG"',
                'Aufgefangen, nicht verworfen: Liefert composer keine Daten, gibt die Pruefung '
                .'die letzten Zeilen dieser Datei aus. Genau das war vorher `2>/dev/null`.',
            ),
        ),
    );

    /**
     * Das eigentliche Durchspielen: ein Schritt, der absichtlich scheitert.
     *
     * Er schreibt auf beide Kanaele und bricht mit einem Code ab, der nicht 1 ist — sonst
     * koennte der Test nicht unterscheiden, ob der Code durchgereicht oder erfunden wurde.
     */
    public function testEinFehlgeschlagenerSchrittZeigtSeineAusgabe(): void
    {
        $ergebnis = $this->schrittFahren(
            'schritt "Absichtlicher Fehlschlag" sh -c \'echo HARMLOSE-ZEILE; '
            .'echo DIE-URSACHE-STEHT-HIER >&2; exit 3\''
        );

        $this->assertSame(3, $ergebnis['code'], 'Der Exit-Code des Schritts muss durchgereicht werden.');

        $this->assertStringContainsString('DIE-URSACHE-STEHT-HIER', $ergebnis['ausgabe'],
            'Genau das ist der Befund: Die Meldung des gescheiterten Befehls muss zu sehen sein.');
        $this->assertStringContainsString('HARMLOSE-ZEILE', $ergebnis['ausgabe'],
            'Auch stdout gehoert dazu — viele Werkzeuge melden ihren Fehler dort.');
        $this->assertStringContainsString('Absichtlicher Fehlschlag', $ergebnis['ausgabe'],
            'Ohne den Namen des Schritts weiss man, was schiefging, aber nicht wobei.');
        $this->assertStringContainsString('Exit-Code: 3', $ergebnis['ausgabe']);
    }

    /**
     * Die andere Haelfte der Zusage: Solange es gutgeht, bleibt es leise.
     *
     * Ohne diesen Test waere „alles ausgeben" eine gueltige Loesung — und eine Pipeline, die
     * jeden apt-get-Fortschritt ausgibt, liest niemand. Dann verdeckt das volle Log den Fehler
     * genauso zuverlaessig wie das leere.
     */
    public function testEinGelungenerSchrittBleibtStill(): void
    {
        $ergebnis = $this->schrittFahren(
            'schritt "Leiser Schritt" sh -c \'echo DAS-GEHOERT-NICHT-INS-JOBLOG; exit 0\''
        );

        $this->assertSame(0, $ergebnis['code']);
        $this->assertStringNotContainsString('DAS-GEHOERT-NICHT-INS-JOBLOG', $ergebnis['ausgabe'],
            'Die Umleitung bleibt: Im Erfolgsfall gehoert die Ausgabe nicht ins Joblog.');
        $this->assertStringContainsString('Leiser Schritt', $ergebnis['ausgabe'],
            'Dass der Schritt lief, gehoert hinein — sonst sieht man den Fortschritt nicht.');
    }

    /**
     * Kein neuer stummer Schritt, der an `schritt` vorbeigeht.
     *
     * Geprueft werden zusammengesetzte Zeilen: Eine Zeile mit `\` am Ende gehoert zur
     * naechsten. Sonst zaehlte die Fortsetzung eines `schritt`-Aufrufs als eigener Befehl —
     * und genau so steht das `--quiet` in `install-composer.sh`.
     */
    public function testKeinSchrittSchweigtAnDerFunktionVorbei(): void
    {
        $verdaechtig = array();

        foreach ($this->ciSkripte() as $datei => $pfad) {
            if ($datei === 'schritt.sh') {
                // Die Funktion selbst leitet um — das ist ihre Aufgabe.
                continue;
            }

            foreach ($this->logischeZeilen($pfad) as $nummer => $zeile) {
                if (!$this->schweigt($zeile) || $this->istKommentar($zeile)) {
                    continue;
                }

                if (strpos(ltrim($zeile), 'schritt ') === 0) {
                    continue;
                }

                if ($this->istAusgenommen($datei, $zeile)) {
                    continue;
                }

                $verdaechtig[] = sprintf('%s:%d — %s', $datei, $nummer, trim($zeile));
            }
        }

        $this->assertSame(array(), $verdaechtig, implode("\n", array_merge(
            array(
                'Diese Schritte unterdruecken ihre Ausgabe, ohne ueber tools/ci/schritt.sh zu laufen.',
                'Damit stirbt der Job im Fehlerfall wieder stumm — der Befund aus 000-000-0029:',
                '',
            ),
            $verdaechtig,
            array(
                '',
                'Entweder den Aufruf in `schritt "Was es tut" <befehl>` fassen, oder — wenn das',
                'Schweigen richtig ist — eine Ausnahme mit Begruendung in AUSNAHMEN eintragen.',
            )
        )));
    }

    /**
     * Und die Ausnahmeliste ueberwacht sich selbst.
     *
     * Findet sich ein Eintrag nicht mehr in seiner Datei, ist er gegenstandslos — und ein
     * gegenstandsloser Eintrag ist keine harmlose Altlast, sondern eine Erlaubnis, die beim
     * naechsten Mal ungefragt greift.
     */
    public function testJedeAusnahmeWirdNochGebraucht(): void
    {
        $skripte = $this->ciSkripte();
        $tot     = array();

        foreach (self::AUSNAHMEN as $datei => $eintraege) {
            if (!isset($skripte[$datei])) {
                $tot[] = sprintf('%s — die Datei gibt es nicht mehr.', $datei);
                continue;
            }

            $inhalt = (string) file_get_contents($skripte[$datei]);

            foreach ($eintraege as $eintrag) {
                if (strpos($inhalt, $eintrag[0]) === false) {
                    $tot[] = sprintf('%s — "%s" steht dort nicht mehr.', $datei, $eintrag[0]);
                }
            }
        }

        $this->assertSame(array(), $tot, implode("\n", array_merge(
            array('Diese Ausnahmen treffen nichts mehr und gehoeren aus AUSNAHMEN gestrichen:', ''),
            $tot
        )));
    }

    /**
     * Faehrt einen Befehl gegen die gesourcete Funktion und gibt Code und Ausgabe zurueck.
     *
     * `set -eu` ist Absicht: Es ist die Umgebung, in der der Befund entstanden ist. stderr
     * wird auf stdout gelegt, weil die Meldung dort landet und der Test beide zusammen liest —
     * wie ein Mensch, der ins Joblog sieht.
     *
     * @return array{code:int,ausgabe:string}
     */
    private function schrittFahren(string $aufruf): array
    {
        $wurzel = dirname(__DIR__, 3);

        $skript = sprintf(
            "set -eu\n. %s/tools/ci/schritt.sh\n%s\n",
            escapeshellarg($wurzel),
            $aufruf
        );

        $ausgabe = array();
        $code    = 0;
        exec('sh -c ' . escapeshellarg($skript) . ' 2>&1', $ausgabe, $code);

        return array('code' => $code, 'ausgabe' => implode("\n", $ausgabe));
    }

    /**
     * @return array<string,string> Dateiname → voller Pfad
     */
    private function ciSkripte(): array
    {
        $skripte = array();

        foreach ((array) glob(dirname(__DIR__, 3) . '/tools/ci/*.sh') as $pfad) {
            $skripte[basename((string) $pfad)] = (string) $pfad;
        }

        $this->assertNotEmpty($skripte, 'In tools/ci/ steht kein Skript — dann prueft dieser Test nichts.');

        return $skripte;
    }

    /**
     * Zeilen einer Datei, Fortsetzungen zusammengefasst.
     *
     * @return array<int,string> Zeilennummer des Anfangs → zusammengesetzte Zeile
     */
    private function logischeZeilen(string $pfad): array
    {
        $roh      = explode("\n", (string) file_get_contents($pfad));
        $zeilen   = array();
        $puffer   = '';
        $beginn   = 0;

        foreach ($roh as $i => $zeile) {
            if ($puffer === '') {
                $beginn = $i + 1;
            }

            $puffer .= ($puffer === '' ? '' : ' ') . rtrim($zeile);

            if (substr(rtrim($zeile), -1) === '\\') {
                $puffer = rtrim($puffer, '\\');
                continue;
            }

            $zeilen[$beginn] = $puffer;
            $puffer          = '';
        }

        return $zeilen;
    }

    /**
     * Schweigt diese Zeile?
     *
     * Die vierte und fuenfte Regel sind die wichtigsten, und sie kamen erst beim Nachmessen
     * dazu: Der Schritt, der 013-005-0004 gekostet hat, schrieb NICHT nach /dev/null, sondern
     * in eine Logdatei — `composer install … > /tmp/i.log 2>&1`. Ein Detektor, der nur
     * /dev/null kennt, haette genau diesen Fall durchgelassen.
     */
    private function schweigt(string $zeile): bool
    {
        foreach (array('/dev/null', '--quiet', '--silent') as $muster) {
            if (strpos($zeile, $muster) !== false) {
                return true;
            }
        }

        // `-qq` nur als eigenstaendiges Argument, damit kein Wort mit dieser Folge anschlaegt.
        if (preg_match('/(^|\s)-qq(\s|$)/', $zeile) === 1) {
            return true;
        }

        // stderr in dieselbe Umleitung wie stdout …
        if (strpos($zeile, '2>&1') !== false) {
            return true;
        }

        // … oder stderr allein in eine Datei. `>&2` ist das Gegenteil und faellt nicht darunter.
        return preg_match('/2>\s*["\'$\/]/', $zeile) === 1;
    }

    private function istKommentar(string $zeile): bool
    {
        return strpos(ltrim($zeile), '#') === 0;
    }

    private function istAusgenommen(string $datei, string $zeile): bool
    {
        foreach (self::AUSNAHMEN[$datei] ?? array() as $eintrag) {
            if (strpos($zeile, $eintrag[0]) !== false) {
                return true;
            }
        }

        return false;
    }
}
