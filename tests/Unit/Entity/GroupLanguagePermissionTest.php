<?php
namespace Tests\Unit\Entity;

use Areanet\PIM\Classes\I18nPermission;
use Areanet\PIM\Entity\Group;
use PHPUnit\Framework\TestCase;

/**
 * Charakterisierungstests für die Sprachrechte einer Gruppe.
 *
 * Bewusst als **Unit-Test**: `langIsWritable()`, `langIsTranslatable()` und
 * `langisOnlyReadable()` hängen an nichts als der Gruppe selbst — weder Datenbank noch HTTP.
 * Ein Integrationstest liefe hier ohnehin ins Leere, weil die Vorlage keine Sprachen
 * konfiguriert (`APP_LANGUAGES` ist leer) und keine konkrete `BaseI18n`-Entity mitbringt;
 * das hat `008-001-0005` festgestellt. Ein Unit-Test deckt die Logik trotzdem ab.
 *
 * **Die Vorgabe ist „erlaubt", nicht „verboten".** Ohne Sprachrechte und ohne Eintrag für
 * eine Sprache liefern alle drei Methoden das großzügige Ergebnis. Ob das beabsichtigt ist,
 * steht hier nicht zur Debatte — es ist der Ist-Zustand.
 */
class GroupLanguagePermissionTest extends TestCase
{
    private function gruppeMit(?array $sprachrechte): Group
    {
        $gruppe = new Group();

        if ($sprachrechte !== null) {
            $gruppe->setLanguages($sprachrechte);
        }

        return $gruppe;
    }

    // ── Ohne gesetzte Sprachrechte ist alles erlaubt ───────────────────────────────────

    public function testOhneSprachrechteIstJedeSpracheSchreibbar(): void
    {
        $this->assertTrue($this->gruppeMit(null)->langIsWritable('de'));
        $this->assertTrue($this->gruppeMit(null)->langIsWritable('irgendwas'));
    }

    public function testOhneSprachrechteIstJedeSpracheUebersetzbar(): void
    {
        $this->assertTrue($this->gruppeMit(null)->langIsTranslatable('de'));
    }

    public function testOhneSprachrechteIstKeineSpracheNurLesbar(): void
    {
        $this->assertFalse($this->gruppeMit(null)->langisOnlyReadable('de'),
            'langisOnlyReadable ist die Verneinung der beiden anderen');
    }

    // ── Eine Sprache ohne eigenen Eintrag bleibt erlaubt ───────────────────────────────

    public function testEineNichtAufgefuehrteSpracheIstSchreibbar(): void
    {
        // Der Punkt, der ueberrascht: Sind Sprachrechte gesetzt, eine Sprache aber nicht
        // darunter, gilt sie als schreibbar. Die Vorgabe ist "erlaubt", nicht "verboten" —
        // wer eine Sprache sperren will, muss sie ausdruecklich auffuehren.
        $gruppe = $this->gruppeMit(array('de' => I18nPermission::IS_READABLE));

        $this->assertTrue($gruppe->langIsWritable('en'),
            'en steht nicht in den Sprachrechten und ist deshalb schreibbar');
    }

    // ── Ein aufgeführter Eintrag sperrt ────────────────────────────────────────────────

    public function testEineAufgefuehrteSpracheIstNichtSchreibbar(): void
    {
        $gruppe = $this->gruppeMit(array('de' => I18nPermission::IS_READABLE));

        $this->assertFalse($gruppe->langIsWritable('de'));
    }

    public function testNurLesbarBedeutetWederSchreibbarNochUebersetzbar(): void
    {
        $gruppe = $this->gruppeMit(array('de' => I18nPermission::IS_READABLE));

        $this->assertFalse($gruppe->langIsWritable('de'));
        $this->assertFalse($gruppe->langIsTranslatable('de'));
        $this->assertTrue($gruppe->langisOnlyReadable('de'));
    }

    public function testUebersetzbarIstNichtSchreibbarAberUebersetzbar(): void
    {
        // Die dritte Stufe: uebersetzen ja, frei schreiben nein.
        $gruppe = $this->gruppeMit(array('de' => I18nPermission::IS_TRANSLATABALE));

        $this->assertFalse($gruppe->langIsWritable('de'));
        $this->assertTrue($gruppe->langIsTranslatable('de'));
        $this->assertFalse($gruppe->langisOnlyReadable('de'));
    }

    // ── Speicherung ────────────────────────────────────────────────────────────────────

    public function testSprachrechteWerdenAlsJsonGehaltenUndWiederGelesen(): void
    {
        $gruppe = $this->gruppeMit(array('de' => I18nPermission::IS_READABLE));

        $this->assertSame(array('de' => I18nPermission::IS_READABLE), $gruppe->getLanguages(),
            'setLanguages() kodiert nach JSON, getLanguages() dekodiert zurueck');
    }

    public function testEinLeererWertLaesstDieSprachrechteUngesetzt(): void
    {
        // setLanguages() ignoriert falsy Werte — ein leeres Array loescht die Rechte also
        // NICHT, es laesst sie unberuehrt. Festgehalten, nicht bewertet.
        $gruppe = new Group();
        $gruppe->setLanguages(array('de' => I18nPermission::IS_READABLE));
        $gruppe->setLanguages(array());

        $this->assertSame(array('de' => I18nPermission::IS_READABLE), $gruppe->getLanguages(),
            'Ein leeres Array setzt die Sprachrechte nicht zurueck');
    }
}
