<?php
namespace Tests\Unit\Migration;

use PHPUnit\Framework\TestCase;

/**
 * Die Migrationsregel für das `Entity/`-Verzeichnis (Story `007-002`).
 *
 * ## Warum die Regel einen Test hat und nicht nur eine Anleitung
 * Eine Rector-Regel lässt sich nicht daran messen, dass ihr Lauf durchgeht — nur daran, was
 * danach im Code steht. Und eine Regel ohne Test verfällt wie eine Anleitung, der niemand
 * folgt: Sie wird beim nächsten Rector-Update still falsch, und auffallen würde es erst bei
 * dem Projekt, das sie benutzt.
 *
 * ## Der Aufbau
 * `tests/Fixtures/RectorMigration/alt/` trägt Beispiel-Entities im Altstand,
 * `…/soll/` den **von Hand geschriebenen** Zielzustand. Von Hand ist der Punkt: Aus einem
 * Rector-Lauf erzeugt, prüfte der Vergleich die Regel gegen sich selbst.
 *
 * ## Was dieser Test mit `007-002-0001` prüft
 * Den Rahmen, nicht die Regel — die gibt es noch nicht. Dass der Prüfstein wirklich Altstand
 * ist, dass der Sollzustand wirklich Zielzustand ist, dass beide sich unterscheiden, und dass
 * der Lauf durchgeht. **Die Vergleiche Stück für Stück kommen mit `0002` bis `0004`**, und die
 * Gleichheit von Ist und Soll mit `0004`.
 */
class RectorRegelTest extends TestCase
{
    /**
     * Die sieben mit Epic `012` gestrichenen Annotationen.
     *
     * Quelle: `an_project/docs/pim-annotationen-migration.md`, Abschnitt 1. Die Liste steht
     * hier ein zweites Mal — und `testDieListenStimmenNochMitDerDokumentationUeberein()` hält
     * fest, dass die beiden nicht auseinanderlaufen.
     */
    private const GESTRICHENE_ANNOTATIONEN = array(
        'Rte', 'Textarea', 'Datetime', 'Time', 'Password', 'MatrixChooser', 'EntitySelector',
    );

    /** Die 14 gestrichenen Felder von `@PIM\Config`. Quelle: dieselbe Datei, Abschnitt 3. */
    private const GESTRICHENE_FELDER = array(
        'viewMode', 'showInList', 'listShorten', 'hide', 'label', 'tab', 'tabs', 'sort',
        'isDatalist', 'isSidebar', 'lines', 'accept', 'readonly', 'filter',
    );

    /**
     * Die Felder, die `@PIM\Checkbox` und `@PIM\Radio` verloren haben.
     *
     * Quelle: `pim-annotationen-migration.md`, Abschnitt 2. Sie stehen getrennt von den
     * `Config`-Feldern, weil sie an anderen Annotationen hingen — und weil die Klassen selbst
     * der Beleg sind: Beide haben heute genau einen Konstruktorparameter, `group`.
     */
    private const GESTRICHENE_FELDER_AUSWAHL = array(
        'horizontalAlignment', 'columns', 'select',
    );

    /**
     * Die Annotationen, die bleiben. Quelle: dieselbe Datei, Abschnitt 2.
     *
     * `Checkbox` und `Radio` sind dabei — sie bleiben, verlieren aber Felder.
     */
    private const GEBLIEBENE_ANNOTATIONEN = array(
        'Config', 'Select', 'Virtualjoin', 'Permissions', 'I18nPermissions', 'Checkbox', 'Radio',
    );

    /** Die 10 gebliebenen Felder. Quelle: dieselbe Datei, Abschnitt 4. */
    private const GEBLIEBENE_FELDER = array(
        'excludeFromSync', 'encoded', 'isFilterable', 'unique', 'type', 'i18n_universal',
        'sortRestrictTo', 'sortBy', 'sortOrder', 'labelProperty',
    );

    /**
     * Der Prüfstein ist wirklich Altstand.
     *
     * Ohne diese Zusicherung wären alle folgenden Vergleiche vakuum: Eine Regel, die gegen
     * bereits migrierten Code läuft, ist immer erfolgreich.
     */
    public function testDerPruefsteinTraegtJedeGestricheneAnnotation(): void
    {
        $alt = $this->inhalt('alt');

        foreach (self::GESTRICHENE_ANNOTATIONEN as $annotation) {
            $this->assertStringContainsString(
                '@PIM\\' . $annotation,
                $alt,
                sprintf('Der Pruefstein muss @PIM\\%s tragen — sonst prueft die Regel sie gegen nichts.', $annotation)
            );
        }

        $this->assertStringContainsString('@ORM\\Column', $alt);
        $this->assertStringContainsString('@ORM\\JoinTable', $alt, 'Der verschachtelte Fall fehlt.');
    }

    public function testDerPruefsteinTraegtJedesGestricheneUndJedesGebliebeneFeld(): void
    {
        $alt = $this->inhalt('alt');

        foreach (array_merge(self::GESTRICHENE_FELDER, self::GEBLIEBENE_FELDER) as $feld) {
            $this->assertMatchesRegularExpression(
                '/\b' . preg_quote($feld, '/') . '\s*=/',
                $alt,
                sprintf('Das Feld %s fehlt im Pruefstein.', $feld)
            );
        }
    }

