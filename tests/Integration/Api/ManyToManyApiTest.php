<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * Charakterisierungstests für den einzigen ManyToMany-Pfad des Frameworks: `PIM\File.tags`.
 *
 * **Warum diese Datei mitten in Epic `006` entsteht und nicht in `008`:** `doctrine/orm` steht
 * heute auf einem eigenen Fork — `area-net-gmbh/doctrine2`, Branch **`bugfix-many2many`**,
 * Stand 2018-08-07. Story `006-002` wechselt auf ein Release, und niemand kann sagen, ob der
 * Fix dieses Forks dort aufgegangen ist (`006-001-0003`).
 *
 * Der Fork ist nach *diesem* Pfad benannt. Ohne Tests darauf mischte sich ein möglicher Bruch
 * mit vier weiteren Major-Sprüngen — Symfony 3.4→4.4, DBAL 2.6→2.13, annotations 1.8→1.14,
 * uuid 3→4 — und wäre nicht mehr zuzuordnen.
 *
 * ## Nicht zu verwechseln mit der dokumentierten Lücke
 * `an_project/docs/technical.md` führt „Schreibprüfung des `MultijoinType`" als Lücke. Das ist
 * ein **Berechtigungs**pfad im `acceptFrom`-Zweig, der mangels `acceptFrom` im ganzen Baum
 * nicht auslösbar ist — festgehalten in
 * `WritePermissionApiTest::testDieSchreibpruefungInMultijoinTypeIstNichtAusloesbar()`.
 *
 * Diese Datei prüft etwas anderes: das **ORM-Verhalten**. Verknüpfungen anlegen, lesen,
 * ändern, löschen. Das ist sehr wohl auslösbar, und es ist das, wonach der Fork benannt ist.
 *
 * ## Die Entity
 * ```php
 * // lib/contentfly/Entity/File.php:81
 * @ORM\ManyToMany(targetEntity="Areanet\PIM\Entity\Tag")
 * @ORM\JoinTable(name="pim_file_tags", joinColumns={@ORM\JoinColumn(onDelete="CASCADE")})
 * @PIM\Config(isFilterable=true)
 * ```
 *
 * **Der Zustand der Verknüpfungstabelle wird direkt per SQL geprüft, nicht nur über die
 * API-Antwort.** Ein ORM-Wechsel kann die Antwort richtig aussehen lassen und die Tabelle
 * trotzdem falsch füllen — genau die Sorte Fehler, die dieser Fork einmal behoben haben soll.
 *
 * Beim Schreiben zeigte sich dabei ein Punkt, der aus der Entity **nicht** ablesbar ist:
 * Die Annotation setzt `onDelete="CASCADE"` nur auf den `joinColumns` (`file_id`), Doctrine
 * legt es aber auf **beiden** Fremdschlüsseln an. Das Schema ist symmetrisch, die Annotation
 * beschreibt es asymmetrisch — wer nur sie liest, erwartet verwaiste Zeilen, die es nicht
 * gibt.
 */
class ManyToManyApiTest extends IntegrationTestCase
{
    private const ENTITY = 'PIM\\File';

    /** @var array<int,string> Ids, deren Protokoll- und Verknüpfungszeilen tearDown() entfernt. */
    private array $aufzuraeumendeIds = array();

