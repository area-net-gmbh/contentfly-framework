<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Wacht über die Bedingung, an der die Autoloader-Entscheidung aus `006-004-0001` hängt.
 *
 * **Die Entscheidung:** zwei Composer-Bäume, Root zuerst, `custom/` ergänzend; bei einem
 * gemeinsamen PSR-4-Präfix gewinnt der Root. Begründung und verworfene Alternative stehen in
 * `an_project/docs/architecture.md` unter *Key decisions*, 2026-09-09.
 *
 * **Die Bedingung:** dass sich die beiden Bäume nicht überschneiden. Ohne sie ist „der Root
 * gewinnt" keine Zusicherung, sondern eine Zufälligkeit der Ladereihenfolge — und dann gewinnt
 * regelmäßig die *ältere* Fassung, weil der Root zuerst kommt.
 *
 * **Warum es diesen Test gibt.** Die Bedingung war jahrelang verletzt, ohne dass es auffiel:
 * `psr/log` lag in 1.1.3 und 3.0.2 gleichzeitig im Prozess, dazu `symfony/polyfill-ctype` und
 * `-mbstring` in unvereinbaren Ständen. Aufgefallen ist es erst, als `006-001-0002` beide Bäume
 * ausgezählt hat. Eine Bedingung, die niemand prüft, ist keine Zusicherung.
 *
 * **Warum als Unit-Test und nicht im Bootstrap.** Drei Orte kamen in Frage:
 *
 * | Ort                          | fängt                          | kostet                    |
 * |------------------------------|--------------------------------|---------------------------|
 * | Unit-Test (hier)             | jeden Testlauf, lokal und CI   | nichts zur Laufzeit       |
 * | Prüfung im Bootstrap         | jeden Request                  | Laufzeit in Produktion    |
 * | Skript in `tools/`, via CI   | jeden CI-Lauf                  | ein weiterer Job-Schritt  |
 *
 * Der Unit-Test gewinnt, weil er nichts kostet, was der Anwendung gehört, ohne Datenbank läuft
 * und Teil der Suite ist, die Epic `008` als Abnahmegrundlage festgeschrieben hat — er wandert
 * also mit ihr mit. Eine Prüfung im Bootstrap zahlte jeder Request für einen Fehler, der beim
 * Installieren entsteht, nicht beim Ausliefern.
 *
 * **Verglichen werden Präfixe, nicht Paketnamen.** Der Grund ist der PHPMailer-Fall: Der Root
 * registrierte `PHPMailer\PHPMailer\` aus einem von Hand hineinkopierten Verzeichnis, das in
 * **keiner** `installed.json` stand. Eine Prüfung über Paketnamen hätte genau die Überschneidung
 * verfehlt, die am längsten bestand.
 */
class AutoloaderUeberschneidungTest extends TestCase
{
    private const WURZEL = __DIR__ . '/../..';

    /**
     * Die Autoloader-Karten eines Baums.
     *
     * Alle drei Arten, auf die Composer eine Klasse findet. Fehlt eine, entsteht genau die
     * Lücke, die der PHPMailer-Fall war.
     *
     * **Composers eigene Laufzeitklassen bleiben aussen vor**, und zwar über ihren *Quellpfad*,
     * nicht über ihren Namen: Jeder generierte Baum trägt eine eigene
     * `vendor/composer/InstalledVersions.php`. Diese Doppelung ist keine Aussage über die
     * Manifeste — sie entsteht bei jedem `composer install` und liesse sich nur abstellen,
     * indem man auf den zweiten Baum verzichtet. Ausgefiltert wird deshalb, was in das
     * `composer/`-Verzeichnis **desselben** Baums zeigt; ein Paket, das zufällig `Composer\`
     * heisst, bliebe damit weiterhin sichtbar.
     *
     * @return array<string,array<int,string>> Kartenname => Schlüssel
     */
    private function karten(string $vendor): array
    {
        $karten = array(
            'PSR-4'    => '/composer/autoload_psr4.php',
            'PSR-0'    => '/composer/autoload_namespaces.php',
            'Classmap' => '/composer/autoload_classmap.php',
        );

        $eigenesGeruest = realpath($vendor . '/composer');

        $ergebnis = array();
        foreach ($karten as $name => $datei) {
            $pfad = $vendor . $datei;
            if (!is_file($pfad)) {
                $ergebnis[$name] = array();
                continue;
            }

            $karte = require $pfad;

            if ($name === 'Classmap' && $eigenesGeruest !== false) {
                $karte = array_filter(
                    $karte,
                    static function ($ziel) use ($eigenesGeruest) {
                        $echt = realpath((string) $ziel);

                        return $echt === false || !str_starts_with($echt, $eigenesGeruest . DIRECTORY_SEPARATOR);
                    }
                );
            }

            $ergebnis[$name] = array_keys($karte);
        }

        return $ergebnis;
    }

    public function testDieBeidenBaeumeUeberschneidenSichNicht(): void
    {
        $root   = self::WURZEL . '/vendor';
        $custom = self::WURZEL . '/custom/vendor';

        self::assertDirectoryExists(
            $root,
            'Ohne vendor/ laeuft nichts — "composer install" fehlt (an_project/docs/runbook.md, Schritt 1).'
        );

        /*
         * Kein markTestSkipped(), wenn der zweite Baum fehlt.
         *
         * Fehlt custom/vendor, gibt es keine Ueberschneidung — das ist ein bestandener Test,
         * nicht ein nicht durchgefuehrter. Ein Uebersprung waere hier derselbe stille
         * Durchwinker, gegen den 008-005-0002 den Umgebungswaechter gebaut hat: gruen, ohne
         * geprueft zu haben.
         *
         * Der Fall ist der Normalfall. Seit 006-004-0002 ist custom/composer.json leer; ein
         * "composer install" darin erzeugt trotzdem ein custom/vendor/autoload.php samt Karten,
         * nur eben ohne Paket darin. Beide Zustaende — Verzeichnis da, Verzeichnis weg — sind
         * gueltig und muessen gruen sein.
         */
        $rootKarten   = $this->karten($root);
        $customKarten = $this->karten($custom);

        $gefunden = array();
        foreach ($rootKarten as $name => $schluessel) {
            $doppelt = array_intersect($schluessel, $customKarten[$name]);
            foreach ($doppelt as $eintrag) {
                $gefunden[] = $name . ': ' . $eintrag;
            }
        }

        self::assertSame(
            array(),
            $gefunden,
            "Root und custom/ registrieren dieselben Autoloader-Eintraege. Der zuerst geladene "
            . "Baum gewinnt — also immer der Root, auch wenn custom/ die neuere Fassung haette.\n"
            . "Doppelt:\n  " . implode("\n  ", $gefunden) . "\n\n"
            . "Ein Paket, das lib/ oder die ausgelieferte Vorlage benutzt, gehoert ins "
            . "Root-Manifest und NICHT nach custom/. Die Einordnungsregel steht in "
            . "tools/dependency-assignment.json, die Entscheidung in "
            . "an_project/docs/architecture.md (Key decisions, 2026-09-09)."
        );
    }

    /**
     * Belegt, dass der Vergleich ueberhaupt etwas zu vergleichen hat.
     *
     * Ohne diese Zusicherung koennte der Test oben aus dem falschen Grund gruen sein — etwa
     * weil ein Pfad nicht stimmt und beide Seiten leer bleiben. Dann pruefte er nichts und
     * saehe aus wie bestanden.
     */
    public function testDieRootKartenSindNichtLeer(): void
    {
        $karten = $this->karten(self::WURZEL . '/vendor');

        self::assertNotEmpty(
            array_merge($karten['PSR-4'], $karten['PSR-0'], $karten['Classmap']),
            'Die Autoloader-Karten des Root-Baums sind leer. Dann vergleicht der Ueberschneidungs'
            . '-Test nichts und ist aus dem falschen Grund gruen.'
        );
    }
}