    /**
     * Und der Sollzustand ist wirklich Zielzustand.
     *
     * Geprüft wird der **Code**, nicht die Kommentare: Dort stehen die Namen der gestrichenen
     * Annotationen absichtlich, weil der Kommentar erklärt, was mit ihnen passiert ist.
     */
    public function testDerSollzustandTraegtNichtsGestrichenesMehr(): void
    {
        $soll = $this->nurCode($this->inhalt('soll'));

        foreach (self::GESTRICHENE_ANNOTATIONEN as $annotation) {
            $this->assertStringNotContainsString('PIM\\' . $annotation, $soll);
        }

        foreach (self::GESTRICHENE_FELDER as $feld) {
            $this->assertDoesNotMatchRegularExpression('/\b' . preg_quote($feld, '/') . '\s*:/', $soll);
        }

        $this->assertStringNotContainsString('@ORM\\', $soll, 'Im Sollzustand steht keine ORM-Annotation mehr.');
        $this->assertStringContainsString('#[ORM\\Column', $soll);
    }

    /**
     * Die gebliebenen Felder stehen im Sollzustand noch — alle zehn.
     *
     * Das ist die Gegenprobe zum Test darüber. Ein Sollzustand, in dem auch die gebliebenen
     * Felder fehlen, wäre bequem zu erreichen und falsch.
     */
    public function testDerSollzustandTraegtJedesGebliebeneFeldNoch(): void
    {
        $soll = $this->nurCode($this->inhalt('soll'));

        foreach (self::GEBLIEBENE_FELDER as $feld) {
            $this->assertMatchesRegularExpression(
                '/\b' . preg_quote($feld, '/') . '\s*:/',
                $soll,
                sprintf('Das Feld %s muss den Lauf ueberstehen, steht aber nicht im Sollzustand.', $feld)
            );
        }
    }

    public function testAltUndSollUnterscheidenSich(): void
    {
        $this->assertNotSame(
            $this->inhalt('alt'),
            $this->inhalt('soll'),
            'Waeren beide gleich, gaebe es nichts zu migrieren und der Vergleich sagte nichts.'
        );
    }

    /**
     * Der Lauf geht durch und hat etwas zu tun.
     *
     * **Dieser Test ist umgedreht worden (007-002-0002).** Mit `0001` war noch keine Regel
     * eingetragen, und er hielt fest, dass Rector genau darüber warnt („Register rules or
     * sets"). Diese Zusicherung ist mit der ersten eingetragenen Regel gegenstandslos
     * geworden — und eine Zusicherung, die nicht mehr stimmt, ist ein Defekt, kein
     * Altbestand.
     *
     * Jetzt gilt die Umkehrung: Rector **muss** etwas vorschlagen. Ein Lauf, der nichts
     * findet, hiesse, dass die Regel nicht greift oder der Prüfstein nicht mehr Altstand ist.
     */
    public function testDerLaufGehtDurchUndHatEtwasZuTun(): void
    {
        $ergebnis = $this->rectorFahren();

        /*
         * EINE FALLE, UND SIE IST GENAU DIE VERKEHRTE RICHTUNG.
         *
         * Rector beendet einen `--dry-run` mit **Exit 2**, wenn er Aenderungen gefunden hat,
         * und mit 0, wenn nichts zu tun war. Ein Test, der hier `assertSame(0, …)` schreibt —
         * und das war mein erster Entwurf —, ist also genau dann gruen, wenn die Regel NICHT
         * greift.
         *
         * Beim anwendenden Lauf (ohne --dry-run) ist es umgekehrt: 0 heisst erledigt.
         */
        $this->assertSame(
            2,
            $ergebnis['code'],
            "Ein Trockenlauf mit Funden endet mit 2. Kam 0, hat die Regel nichts gefunden:\n"
            . $ergebnis['ausgabe']
        );

        $this->assertStringNotContainsString(
            'Register rules or sets',
            $ergebnis['ausgabe'],
            'Es ist eine Regel eingetragen — die Warnung darf nicht mehr kommen.'
        );

        $this->assertStringContainsString(
            'would have been changed',
            $ergebnis['ausgabe'],
            'Rector findet nichts zu tun. Entweder greift die Regel nicht, oder der Pruefstein '
            .'ist kein Altstand mehr.'
        );
    }

    /**
     * Nach dem Lauf steht keine ORM-Annotation mehr da (007-002-0002).
     *
     * Das ist die erste Hälfte der Regel, und die einzige, für die es fertige Regeln gibt:
     * `DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES`.
     */
    public function testNachDemLaufStehtKeineOrmAnnotationMehrDa(): void
    {
        $ergebnis = $this->rectorFahren(true);

        $this->assertSame(0, $ergebnis['code'], $ergebnis['ausgabe']);

        foreach ($ergebnis['dateien'] as $name => $inhalt) {
            $this->assertDoesNotMatchRegularExpression(
                '/@\\w+\\\\(Entity|Table|Column|ManyToOne|OneToOne|OneToMany|JoinColumn|JoinTable)\\b/',
                $this->wirksameZeilen($inhalt),
                sprintf('In %s steht nach dem Lauf noch eine ORM-Annotation.', $name)
            );

            /*
             * Alias-unabhaengig: Die Alias-Probe schreibt `#[Abbildung\Column`, und Rector
             * raeumt Importe nicht um. Eine Pruefung auf `ORM` ginge daran vorbei.
             */
            $this->assertMatchesRegularExpression(
                '/#\\[\\w+\\\\Column/',
                $inhalt,
                sprintf('In %s ist kein ORM-Attribut entstanden.', $name)
            );
        }
    }