    /**
     * Entfernt, was `deleteAfterTest()` nicht erreicht, und übergibt dann an die Basis.
     *
     * Zwei Dinge fallen durch: die Zeilen in `pim_file_tags` (sie haben keine eigene `id`,
     * die Basis löscht aber über `id`) und die Protokollzeilen (sie stehen unter `model_id`,
     * und die entscheidenden entstehen erst **nach** der Anmeldung — jedes `/api/update`
     * schreibt eine).
     *
     * Ohne das wuchs `pim_log` mit jedem Lauf um drei Zeilen. Genau der Rückstand, gegen den
     * `000-000-0008` geschrieben wurde.
     */
    protected function tearDown(): void
    {
        foreach ($this->aufzuraeumendeIds as $id) {
            $this->pdo()->prepare('DELETE FROM pim_file_tags WHERE file_id = :id')->execute(array('id' => $id));
            $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $id));
        }

        $this->aufzuraeumendeIds = array();

        parent::tearDown();
    }

    /**
     * Legt einen Tag **an der API vorbei** an.
     *
     * Wie in Epic `008`: Ein Lesetest, dessen Vorbedingung über den Schreibpfad läuft, den er
     * selbst prüft, verliert seine Aussagekraft.
     */
    private function tag(string $titel): string
    {
        $id = 'm2m-t-'.bin2hex(random_bytes(5));

        $this->pdo()->prepare(
            'INSERT INTO pim_tag (id, title, created, modified, views, isIntern)
             VALUES (:id, :titel, NOW(), NOW(), 0, 0)'
        )->execute(array('id' => $id, 'titel' => $titel));

        $this->deleteAfterTest('pim_tag', $id);
        $this->aufzuraeumendeIds[] = $id;

        return $id;
    }

    /**
     * Legt eine Datei-Zeile an — ohne Upload.
     *
     * `/api/insert` scheidet aus: `pim_file` verlangt `hash` und `type` als NOT NULL, und der
     * Insert-Pfad füllt sie nicht. Dateien entstehen sonst über `/file/upload`, aber der
     * Upload-Pfad hat mit ManyToMany nichts zu tun und brächte nur eigene Fehlerquellen mit
     * (siehe `FileApiTest`, wo er charakterisiert ist).
     */
    private function datei(string $name = 'm2m.txt'): string
    {
        $id = 'm2m-f-'.bin2hex(random_bytes(5));

        $this->pdo()->prepare(
            'INSERT INTO pim_file (id, name, type, hash, size, created, modified, views, isIntern)
             VALUES (:id, :name, :typ, :hash, 5, NOW(), NOW(), 0, 0)'
        )->execute(array(
            'id'   => $id,
            'name' => $name,
            'typ'  => 'text/plain',
            'hash' => bin2hex(random_bytes(8)),
        ));

        $this->deleteAfterTest('pim_file', $id);
        $this->aufzuraeumendeIds[] = $id;

        return $id;
    }

    /** Setzt Verknüpfungen direkt in die Tabelle. */
    private function verknuepfen(string $dateiId, string ...$tagIds): void
    {
        $stmt = $this->pdo()->prepare('INSERT INTO pim_file_tags (file_id, tag_id) VALUES (:f, :t)');

        foreach ($tagIds as $tagId) {
            $stmt->execute(array('f' => $dateiId, 't' => $tagId));
        }
    }

    /** Die Tag-Ids, die in `pim_file_tags` für diese Datei stehen — sortiert. */
    private function verknuepfungen(string $dateiId): array
    {
        $stmt = $this->pdo()->prepare('SELECT tag_id FROM pim_file_tags WHERE file_id = :f ORDER BY tag_id');
        $stmt->execute(array('f' => $dateiId));

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Räumt die Verknüpfungen einer Datei ab.
     *
     * `tearDown()` tut das ohnehin; die Aufrufe im Testrumpf stehen dort, wo **vor** einer
     * Assertion aufgeräumt werden soll — dann hängt eine Fehlermeldung nicht davon ab, ob
     * das Aufräumen vorher gelungen ist.
     */
    private function verknuepfungenAufraeumen(string $dateiId): void
    {
        $this->pdo()->prepare('DELETE FROM pim_file_tags WHERE file_id = :f')->execute(array('f' => $dateiId));
    }

    // ── Lesen ──────────────────────────────────────────────────────────────────────────

    public function testEineVerknuepfteDateiLiefertIhreTagsAlsVolleObjekte(): void
    {
        // Bemerkenswert und deshalb festgehalten: Die Antwort enthaelt nicht nur die Ids,
        // sondern jeden Tag als vollstaendiges Objekt — mit created, modified, views, users,
        // groups. Ein Client, der nur die Zuordnung braucht, bekommt den ganzen Datensatz.
        $datei = $this->datei();
        $alpha = $this->tag('M2M-Alpha');
        $beta  = $this->tag('M2M-Beta');
        $this->verknuepfen($datei, $alpha, $beta);

        [$status, $body] = $this->postJson(
            '/api/single',
            array('entity' => self::ENTITY, 'id' => $datei),
            $this->token()
        );

        $this->assertSame(200, $status);

        // Sortiert verglichen: Die Reihenfolge, in der die Tags zurueckkommen, sichert
        // nichts zu — weder die Entity noch die Abfrage geben eine an. Ein Test, der sich
        // darauf verlaesst, ist ein Test, der irgendwann ohne Grund rot wird.
        $tags = $body['data']['tags'];
        $this->assertCount(2, $tags);

        $ids = array_column($tags, 'id');
        sort($ids);
        $erwartet = array($alpha, $beta);
        sort($erwartet);
        $this->assertSame($erwartet, $ids);

        $titel = array_column($tags, 'title');
        sort($titel);
        $this->assertSame(array('M2M-Alpha', 'M2M-Beta'), $titel);
        $this->assertArrayHasKey('created', $tags[0], 'Volles Objekt, nicht nur die Id');

        $this->verknuepfungenAufraeumen($datei);
    }

    public function testEineDateiOhneTagsLiefertEineLeereListe(): void
    {
        $datei = $this->datei();

        [, $body] = $this->postJson(
            '/api/single',
            array('entity' => self::ENTITY, 'id' => $datei),
            $this->token()
        );

        $this->assertSame(array(), $body['data']['tags'],
            'Leere Liste, nicht null — der Unterschied zaehlt fuer einen Client');
    }

    public function testDasSchemaBeschreibtDieVerknuepfungstabelle(): void
    {
        // Der Client erfaehrt aus dem Schema, wie die Beziehung physisch aussieht. Nach dem
        // Doctrine-Wechsel muss das unveraendert gelten — sonst brechen Clients, die danach
        // gehen.
        [$status, $roh] = $this->get('/api/schema', $this->token());
        $this->assertSame(200, $status);

        $tags = json_decode($roh, true)['data'][self::ENTITY]['properties']['tags'];

        $this->assertSame('multijoin', $tags['type']);
        $this->assertSame('Areanet\\PIM\\Entity\\Tag', $tags['accept']);
        $this->assertSame('pim_file_tags', $tags['foreign']);
        $this->assertSame('file_id', $tags['dbfield']);
        $this->assertSame('tag_id', $tags['dbfield_foreign']);
        $this->assertTrue($tags['isFilterable']);
    }

    // ── Schreiben ──────────────────────────────────────────────────────────────────────

    public function testTagsLassenSichUeberUpdateSetzen(): void
    {
        $datei = $this->datei();
        $alpha = $this->tag('M2M-Setzen-A');
        $beta  = $this->tag('M2M-Setzen-B');

        [$status] = $this->postJson(
            '/api/update',
            array('entity' => self::ENTITY, 'id' => $datei, 'data' => array('tags' => array($alpha, $beta))),
            $this->token()
        );

        $erwartet = array($alpha, $beta);
        sort($erwartet);

        $this->assertSame(200, $status);
        $this->assertSame($erwartet, $this->verknuepfungen($datei),
            'Die Zeilen stehen wirklich in pim_file_tags — per SQL geprueft, nicht ueber die Antwort');

        $this->verknuepfungenAufraeumen($datei);
    }

    public function testEineNeueMengeErsetztDieAlteVollstaendig(): void
    {
        // Der Punkt, an dem ein ORM-Wechsel schiefgehen kann: Update ist ERSETZEN, nicht
        // Ergaenzen. Wer beta wegnehmen will, schickt die Menge ohne beta — und die Zeile
        // muss verschwinden, nicht liegenbleiben.
        $datei = $this->datei();
        $alpha = $this->tag('M2M-Ersetzen-A');
        $beta  = $this->tag('M2M-Ersetzen-B');
        $this->verknuepfen($datei, $alpha, $beta);

        [$status] = $this->postJson(
            '/api/update',
            array('entity' => self::ENTITY, 'id' => $datei, 'data' => array('tags' => array($alpha))),
            $this->token()
        );

        $this->assertSame(200, $status);
        $this->assertSame(array($alpha), $this->verknuepfungen($datei),
            'beta ist weg — die Menge wurde ersetzt, nicht ergaenzt');

        $this->verknuepfungenAufraeumen($datei);
    }

    public function testEineLeereMengeLoestAlleVerknuepfungen(): void
    {
        $datei = $this->datei();
        $this->verknuepfen($datei, $this->tag('M2M-Leeren-A'), $this->tag('M2M-Leeren-B'));

        [$status] = $this->postJson(
            '/api/update',
            array('entity' => self::ENTITY, 'id' => $datei, 'data' => array('tags' => array())),
            $this->token()
        );

        $this->assertSame(200, $status);
        $this->assertSame(array(), $this->verknuepfungen($datei));
    }

    // ── Löschen ────────────────────────────────────────────────────────────────────────

    public function testMitDerDateiVerschwindenAuchIhreVerknuepfungen(): void
    {
        // Die JoinColumn traegt onDelete="CASCADE". Ob die Verknuepfungen von der Datenbank
        // (Fremdschluessel) oder vom ORM entfernt werden, ist von aussen nicht zu
        // unterscheiden — und fuer den Vertrag auch nicht wichtig. Wichtig ist, dass keine
        // verwaisten Zeilen zurueckbleiben.
        $datei = $this->datei();
        $this->verknuepfen($datei, $this->tag('M2M-Cascade-A'), $this->tag('M2M-Cascade-B'));

        $this->assertCount(2, $this->verknuepfungen($datei), 'Vorbedingung');

        [$status] = $this->postJson(
            '/api/delete',
            array('entity' => self::ENTITY, 'id' => $datei),
            $this->token()
        );

        $this->assertSame(200, $status);
        $this->assertSame(array(), $this->verknuepfungen($datei),
            'Keine verwaisten Zeilen in pim_file_tags');

    }

    public function testAuchDasLoeschenDesTagsRaeumtDieVerknuepfungAuf(): void
    {
        // Die Gegenrichtung — und sie raeumt ebenfalls auf, entgegen der Erwartung beim
        // Schreiben dieses Tests. Die Annotation setzt onDelete="CASCADE" nur auf den
        // joinColumns (file_id); Doctrine legt es aber auf BEIDEN Fremdschluesseln an:
        //
        //   CONSTRAINT FK_…46F22BC   FOREIGN KEY (file_id) REFERENCES pim_file (id) ON DELETE CASCADE
        //   CONSTRAINT FK_…DD1FDCE8  FOREIGN KEY (tag_id)  REFERENCES pim_tag  (id) ON DELETE CASCADE
        //
        // Das Schema ist also symmetrisch, obwohl die Annotation es asymmetrisch beschreibt.
        // Gut so — es gibt keine verwaisten Zeilen. Festgehalten, weil es NICHT aus der
        // Entity ablesbar ist: Wer nur die Annotation liest, erwartet das Gegenteil.
        $datei = $this->datei();
        $tag   = $this->tag('M2M-Tag-geloescht');
        $this->verknuepfen($datei, $tag);

        $this->assertSame(array($tag), $this->verknuepfungen($datei), 'Vorbedingung');

        [$status] = $this->postJson(
            '/api/delete',
            array('entity' => 'PIM\\Tag', 'id' => $tag),
            $this->token()
        );

        $verbliebene = $this->verknuepfungen($datei);

        $this->assertSame(200, $status, 'Das Loeschen des Tags gelingt');
        $this->assertSame(array(), $verbliebene,
            'Keine verwaiste Zeile — der Fremdschluessel auf tag_id kaskadiert ebenfalls');
    }

    // ── Filtern ────────────────────────────────────────────────────────────────────────

    public function testUeberTagsLaesstSichFiltern(): void
    {
        // isFilterable=true steht im Schema; hier der Nachweis, dass es auch wirkt. Der
        // Filter geht ueber die Verknuepfungstabelle — genau die Art Abfrage, die ein
        // ORM-Wechsel anders erzeugen koennte.
        $gesucht    = $this->datei('m2m-gesucht.txt');
        $ungesucht  = $this->datei('m2m-ungesucht.txt');
        $tag        = $this->tag('M2M-Filter');
        $andererTag = $this->tag('M2M-Filter-Anders');

        $this->verknuepfen($gesucht, $tag);
        $this->verknuepfen($ungesucht, $andererTag);

        [$status, $body] = $this->postJson(
            '/api/list',
            array('entity' => self::ENTITY, 'where' => array('tags' => $tag)),
            $this->token()
        );

        $ids = array_column($body['data'], 'id');

        $this->verknuepfungenAufraeumen($gesucht);
        $this->verknuepfungenAufraeumen($ungesucht);

        $this->assertSame(200, $status);
        $this->assertContains($gesucht, $ids, 'Die verknuepfte Datei wird gefunden');
        $this->assertNotContains($ungesucht, $ids, 'Die anders verknuepfte nicht');
    }
}
