<?php
declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassLike\RemoveAnnotationRector;
use Rector\Php80\Rector\Class_\AnnotationToAttributeRector;
use Rector\Php80\ValueObject\AnnotationToAttribute;
use Rector\Doctrine\Set\DoctrineSetList;

/*
 * Die Migrationsregel für das `Entity/`-Verzeichnis eines Bestandsprojekts (Story 007-002).
 *
 * ── Wofür ─────────────────────────────────────────────────────────────────────────────
 *
 * Ein Projekt, das von einem Contentfly-Stand vor Epic 010/012 kommt, lässt diese Regel
 * einmal über sein `Entity/`-Verzeichnis laufen und hat danach
 *
 *   - PHP-Attribute statt `@ORM\*`-Annotationen (Epic 010),
 *   - keine der mit Epic 012 gestrichenen `@PIM\*`-Annotationen,
 *   - keines der gestrichenen Felder in den `@PIM\*`-Annotationen, die geblieben sind.
 *
 * Die vollständige Liste, aus der die Regel gebaut ist, steht in
 * `an_project/docs/pim-annotationen-migration.md`. Sie ist die Quelle; was hier steht, ist
 * ihre Umsetzung.
 *
 * ── DER AUFRUF, und der erste Schritt ist immer ein Trockenlauf ───────────────────────
 *
 *     ./vendor/bin/rector process pfad/zu/Entity --dry-run
 *     ./vendor/bin/rector process pfad/zu/Entity
 *
 * Der Pfad hinter `process` übersteuert `withPaths()` unten. Ohne Pfad läuft die Regel über
 * das, was hier eingetragen ist.
 *
 * ── WARUM DER PFAD ENG IST ────────────────────────────────────────────────────────────
 *
 * Rectors eigenes Gerüst (`rector init`) trägt `custom`, `lib`, `tests` und `tools` ein — also
 * den ganzen Baum. Hier wäre das falsch und gefährlich: `lib/` ist der Frameworkcode, der
 * längst auf Attributen steht, und `tests/Fixtures/RectorMigration/alt/` ist der Prüfstein
 * DIESER Regel, der im Altstand bleiben muss. Ein Lauf über den ganzen Baum schriebe beide um.
 *
 * Eingetragen ist deshalb nur `custom/Entity` — das Verzeichnis, das ein Projekt migriert.
 *
 * ── WAS DIESE REGEL NICHT TUN KANN ───────────────────────────────────────────────────
 *
 * Sie laeuft ueber `Entity/`. Alles andere bleibt Handarbeit, und es gehoert hier genannt,
 * damit niemand den Lauf fuer vollstaendig haelt:
 *
 *   DREI TYPE-KLASSEN AUS DER KONFIGURATION STREICHEN. Mit den Annotationen sind `RteType`,
 *   `PasswordType` und `EntitySelectorType` entfallen. Ein Projekt, das eine davon in
 *   `APP_SYSTEM_TYPES` oder `APP_CUSTOM_TYPES` auffuehrt, bricht beim Start mit
 *   `contentfly_type_class_not_found` ab. Das steht in `custom/config.php`, nicht in einer
 *   Entity — eine Regel ueber `Entity/` kommt dort nie vorbei, und sie zu erweitern hiesse,
 *   die Konfiguration eines Projekts umzuschreiben.
 *
 *   Die entfallene Plugin-Schnittstelle und die `FRONTEND_*`-Konfiguration ebenso; beides
 *   steht in `an_project/docs/pim-annotationen-migration.md`, Abschnitte 5 und 6.
 *
 * Die vollstaendige Liste dessen, was die Regel abdeckt und was nicht, sammelt
 * `007-002-0005`.
 *
 * ── Stand ─────────────────────────────────────────────────────────────────────────────
 *
 * Eingetragen: der ORM-Teil (`007-002-0002`), die sieben gestrichenen `@PIM\*`-Annotationen
 * und die Umstellung der gebliebenen auf Attribute (`007-002-0003`). Noch offen: die
 * gestrichenen Felder aus den Annotationen, die geblieben sind (`007-002-0004`).
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/custom/Entity',
    ])
    /*
     * DER ORM-TEIL (007-002-0002).
     *
     * `ANNOTATIONS_TO_ATTRIBUTES` aus `rector-doctrine` — und zwar dieses Set und nicht die
     * generische `AnnotationToAttributeRector`, bei der jede Mapping-Annotation einzeln
     * einzutragen wäre. Das Set kennt sie alle, samt der verschachtelten Formen, und wird mit
     * Doctrine gepflegt.
     *
     * ES KOMMT OHNE ZUSAETZLICHES PAKET: `rector/rector` 1.2 liefert `rector-doctrine` in
     * seinem eigenen Vendor mit. Nachgesehen am 2026-09-11 — ein `composer require
     * rector/rector-doctrine` ist nicht nötig und würde eine zweite Fassung derselben Regeln
     * in den Baum holen.
     *
     * DIE ANDEREN SETS BLEIBEN BEWUSST DRAUSSEN. `DOCTRINE_CODE_QUALITY` und
     * `TYPED_COLLECTIONS` ändern Code, nicht Mapping — das gehört einem Projekt und nicht
     * einer Migrationsregel. Wer sie will, trägt sie selbst ein.
     */
    ->withSets([
        DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
    ])
    /*
     * DIE SIEBEN GESTRICHENEN @PIM-ANNOTATIONEN (007-002-0003).
     *
     * Mit Epic 012 ist der Teil der `@PIM`-Annotationen weggefallen, der Eingabemasken
     * beschrieb. Diese sieben sind ersatzlos zu loeschen; die Liste steht in
     * an_project/docs/pim-annotationen-migration.md, Abschnitt 1.
     *
     * WARUM DAS NICHT OPTIONAL IST: Ein stehengebliebenes Feld ist kein geduldetes Relikt.
     * `Doctrine\Common\Annotations\Annotation::__get()` wirft eine BadMethodCallException,
     * und der AnnotationReader bricht schon beim Einlesen ab — das Projekt startet dann gar
     * nicht.
     *
     * VOLLQUALIFIZIERT UND NICHT `PIM\Rte`, und das ist der Unterschied, der zaehlt: Beide
     * Schreibweisen funktionieren (nachgemessen am 2026-09-11), aber die kurze haengt am
     * Alias. Ein Projekt, das `use Areanet\PIM\Classes\Annotations as Anders;` schreibt,
     * wuerde von `PIM\Rte` nicht erfasst. Rector loest den vollen Namen ueber die
     * use-Anweisungen auf und findet ihn unter jedem Alias; `tests/Fixtures/RectorMigration/`
     * traegt dafuer eine eigene Datei.
     *
     * KEIN EIGENER CODE NOETIG. Die Story nahm an, fuer den @PIM-Teil gebe es keinen fertigen
     * Rector-Satz. Fuer das Entfernen einer GANZEN Annotation gibt es einen —
     * RemoveAnnotationRector, konfigurierbar, und er greift auch auf Eigenschaften
     * (`getNodeTypes()` nennt Property). Der eigene Anteil liegt bei den FELDERN und damit in
     * 007-002-0004.
     */
    ->withConfiguredRule(RemoveAnnotationRector::class, [
        'Areanet\\PIM\\Classes\\Annotations\\Rte',
        'Areanet\\PIM\\Classes\\Annotations\\Textarea',
        'Areanet\\PIM\\Classes\\Annotations\\Datetime',
        'Areanet\\PIM\\Classes\\Annotations\\Time',
        'Areanet\\PIM\\Classes\\Annotations\\Password',
        'Areanet\\PIM\\Classes\\Annotations\\MatrixChooser',
        'Areanet\\PIM\\Classes\\Annotations\\EntitySelector',
    ])
    /*
     * UND DIE GEBLIEBENEN @PIM-ANNOTATIONEN WERDEN ATTRIBUTE (007-002-0003).
     *
     * DAS WAR NICHT GEPLANT UND IST DER WICHTIGERE TEIL. Der Schnitt dieser Story ging davon
     * aus, die gebliebenen Annotationen seien nicht anzufassen. Sie sind es, und der Grund ist
     * ein STILLER AUSFALL:
     *
     * `Classes/Metadaten/Metadatenleser` liest seit 010-001-0003 ausschliesslich PHP-Attribute
     * per Reflection; der AnnotationReader ist aus dem Framework verschwunden. Ein Projekt,
     * das nach der Migration `@PIM\Config(excludeFromSync=true)` im Docblock behaelt, hat
     * damit eine Konfiguration, die NIEMAND MEHR LIEST — und es gibt keine Fehlermeldung. Die
     * Entity landet wieder in der Sync-API, ein `encoded`-Feld wird unverschluesselt
     * geschrieben, ein `isFilterable` verschwindet aus den Filtern. Alles lautlos.
     *
     * Der ORM-Satz oben faesst die @PIM-Angaben nicht an, also braucht es diese Regel.
     * `ManyToMany` ist mit dabei: Die Annotation gehoert zur PIM-Seite und steht in
     * Abschnitt 2 der Streichliste ausdruecklich unter denen, die bleiben.
     */
    ->withConfiguredRule(AnnotationToAttributeRector::class, [
        new AnnotationToAttribute('Areanet\\PIM\\Classes\\Annotations\\Config'),
        new AnnotationToAttribute('Areanet\\PIM\\Classes\\Annotations\\Select'),
        new AnnotationToAttribute('Areanet\\PIM\\Classes\\Annotations\\Virtualjoin'),
        new AnnotationToAttribute('Areanet\\PIM\\Classes\\Annotations\\Permissions'),
        new AnnotationToAttribute('Areanet\\PIM\\Classes\\Annotations\\I18nPermissions'),
        new AnnotationToAttribute('Areanet\\PIM\\Classes\\Annotations\\Checkbox'),
        new AnnotationToAttribute('Areanet\\PIM\\Classes\\Annotations\\Radio'),
        new AnnotationToAttribute('Areanet\\PIM\\Classes\\Annotations\\ManyToMany'),
    ]);