    /**
     * Der verschachtelte Fall — der, an dem eine Umstellung erfahrungsgemäss scheitert.
     *
     * Aus `@ORM\JoinTable(joinColumns={@ORM\JoinColumn(…)}, inverseJoinColumns={…})` müssen
     * drei eigenständige Attribute werden. Der Task hat das als Messung verlangt und nicht als
     * Annahme — hier ist sie.
     */
    public function testDerVerschachtelteFallWirdRichtigUmgesetzt(): void
    {
        $ergebnis = $this->rectorFahren(true);
        $rubrik   = $ergebnis['dateien']['Rubrik.php'] ?? '';

        $this->assertNotSame('', $rubrik, 'Rubrik.php fehlt im Ergebnis.');

        $this->assertStringContainsString("#[ORM\\JoinTable(name: 'fixture_rubrik_artikel')]", $rubrik);
        $this->assertStringContainsString("#[ORM\\JoinColumn(name: 'rubrik_id', referencedColumnName: 'id')]", $rubrik);
        $this->assertStringContainsString("#[ORM\\InverseJoinColumn(name: 'artikel_id', referencedColumnName: 'id')]", $rubrik);

        $this->assertStringNotContainsString('joinColumns=', $rubrik, 'Die verschachtelte Form steht noch da.');
        $this->assertStringNotContainsString('inverseJoinColumns=', $rubrik);
    }

    /**
     * Keine der sieben gestrichenen Annotationen bleibt stehen (007-002-0003).
     */
    public function testNachDemLaufBleibtKeineGestricheneAnnotationStehen(): void
    {
        $ergebnis = $this->rectorFahren(true);

        $übrig = array();

        foreach ($ergebnis['dateien'] as $name => $inhalt) {
            $wirksam = $this->wirksameZeilen($inhalt);

            foreach (self::GESTRICHENE_ANNOTATIONEN as $annotation) {
                if (preg_match('/@\\w+\\\\' . $annotation . '\\b/', $wirksam) === 1) {
                    $übrig[] = $name . ': ' . $annotation;
                }
            }
        }

        $this->assertSame(array(), $übrig, implode("\n", array_merge(
            array(
                'Diese gestrichenen Annotationen stehen nach dem Lauf noch da. Ein',
                'stehengebliebenes Feld ist kein geduldetes Relikt: Der AnnotationReader',
                'bricht schon beim Einlesen ab, und das Projekt startet nicht (007-002-0003).',
                '',
            ),
            $übrig
        )));
    }

    /**
     * Auch unter einem fremden Alias — und das ist der Grund für die Konfiguration mit dem
     * vollqualifizierten Namen.
     *
     * `AndererAlias.php` importiert `Areanet\PIM\Classes\Annotations as Anders`. Wäre die
     * Regel auf `PIM\Rte` konfiguriert, ginge sie hier vorbei — und ein Projekt mit eigenem
     * Alias hielte den Lauf für vollständig.
     */
    public function testDieRegelGreiftAuchUnterEinemFremdenAlias(): void
    {
        $ergebnis = $this->rectorFahren(true);
        $datei    = $ergebnis['dateien']['AndererAlias.php'] ?? '';

        $this->assertNotSame('', $datei, 'Die Alias-Probe fehlt im Ergebnis.');

        $wirksam = $this->wirksameZeilen($datei);

        $this->assertStringNotContainsString('@Anders\\Rte', $wirksam);
        $this->assertStringNotContainsString('@Anders\\Password', $wirksam);

        // Und der Alias des Projekts bleibt: die Regel raeumt keine Importe um.
        $this->assertStringContainsString('#[Abbildung\\Column', $datei);
        $this->assertStringContainsString('#[Anders\\Config', $datei);
    }

    /**
     * Die gebliebenen Annotationen sind unangetastet — **Zeichen für Zeichen**.
     *
     * Das ist die wichtigere Hälfte dieses Tasks. Eine Regel, die nur daran gemessen wird, was
     * sie entfernen soll, kann alles andere mit abräumen, ohne dass es auffällt.
     *
     * Ihre gestrichenen FELDER stehen hier noch drin — die fallen erst mit `007-002-0004`.
     */
    public function testDieGebliebenenAnnotationenSindUnangetastet(): void
    {
        $ergebnis = $this->rectorFahren(true);

        $vorher  = $this->pimAngaben($this->inhalt('alt'));
        $nachher = array();

        /*
         * KEIN array_merge: Bei Zeichenketten-Schluesseln ERSETZT es, statt anzuhaengen — mein
         * erster Entwurf verglich damit nur die letzte Datei und war fuer die anderen blind.
         */
        foreach ($ergebnis['dateien'] as $inhalt) {
            foreach ($this->pimAngaben($inhalt) as $annotation => $angaben) {
                foreach ($angaben as $angabe) {
                    $nachher[$annotation][] = $angabe;
                }
            }
        }

        foreach (self::GEBLIEBENE_ANNOTATIONEN as $annotation) {
            $this->assertSame(
                $vorher[$annotation] ?? array(),
                $nachher[$annotation] ?? array(),
                sprintf(
                    '@PIM\%s hat sich geaendert. Diese Annotation bleibt, und ihre Felder '
                    .'fallen erst mit 007-002-0004.',
                    $annotation
                )
            );
        }
    }

