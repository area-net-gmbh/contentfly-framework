<?php
namespace Tests\Unit\Migration;

use PHPUnit\Framework\TestCase;

/**
 * Der Wächter über die Vollständigkeit des Migrationsleitfadens (007-004-0003).
 *
 * ## Was die Story verlangt
 * „Jeder der Brüche ist im Leitfaden erreichbar — entweder als eigener Schritt oder als Verweis
 * ins Register, und **keiner fällt heraus**."
 *
 * ## Warum das eine Prüfung braucht
 * **Eine Sorgfaltsfrage bleibt es nur so lange, bis sie einmal schiefgeht.** Das Register wächst
 * mit jeder Story — in Epic `007` allein sind vier Einträge und ein Abschnitt dazugekommen.
 * Ohne Prüfung fällt ein neuer Abschnitt genau dann heraus, wenn niemand mehr daran denkt.
 *
 * ## Abschnittsweise, und der Preis steht im Leitfaden
 * Geprüft wird, dass jeder **Abschnitt** des Registers einer Phase zugeordnet ist — nicht jeder
 * Eintrag. Entschieden am 2026-09-11: 14 Zuordnungen statt 100 Markierungen. Ein neuer Eintrag
 * in einem bereits zugeordneten Abschnitt gilt damit als abgedeckt; das ist eine Entscheidung
 * und keine Selbstverständlichkeit, und sie steht im Leitfaden.
 *
 * ## Beide Richtungen
 * - **Jeder Abschnitt ist zugeordnet** — sonst führt der Leitfaden an ihm vorbei.
 * - **Jede Zuordnung trifft einen Abschnitt** — sonst führt sie ins Leere, nachdem der
 *   Abschnitt umbenannt oder aufgelöst wurde. Dieselbe Regel wie bei den Gates aus `006-005`.
 */
class MigrationsleitfadenTest extends TestCase
{
    public function testJederRegisterAbschnittIstEinerPhaseZugeordnet(): void
    {
        $zugeordnet = $this->zuordnung();

        $ohnePhase = array_values(array_diff($this->registerAbschnitte(), $zugeordnet));

        sort($ohnePhase);

        $this->assertSame(array(), $ohnePhase, implode("\n", array_merge(
            array(
                'Diese Abschnitte von breaking-changes.md sind im Migrationsleitfaden keiner',
                'Phase zugeordnet — der Leitfaden fuehrt an ihnen vorbei (007-004-0003):',
                '',
            ),
            $ohnePhase,
            array(
                '',
                'In an_project/docs/migration.md in die Tabelle "Welcher Register-Abschnitt in',
                'welcher Phase" eintragen. Passt der Abschnitt in keine der Phasen, gehoert die',
                'Phasenliste erweitert — und nicht der Eintrag hineingezwaengt.',
            )
        )));
    }

    public function testJedeZuordnungTrifftEinenAbschnitt(): void
    {
        $abschnitte = $this->registerAbschnitte();

        $insLeere = array_values(array_diff($this->zuordnung(), $abschnitte));

        sort($insLeere);

        $this->assertSame(array(), $insLeere, implode("\n", array_merge(
            array(
                'Diese Zuordnungen im Migrationsleitfaden zeigen auf Abschnitte, die es in',
                'breaking-changes.md nicht (mehr) gibt:',
                '',
            ),
            $insLeere,
            array(
                '',
                'Eine Zuordnung ins Leere ist schlimmer als keine: Sie sieht aus wie ein Weg.',
                'Entweder der Abschnitt heisst anders — dann hier nachziehen — oder er ist weg,',
                'dann faellt die Zeile mit.',
            )
        )));
    }

    /**
     * Und die Zahl im Kopf des Leitfadens stimmt noch.
     *
     * Sie ist die einzige Stelle, an der der Leitfaden eine Grösse des Registers behauptet —
     * und eine Zahl, die nicht mehr stimmt, ist derselbe Defekt wie ein Verweis, der ins Leere
     * zeigt. Sie trägt ihr Datum, damit sichtbar bleibt, worauf sie sich bezieht.
     */
    public function testDieAngegebenenGroessenStimmenNoch(): void
    {
        $leitfaden = $this->lesen('an_project/docs/migration.md');
        $register  = $this->lesen('an_project/docs/breaking-changes.md');

        $eintraege  = substr_count($register, "\n### ");
        $abschnitte = count($this->registerAbschnitte());

        $this->assertStringContainsString(
            sprintf('**%d Einträge in %d Abschnitten**', $eintraege, $abschnitte),
            $leitfaden,
            sprintf(
                "Der Leitfaden nennt eine andere Groesse als das Register hat.\n"
                ."Gezaehlt: %d Eintraege in %d Abschnitten.\n"
                .'Die Zahl im Kopf von migration.md gehoert nachgezogen, samt ihrem Datum.',
                $eintraege,
                $abschnitte
            )
        );
    }

    /**
     * Die Abschnitte des Registers — die `##`-Überschriften.
     *
     * @return array<int,string>
     */
    private function registerAbschnitte(): array
    {
        $gefunden = array();

        foreach (explode("\n", $this->lesen('an_project/docs/breaking-changes.md')) as $zeile) {
            if (str_starts_with($zeile, '## ')) {
                $gefunden[] = trim(substr($zeile, 3));
            }
        }

        $this->assertNotEmpty($gefunden, 'Das Register hat keine Abschnitte — dann prueft dieser Test nichts.');

        return $gefunden;
    }

    /**
     * Die Abschnitte, die der Leitfaden einer Phase zuordnet.
     *
     * Gelesen wird die Tabelle *Welcher Register-Abschnitt in welcher Phase*: Zeilen der Form
     * `| 3 | A · B |`. Mehrere Abschnitte in einer Zelle trennt das Mittelpunkt-Zeichen; die
     * kursive Notiz für eine Phase ohne Abschnitt wird übergangen.
     *
     * @return array<int,string>
     */
    private function zuordnung(): array
    {
        $zeilen = explode("\n", $this->lesen('an_project/docs/migration.md'));
        $inTabelle = false;
        $gefunden  = array();

        foreach ($zeilen as $zeile) {
            if (str_starts_with($zeile, '| Phase | Register-Abschnitt |')) {
                $inTabelle = true;
                continue;
            }

            if (!$inTabelle) {
                continue;
            }

            if (!str_starts_with($zeile, '|')) {
                break;
            }

            $zellen = array_map('trim', explode('|', trim($zeile, '|')));

            if (count($zellen) < 2 || !ctype_digit($zellen[0])) {
                continue;
            }

            foreach (explode('·', $zellen[1]) as $eintrag) {
                $eintrag = trim($eintrag);

                // Die kursive Notiz einer Phase ohne Abschnitt.
                if ($eintrag === '' || str_starts_with($eintrag, '*(')) {
                    continue;
                }

                $gefunden[] = $eintrag;
            }
        }

        $this->assertNotEmpty($gefunden, 'Im Leitfaden wurde keine Zuordnung gefunden — dann prueft dieser Test nichts.');

        return $gefunden;
    }

    private function lesen(string $relativ): string
    {
        $pfad = dirname(__DIR__, 3) . '/' . $relativ;

        $this->assertFileExists($pfad);

        return (string) file_get_contents($pfad);
    }
}
