<?php
namespace Custom\Classes\Anmeldung;

use Areanet\PIM\Classes\Security\Anmeldeprovider;
use Areanet\PIM\Classes\Security\Bestandspruefung;
use Areanet\PIM\Classes\Security\Fremdkennung;
use Symfony\Component\HttpFoundation\Request;

/**
 * Die Vorlage für einen Anmeldeprovider — und sie läuft wirklich (`013-004-0004`).
 *
 * Ein Provider hat **eine** Pflicht: gegen das Fremdsystem prüfen. Alles andere macht das
 * Framework — Benutzer finden oder anlegen, Gruppen und Adminflag setzen, den Token ausstellen,
 * abmelden. Er fasst die Datenbank **nicht** an; das ist der Unterschied zum alten
 * `LoginManager`, der eine fertige `User`-Entity liefern musste und damit die Provisionierung
 * ins Projekt schob.
 *
 * ── Was ein echtes Projekt hier ändert ────────────────────────────────────────────────
 *
 * `pruefen()`. Dort steht in einem echten Projekt der Aufruf gegen LDAP, SAML, OIDC oder was
 * immer das Fremdsystem ist. Zurück kommt eine `Fremdkennung` mit der Kennung **im
 * Fremdsystem** — nicht dem Contentfly-Alias — und dem, was das Fremdsystem an Gruppen sagt.
 *
 * ── Was es nicht anfassen sollte ──────────────────────────────────────────────────────
 *
 * Den Rückgabetyp. `null` heisst abgelehnt, und es ist der einzige Weg, abzulehnen: Eine
 * Ausnahme führt zum selben Ergebnis, wird aber vom Framework verschluckt, damit ihre Meldung
 * nicht beim Aufrufer landet. Ein LDAP-Fehler samt Servernamen in der Antwort ist genau das,
 * was bis `013-004-0001` passierte.
 *
 * Und die Abbildung auf Contentfly-Gruppen: Die gehört in `SECURITY_PROVIDER_GRUPPEN`, nicht
 * hierher. Ein Provider sagt, was das Fremdsystem sagt.
 *
 * ── Warum diese Vorlage niemanden hereinlässt ─────────────────────────────────────────
 *
 * Sie prüft gegen eine Liste aus der Umgebungsvariablen `CONTENTFLY_BEISPIEL_PROVIDER`. Ist sie
 * nicht gesetzt, ist die Liste leer, und **jede** Anmeldung wird abgelehnt. Eine Vorlage, die
 * eine Installation versehentlich offen lässt, wäre schlimmer als gar keine.
 *
 * Das Format ist `kennung:geheimnis:gruppe|gruppe`, mehrere durch Komma getrennt. Es ist ein
 * Platzhalter für ein Fremdsystem und kein Vorschlag: Geheimnisse in einer Umgebungsvariablen
 * sind für einen Test in Ordnung und für den Betrieb nicht.
 */
final class BeispielProvider implements Anmeldeprovider, Bestandspruefung
{
    public const UMGEBUNGSVARIABLE = 'CONTENTFLY_BEISPIEL_PROVIDER';

    public function pruefen(Request $request): ?Fremdkennung
    {
        $daten    = $request->request->all();
        $kennung  = $daten['alias'] ?? null;
        $vorgezeigt = $daten['pass'] ?? null;

        if (!is_string($kennung) || !is_string($vorgezeigt) || $kennung === '') {
            return null;
        }

        foreach ($this->bekannte() as $eintrag) {
            if ($eintrag['kennung'] !== $kennung) {
                continue;
            }

            /*
             * `hash_equals()` und nicht `===`: Ein Vergleich, der beim ersten abweichenden
             * Zeichen abbricht, verrät über seine Laufzeit, wie weit man richtig lag. Bei einem
             * Geheimnis dieser Art ist das die ganze Prüfung.
             */
            if (!hash_equals($eintrag['geheimnis'], $vorgezeigt)) {
                return null;
            }

            return new Fremdkennung($eintrag['kennung'], $eintrag['gruppen']);
        }

        return null;
    }

    /**
     * Kennt das „Fremdsystem" diese Kennung noch? (`013-005-0002`)
     *
     * Die Vorlage implementiert `Bestandspruefung`, weil sie es **kann**: Ihre Liste steht in
     * der Umgebung, und darin nachzusehen braucht kein Geheimnis. Ein echter Provider kann das
     * nicht immer — ein OIDC-Provider etwa prüft einen Token, den der Client mitbringt, und hat
     * ohne ihn keine Handhabe. Dann bleibt dieses Interface weg, und `appcms:provider:abgleich`
     * überspringt ihn sichtbar.
     *
     * **Ohne konfigurierte Liste gibt es keine Auskunft, nicht „kennt niemanden".** Der
     * Unterschied entscheidet: Würde eine fehlende Konfiguration als `false` gelesen, sperrte
     * der erste Abgleich nach einem vergessenen Umgebungseintrag jeden Benutzer aus.
     */
    public function kenntKennung(string $kennung): ?bool
    {
        $roh = $_ENV[self::UMGEBUNGSVARIABLE] ?? getenv(self::UMGEBUNGSVARIABLE) ?: '';

        if (!is_string($roh) || trim($roh) === '') {
            return null;
        }

        foreach ($this->bekannte() as $eintrag) {
            if ($eintrag['kennung'] === $kennung) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{kennung: string, geheimnis: string, gruppen: list<string>}>
     */
    private function bekannte(): array
    {
        $roh = $_ENV[self::UMGEBUNGSVARIABLE] ?? getenv(self::UMGEBUNGSVARIABLE) ?: '';

        if (!is_string($roh) || trim($roh) === '') {
            return array();
        }

        $liste = array();

        foreach (explode(',', $roh) as $zeile) {
            $teile = explode(':', trim($zeile));

            if (count($teile) < 2 || $teile[0] === '' || $teile[1] === '') {
                continue;
            }

            $liste[] = array(
                'kennung'   => $teile[0],
                'geheimnis' => $teile[1],
                'gruppen'   => isset($teile[2]) && $teile[2] !== ''
                    ? explode('|', $teile[2])
                    : array(),
            );
        }

        return $liste;
    }
}