    /**
     * **Zwei Läufe sind nötig, und der zweite ist kein Komfort** (007-002-0004).
     *
     * Die Regel, die Felder aus Attributen entfernt, sieht Attribute — und die entstehen erst,
     * wenn `AnnotationToAttributeRector` im selben Lauf die Annotation umgeschrieben hat. Ein
     * Rector-Durchgang wendet die Regeln auf den Baum an, den er vorgefunden hat; was eine
     * Regel neu erzeugt, erreicht eine andere erst im nächsten.
     *
     * **Wer nur einmal läuft, hat einen kaputten Baum, nicht einen halb migrierten:** Dort
     * steht dann `#[PIM\Config(label: 'Artikel')]`, und `Config::__construct()` hat kein
     * `$label` — „Unknown named parameter $label", ein Fatal Error beim Laden der Entity.
     *
     * Dieser Test hält den Fixpunkt fest: Nach dem zweiten Lauf ist nichts mehr zu tun. Damit
     * steht die Abbruchbedingung als geprüfte Zusicherung da und nicht als Ratschlag —
     * **laufen, bis Rector nichts mehr meldet.**
     */
    public function testZweiLaeufeSindNoetigUndDerDritteFindetNichtsMehr(): void
    {
        $ziel = $this->kopieAnlegen();

        $erster = $this->rectorAufrufen($ziel, true);
        $this->assertSame(0, $erster['code'], $erster['ausgabe']);

        $zweiter = $this->rectorAufrufen($ziel, true);
        $this->assertSame(0, $zweiter['code'], $zweiter['ausgabe']);
        $this->assertStringContainsString(
            'have been changed',
            $zweiter['ausgabe'],
            'Der zweite Lauf muss noch etwas zu tun haben — sonst greift die Feldregel nie.'
        );

        $dritter = $this->rectorAufrufen($ziel, false);
        $this->assertSame(
            0,
            $dritter['code'],
            "Nach zwei Laeufen ist der Fixpunkt erreicht; ein dritter darf nichts mehr finden:\n"
            . $dritter['ausgabe']
        );

        $this->aufraeumen($ziel);
    }

    /**
     * Kein gestrichenes Feld bleibt übrig — der Kern dieses Tasks.
     */
    public function testNachDemFixpunktBleibtKeinGestrichenesFeld(): void
    {
        foreach ($this->bisZumFixpunkt() as $name => $inhalt) {
            foreach ($this->pimAngaben($inhalt) as $annotation => $angaben) {
                foreach ($angaben as $angabe) {
                    foreach (array_merge(self::GESTRICHENE_FELDER, self::GESTRICHENE_FELDER_AUSWAHL) as $feld) {
                        $this->assertDoesNotMatchRegularExpression(
                            '/\b' . preg_quote($feld, '/') . '=/',
                            $angabe,
                            sprintf(
                                'In %s traegt @PIM\%s noch das gestrichene Feld %s. Das ist kein '
                                .'Relikt: Der Konstruktor kennt es nicht, und das Laden der Entity '
                                .'bricht mit "Unknown named parameter" ab.',
                                $name,
                                $annotation,
                                $feld
                            )
                        );
                    }
                }
            }
        }
    }

    /**
     * Und kein gebliebenes Feld ist verloren gegangen — die Gegenprobe.
     *
     * Sie ist die wichtigere Hälfte: Eine Regel, die zu viel entfernt, fällt beim Test auf das
     * Entfernen nicht auf.
     */
    public function testNachDemFixpunktStehenAlleGebliebenenFelderNoch(): void
    {
        $alle = implode("\n", $this->bisZumFixpunkt());

        foreach (self::GEBLIEBENE_FELDER as $feld) {
            $this->assertMatchesRegularExpression(
                '/\b' . preg_quote($feld, '/') . '\s*:/',
                $alle,
                sprintf('Das Feld %s ist beim Lauf verloren gegangen.', $feld)
            );
        }

        // `group` bleibt an Checkbox und Radio — das einzige Feld, das beide behalten.
        $this->assertSame(
            2,
            preg_match_all('/group:/', $alle),
            'group muss an Checkbox UND Radio stehen bleiben.'
        );
    }

    /**
     * Das Ergebnis entspricht dem von Hand geschriebenen Sollzustand.
     *
     * **Verglichen wird je Element, nicht je Zeile.** Die Reihenfolge der Attribute an einer
     * Klasse oder Eigenschaft trägt keine Aussage — Doctrine liest sie als Liste, und Rector
     * setzt sie in seiner eigenen Ordnung. Ein Vergleich, der auch darauf besteht, wäre rot
     * wegen einer Nichtaussage.
     *
     * Dass das Attribut am **richtigen** Element hängt, prüft der Vergleich trotzdem: Der
     * Schlüssel ist der Name der Klasse oder der Eigenschaft.
     */
    public function testDasErgebnisEntsprichtDemSollzustand(): void
    {
        $ergebnis = $this->bisZumFixpunkt();

        foreach ($ergebnis as $name => $inhalt) {
            $soll = (string) file_get_contents($this->fixtures('soll') . '/' . $name);

            $this->assertSame(
                $this->attributeJeElement($soll),
                $this->attributeJeElement($inhalt),
                sprintf(
                    '%s weicht vom Sollzustand ab. Der Sollzustand ist von Hand geschrieben — '
                    .'weicht die Regel ab, ist die Regel zu pruefen, nicht die Vorlage.',
                    $name
                )
            );
        }
    }

    /**
     * **Jedes Attribut des Ergebnisses lässt sich wirklich instanziieren.**
     *
     * Das ist die Verification dieses Tasks in ausführbarer Form, und sie prüft etwas, das
     * kein Textvergleich prüfen kann: Ein Attribut mit einem Feld, das der Konstruktor nicht
     * kennt, ist syntaktisch einwandfrei und wirft erst beim Laden — „Unknown named parameter".
     * Genau der Zustand, den ein Projekt nach nur einem Lauf hätte.
     *
     * PHPStan deckt die andere Seite ab: Es prüft den **Sollzustand** gegen die Konstruktoren.
     * Dieser Test prüft, was die **Regel** tatsächlich erzeugt.
     *
     * Die Dateien werden dafür in einen eigenen Namensraum umgeschrieben — `alt/` und `soll/`
     * tragen denselben Klassennamen, und ohne die Umbenennung kollidierten sie im Prozess.
     */
    public function testJedesAttributDesErgebnissesLaesstSichInstanziieren(): void
    {
        $gezaehlt = 0;

        foreach ($this->bisZumFixpunkt() as $name => $inhalt) {
            $eigen  = 'ProbeRector' . bin2hex(random_bytes(4));
            $quelle = (string) preg_replace('/namespace [^;]+;/', 'namespace ' . $eigen . ';', $inhalt, 1);

            $tmp = sys_get_temp_dir() . '/' . $eigen . '-' . $name;
            file_put_contents($tmp, $quelle);
            require $tmp;
            unlink($tmp);

            $klasse  = $eigen . '\\' . basename($name, '.php');
            $spiegel = new \ReflectionClass($klasse);

            $attribute = $spiegel->getAttributes();

            foreach ($spiegel->getProperties(\ReflectionProperty::IS_PROTECTED) as $eigenschaft) {
                if ($eigenschaft->getDeclaringClass()->getName() !== $klasse) {
                    continue;
                }

                $attribute = array_merge($attribute, $eigenschaft->getAttributes());
            }

            foreach ($attribute as $attribut) {
                $attribut->newInstance();
                ++$gezaehlt;
            }
        }

        $this->assertGreaterThan(
            30,
            $gezaehlt,
            'Es wurden kaum Attribute geprueft — dann sagt dieser Test nichts.'
        );
    }

    /**
     * Und die Gegenprobe: Gegen bereits umgestellte Entities darf die Regel **nichts** tun.
     *
     * `lib/contentfly/Entity/` steht seit Epic `010` auf Attributen. Ein Vorschlag dort wäre
     * das Zeichen, dass die Regel mehr tut, als sie soll — und ein Projekt, das sie zweimal
     * laufen liesse, bekäme beim zweiten Mal Schaden.
     */
    public function testGegenUmgestellteEntitiesTutDieRegelNichts(): void
    {
        $ergebnis = $this->rectorFahren(false, $this->wurzel() . '/lib/contentfly/Entity');

        // Hier ist 0 das richtige Ergebnis: Ein Trockenlauf OHNE Funde endet mit 0.
        $this->assertSame(
            0,
            $ergebnis['code'],
            "Die Regel schlaegt an bereits umgestellten Entities etwas vor:\n" . $ergebnis['ausgabe']
        );

        $this->assertStringContainsString('Rector is done!', $ergebnis['ausgabe']);

        $this->assertStringNotContainsString(
            'would have been changed',
            $ergebnis['ausgabe'],
            'Die Regel tut an bereits umgestellten Entities etwas — ein Projekt, das sie '
            .'zweimal laufen liesse, bekaeme beim zweiten Mal Schaden.'
        );
    }

    /**
     * Die Listen hier stimmen noch mit der Dokumentation überein.
     *
     * Sie stehen an zwei Stellen, und zwei Stellen laufen auseinander. Die Datei nennt ihre
     * Anzahlen in den Abschnittstiteln — daran hängt diese Prüfung. Ändert jemand die Liste,
     * ohne diesen Test anzufassen, wird der Lauf rot statt still falsch.
     *
     * Dieselbe Regel wie bei den Gates aus `006-005`.
     */
    public function testDieListenStimmenNochMitDerDokumentationUeberein(): void
    {
        $doku = (string) file_get_contents($this->wurzel() . '/an_project/docs/pim-annotationen-migration.md');

        $this->assertStringContainsString('## 1. Entfallene Annotationen (7)', $doku);
        $this->assertStringContainsString('## 3. Entfallene Felder von `@PIM\\Config` (14)', $doku);
        $this->assertStringContainsString('## 4. Gebliebene Felder von `@PIM\\Config` (10)', $doku);

        $this->assertCount(7, self::GESTRICHENE_ANNOTATIONEN);
        $this->assertCount(14, self::GESTRICHENE_FELDER);
        $this->assertCount(10, self::GEBLIEBENE_FELDER);

        foreach (self::GESTRICHENE_ANNOTATIONEN as $annotation) {
            $this->assertStringContainsString('`@PIM\\' . $annotation, $doku,
                sprintf('%s steht nicht mehr in der Dokumentation.', $annotation));
        }
    }

    /**
     * Der Prüfstein darf keine Felder erfinden.
     *
     * **Das ist aus einem eigenen Fehler entstanden.** Mein erster Entwurf schrieb
     * `@PIM\Radio(options=…)`, `@PIM\Virtualjoin(entity=…, mappedBy=…)` und
     * `@PIM\Permissions(mode=…)` — Felder, die keine dieser Klassen je hatte. Ich hatte sie aus
     * der Streichliste abgeleitet, und die sagt nur, was WEGFAELLT. Was BLEIBT, sagt der
     * Konstruktor der Annotationsklasse.
     *
     * PHPStan hat es gefangen, aber nur im Sollzustand: Dort stehen Attribute, und die prüft
     * es. Im Altstand stehen Docblocks, und die sieht es nicht an. Ein Prüfstein, dessen
     * Altstand Felder erfindet, liesse die Regel gegen Fiktion messen.
     *
     * Geprüft werden deshalb **beide** Stände gegen die echten Konstruktoren.
     */
    public function testDerPruefsteinErfindetKeineFelder(): void
    {
        $erfunden = array();

        foreach (array('alt', 'soll') as $stand) {
            foreach ($this->angaben($this->wirksameZeilen($this->inhalt($stand))) as $ort => $felder) {
                [$annotation, $zeile] = explode('|', $ort, 2);

                $erlaubt = $this->konstruktorfelder($annotation);

                if ($erlaubt === null) {
                    // Eine gestrichene Annotation — ihre Klasse gibt es nicht mehr, und was
                    // in ihren Klammern stand, ist gleichgueltig: sie verschwindet ganz.
                    continue;
                }

                foreach ($felder as $feld) {
                    if (in_array($feld, $erlaubt, true)
                        || in_array($feld, self::GESTRICHENE_FELDER, true)
                        || in_array($feld, self::GESTRICHENE_FELDER_AUSWAHL, true)) {
                        continue;
                    }

                    $erfunden[] = sprintf('%s: %s(%s) — %s', $stand, $annotation, $feld, $zeile);
                }
            }
        }

        $this->assertSame(array(), $erfunden, implode("\n", array_merge(
            array(
                'Diese Felder gibt es an den Annotationsklassen nicht, und sie stehen auch nicht',
                'auf der Streichliste. Der Pruefstein erfindet sie also — und die Regel wuerde',
                'gegen Fiktion gemessen (007-002-0001):',
                '',
            ),
            $erfunden,
            array(
                '',
                'Was eine Annotation traegt, sagt ihr Konstruktor in',
                'lib/contentfly/Classes/Annotations/ — nicht die Streichliste.',
            )
        )));
    }

    // ── Helfer ─────────────────────────────────────────────────────────────────────────

    /**
     * Die PIM-Angaben einer Datei, je Annotationsname und normalisiert.
     *
     * Normalisiert heisst: Zeilenumbrüche und Einrückung raus, damit eine mehrzeilige
     * Annotation mit ihrer einzeiligen Form vergleichbar ist. Die Namen der ANNOTATION zählen
     * ohne Alias — `@Anders\Config` und `#[PIM\Config]` sind dieselbe Angabe.
     *
     * @return array<string,array<int,string>>
     */
    private function pimAngaben(string $inhalt): array
    {
        $treffer = array();

        preg_match_all(
            '/(?:@|#\\[)\\w+\\\\(\\w+)\\s*(\\(([^)]*)\\))?/s',
            $this->wirksameZeilen($inhalt),
            $funde,
            PREG_SET_ORDER
        );

        foreach ($funde as $fund) {
            if (!in_array($fund[1], self::GEBLIEBENE_ANNOTATIONEN, true)) {
                continue;
            }

            $treffer[$fund[1]][] = $this->normalisieren($fund[3] ?? '');
        }

        return $treffer;
    }

    /**
     * Die Konstruktorparameter einer Annotationsklasse, oder `null`, wenn es sie nicht gibt.
     *
     * Eine Klasse ohne Konstruktor nimmt nichts entgegen — das ist bei `Permissions` und
     * `I18nPermissions` der Fall und ergibt eine leere Liste, nicht `null`.
     *
     * @return array<int,string>|null
     */
    private function konstruktorfelder(string $annotation): ?array
    {
        $klasse = 'Areanet\\PIM\\Classes\\Annotations\\' . $annotation;

        if (!class_exists($klasse)) {
            return null;
        }

        $spiegel     = new \ReflectionClass($klasse);
        $konstruktor = $spiegel->getConstructor();

        if ($konstruktor === null) {
            return array();
        }

        return array_map(
            static fn (\ReflectionParameter $p): string => $p->getName(),
            $konstruktor->getParameters()
        );
    }

    /**
     * Eine Argumentliste auf `feld=wert`-Paare bringen, unabhaengig von der Schreibweise.
     *
     * `label="Artikel"` (Annotation) und `label: 'Artikel'` (Attribut) ergeben beide
     * `label=Artikel`. Nur so laesst sich vergleichen, ob der Lauf Felder verloren hat, ohne
     * dass der Wechsel der Schreibweise selbst als Verlust zaehlt.
     *
     * Sortiert, weil die Reihenfolge der Argumente keine Aussage traegt.
     */
    private function normalisieren(string $argumente): string
    {
        $flach = (string) preg_replace('/\\s*\\*\\s*|\\s+/', ' ', $argumente);

        preg_match_all('/(\\w+)\\s*[:=]\\s*("[^"]*"|\\x27[^\\x27]*\\x27|[^,]+)/', $flach, $funde, PREG_SET_ORDER);

        $paare = array();

        foreach ($funde as $fund) {
            $paare[] = $fund[1] . '=' . trim($fund[2], " \x22\x27");
        }

        sort($paare);

        return implode(', ', $paare);
    }

    /**
     * Alle `@PIM\X(...)`- und `#[PIM\X(...)]`-Angaben mit ihren benannten Feldern.
     *
     * @return array<string,array<int,string>> "Annotation|Zeile" => Feldnamen
     */
    private function angaben(string $inhalt): array
    {
        $treffer = array();

        preg_match_all(
            /*
             * VIER BACKSLASHES, und das ist kein Vertippen. In einfachen Anfuehrungszeichen
             * macht PHP aus `\\\\` ein `\\`, und die Regex sieht damit einen literalen
             * Backslash. Mein erster Entwurf schrieb zwei — daraus wurde `PIM\\(`, also eine
             * ESCAPTE KLAMMER, und der Ausdruck traf nie etwas. Der Test war gruen, weil er
             * nichts fand (007-002-0001).
             */
            '/(?:@|#\\[)\\w+\\\\(\\w+)\\s*\\(([^)]*)\\)/s',
            $inhalt,
            $funde,
            PREG_SET_ORDER
        );

        foreach ($funde as $i => $fund) {
            $felder = array();

            /*
             * Zeichenketten-Werte zuerst heraus: `options="eins=Eins,zwei=Zwei"` traegt
             * Gleichheitszeichen IM WERT, und ohne diesen Schritt las der Ausdruck `eins` und
             * `zwei` als Feldnamen.
             */
            $ohneWerte = (string) preg_replace('/"[^"]*"|\'[^\']*\'/', '""', $fund[2]);

            /*
             * `(?::(?!:)|=)` statt `[:=]`: Ein doppelter Doppelpunkt ist kein benanntes
             * Argument. Ohne die Einschraenkung las der Ausdruck aus
             * `targetEntity: \Tests\…\Artikel::class` das Wort `Artikel` als Feldnamen.
             */
            preg_match_all('/(\w+)\s*(?::(?!:)|=)/', $ohneWerte, $namen);

            foreach ($namen[1] as $name) {
                $felder[] = $name;
            }

            if ($felder !== array()) {
                $treffer[$fund[1] . '|' . $i] = $felder;
            }
        }

        return $treffer;
    }


    private function wurzel(): string
    {
        return dirname(__DIR__, 3);
    }

    private function fixtures(string $stand): string
    {
        return $this->wurzel() . '/tests/Fixtures/RectorMigration/' . $stand;
    }

    /** Alle Dateien eines Stands, in stabiler Reihenfolge aneinandergehängt. */
    private function inhalt(string $stand): string
    {
        $dateien = glob($this->fixtures($stand) . '/*.php');

        $this->assertNotEmpty($dateien, sprintf('Unter %s liegt keine Datei.', $this->fixtures($stand)));

        sort($dateien);

        return implode("\n", array_map(
            static fn (string $pfad): string => (string) file_get_contents($pfad),
            $dateien
        ));
    }

    /**
     * Die Zeilen, auf denen eine Annotation oder ein Attribut **wirkt**.
     *
     * **Der Unterschied ist hier nicht Kommentar gegen Code, und daran ist mein erster Anlauf
     * gescheitert.** Im Altstand stehen die Annotationen IN Docblocks — Kommentare
     * herauszufiltern nahm genau sie mit, und der Test war gruen, weil er nichts mehr fand.
     *
     * Wirksam ist eine Annotation nur am Zeilenanfang: `* @PIM\Config(…)` im Docblock,
     * `#[PIM\Config(…)]` im Code. Meine Prosa-Beispiele stehen mitten im Satz, hinter einem
     * Backtick — die bleiben damit aussen vor, ohne dass eine Ausnahmeliste noetig waere.
     *
     * Mehrzeilige Annotationen werden mitgenommen, solange die Klammern offen sind; sonst
     * verlore die Pruefung genau die Faelle, um die es geht.
     */
    private function wirksameZeilen(string $inhalt): string
    {
        $behalten = array();
        $offen    = 0;

        foreach (explode("\n", $inhalt) as $zeile) {
            $getrimmt = ltrim($zeile, " \t*");

            /*
             * ALIAS-UNABHAENGIG (007-002-0003). Hier stand `@PIM\\` und `#[PIM\\` fest
             * verdrahtet — und `AndererAlias.php` importiert die Annotationen als `Anders`.
             * Die Pruefungen gingen daran vorbei, und zwar still.
             */
            if ($offen === 0
                && preg_match('/^@\\w+\\\\\\w/', $getrimmt) !== 1
                && preg_match('/^#\\[\\w+\\\\\\w/', $getrimmt) !== 1) {
                continue;
            }

            $behalten[] = $zeile;
            $offen     += substr_count($zeile, '(') - substr_count($zeile, ')');

            if ($offen < 0) {
                $offen = 0;
            }
        }

        return implode("\n", $behalten);
    }

    /** Kommentarzeilen heraus — dort stehen die alten Namen absichtlich. */
    private function nurCode(string $inhalt): string
    {
        $zeilen = array_filter(
            explode("\n", $inhalt),
            static function (string $zeile): bool {
                $g = ltrim($zeile);

                return !str_starts_with($g, '*') && !str_starts_with($g, '/*') && !str_starts_with($g, '//');
            }
        );

        return implode("\n", $zeilen);
    }

    /**
     * Eine Kopie des Prüfsteins in einem Wegwerf-Verzeichnis.
     *
     * Nie gegen den Prüfstein selbst: Ein Lauf ohne `--dry-run` schriebe den Altstand um, und
     * der Test wäre beim zweiten Aufruf grün, weil es nichts mehr zu tun gibt.
     */
    private function kopieAnlegen(): string
    {
        $ziel = sys_get_temp_dir() . '/contentfly-rector-' . bin2hex(random_bytes(6));
        mkdir($ziel, 0777, true);

        foreach ((array) glob($this->fixtures('alt') . '/*.php') as $pfad) {
            copy((string) $pfad, $ziel . '/' . basename((string) $pfad));
        }

        return $ziel;
    }

    private function aufraeumen(string $ziel): void
    {
        foreach ((array) glob($ziel . '/*.php') as $pfad) {
            unlink((string) $pfad);
        }

        rmdir($ziel);
    }

    /**
     * Ein Rector-Aufruf gegen ein Verzeichnis.
     *
     * @return array{code:int,ausgabe:string}
     */
    private function rectorAufrufen(string $ziel, bool $anwenden): array
    {
        $befehl = sprintf(
            '%s %s process %s%s --no-progress-bar --clear-cache 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($this->wurzel() . '/vendor/bin/rector'),
            escapeshellarg($ziel),
            $anwenden ? '' : ' --dry-run'
        );

        $ausgabe = array();
        $code    = 0;
        exec($befehl, $ausgabe, $code);

        return array('code' => $code, 'ausgabe' => implode("\n", $ausgabe));
    }

    /**
     * Zwei Läufe — bis nichts mehr zu tun ist — und die Dateien danach.
     *
     * Zwei und nicht „bis es stabil ist" in einer Schleife: Die Zahl ist die Aussage. Wäre ein
     * dritter nötig, müsste das auffallen und nicht stillschweigend mitlaufen; genau das prüft
     * `testZweiLaeufeSindNoetigUndDerDritteFindetNichtsMehr()`.
     *
     * @return array<string,string> Dateiname => Inhalt nach dem Fixpunkt
     */
    private function bisZumFixpunkt(): array
    {
        $ziel = $this->kopieAnlegen();

        $this->rectorAufrufen($ziel, true);
        $this->rectorAufrufen($ziel, true);

        $dateien = array();

        foreach ((array) glob($ziel . '/*.php') as $pfad) {
            $dateien[basename((string) $pfad)] = (string) file_get_contents((string) $pfad);
        }

        $this->aufraeumen($ziel);

        return $dateien;
    }

    /**
     * Die Attribute je Element — Klasse oder Eigenschaft —, sortiert.
     *
     * Der Schlüssel ist der Name des Elements, damit ein Attribut, das an der falschen
     * Eigenschaft landet, auffällt. Sortiert, weil die Reihenfolge der Attribute an einem
     * Element keine Aussage trägt.
     *
     * @return array<string,array<int,string>>
     */
    private function attributeJeElement(string $inhalt): array
    {
        $ergebnis = array();
        $puffer   = array();
        $offen    = 0;

        foreach (explode("\n", $inhalt) as $zeile) {
            $getrimmt = trim($zeile);

            if ($offen > 0) {
                $puffer[count($puffer) - 1] .= ' ' . $getrimmt;
                $offen += substr_count($getrimmt, '(') - substr_count($getrimmt, ')');
                continue;
            }

            if (str_starts_with($getrimmt, '#[')) {
                $puffer[] = $getrimmt;
                $offen    = substr_count($getrimmt, '(') - substr_count($getrimmt, ')');
                continue;
            }

            if (preg_match('/^(?:final\s+)?class\s+(\w+)/', $getrimmt, $fund) === 1
                || preg_match('/^(?:protected|public|private)\s+\$(\w+)\s*;/', $getrimmt, $fund) === 1) {
                sort($puffer);
                $ergebnis[$fund[1]] = $puffer;
                $puffer             = array();
            }
        }

        return $ergebnis;
    }

    /**
     * Rector gegen eine **Kopie** des Prüfsteins, nie gegen den Prüfstein selbst.
     *
     * Ohne die Kopie schriebe ein Lauf ohne `--dry-run` den Altstand um, und der Test wäre
     * beim zweiten Aufruf grün, weil es nichts mehr zu tun gibt.
     *
     * @param bool        $anwenden Ohne `--dry-run` fahren und die Dateien zurückgeben.
     * @param string|null $quelle   Ein anderes Verzeichnis; wird dann **nicht** kopiert und
     *                              immer mit `--dry-run` gefahren.
     *
     * @return array{code:int,ausgabe:string,dateien:array<string,string>}
     */
    private function rectorFahren(bool $anwenden = false, ?string $quelle = null): array
    {
        if ($quelle !== null) {
            // Ein fremdes Verzeichnis wird nie verändert — nur befragt.
            $ziel     = $quelle;
            $anwenden = false;
        } else {
            $ziel = sys_get_temp_dir() . '/contentfly-rector-' . bin2hex(random_bytes(6));
            mkdir($ziel, 0777, true);

            foreach ((array) glob($this->fixtures('alt') . '/*.php') as $pfad) {
                copy((string) $pfad, $ziel . '/' . basename((string) $pfad));
            }
        }

        $befehl = sprintf(
            '%s %s process %s%s --no-progress-bar --clear-cache 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($this->wurzel() . '/vendor/bin/rector'),
            escapeshellarg($ziel),
            $anwenden ? '' : ' --dry-run'
        );

        $ausgabe = array();
        $code    = 0;
        exec($befehl, $ausgabe, $code);

        $dateien = array();

        if ($quelle === null) {
            foreach ((array) glob($ziel . '/*.php') as $pfad) {
                $dateien[basename((string) $pfad)] = (string) file_get_contents((string) $pfad);
                unlink((string) $pfad);
            }

            rmdir($ziel);
        }

        return array('code' => $code, 'ausgabe' => implode("\n", $ausgabe), 'dateien' => $dateien);
    }
}
