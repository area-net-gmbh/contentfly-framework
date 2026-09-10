---
id: 010-000-0000
title: Doctrine und Entity-Layer modernisieren
status: done
depends_on: [006-000-0000, 012-000-0000]
---

# Doctrine und Entity-Layer modernisieren

## Goal
Der Entity-Layer ist die tiefste Kopplung des Frameworks — jedes Bestandsprojekt erbt von
`Areanet\PIM\Entity\Base` und schreibt `@PIM\*`-Annotationen in seine eigenen Entities. Was hier
entschieden wird, muss jedes migrierende Projekt nachvollziehen. Deshalb ein eigenes Epic und
nicht ein Nebenschauplatz des Kernel-Umbaus.

## Ausgangslage, gemessen am 2026-09-09

Alle Zahlen stammen aus dem Baum, nicht aus einer Schätzung.

| | |
|---|---|
| Entities im Framework | 21, dazu `custom/Entity/Core/Example.php` in der Vorlage |
| `@ORM\*`-Annotationen | 166 |
| `@PIM\*`-Annotationen | 36, verteilt auf acht Annotationsklassen, die Epic `012` überlebt haben |
| `AnnotationReader` ausserhalb Doctrines eigenem Driver | 3 Lesestellen (`Api.php`, `JoinBidirectionalType.php`, `ApiController.php`) |
| `AnnotationRegistry` | Bootstrap, `TypeManager` (3 Aufrufe), `Plugin.php` |
| `Lexer::T_*` in eigenen DQL-Funktionen | 11 Stellen in 3 Dateien (`FindInSet`, `Distance`, `PointStr`) |
| `Doctrine\Common\Cache` | 8 Stellen, alle im Bootstrap |
| Eigene ORM-Klassen | 8 (`EntityManagerFactory`, `UuidGenerator`, `ContentflyQuoteStrategy`, 1 DQL-Funktion, 4 Spatial) |
| Übergeben aus Epic `009` | 31 PHPStan-Befunde über acht benannte Muster, 2 abandoned Pakete |

### Drei Befunde, die den Zuschnitt bestimmen

**1. ORM 3 zwingt DBAL nicht weiter.** `doctrine/orm` 3.7 verlangt `php ^8.1` und
`doctrine/dbal ^3.8.2 || ^4`. Der Baum steht seit Story `009-005` auf DBAL **3.10.6**. Der
Versionssprung braucht also **keinen zweiten Datenbank-Umbau** — anders als der Kernel-Schnitt,
der DBAL 2 → 3 erzwungen hat.

**2. Die Attribut-Umstellung braucht ORM 3 nicht.** ORM 2.20 bringt bereits einen
`AttributeDriver`. Die 202 Annotationen lassen sich auf Attribute bringen und dabei gegen ein
**unverändertes** ORM messen. Das ist der Grund für den Schnitt unten: eine Variable je Story,
dasselbe Vorgehen, das bei DBAL getragen hat.

**3. Konstanten in Attributen sind kein Problem, sondern eine Verbesserung.** `Entity\Base`
trägt `@ORM\GeneratedValue(strategy=APPCMS_ID_STRATEGY)` und `@ORM\Column(type=APPCMS_ID_TYPE)`.
Annotationen sind Text in einem Docblock, den ein Reader parsen muss; ein Attribut ist PHP-Code,
und eine globale Konstante ist ein zulässiger konstanter Ausdruck. Die Konfigurierbarkeit bleibt
also erhalten, **ohne** Sonderbehandlung. Zu prüfen bleibt nur der Zeitpunkt: Attribute werden
beim Reflektieren ausgewertet, die Konstanten müssen dann definiert sein.

## Erfolgskriterien
- **Doctrine ORM auf einer releasten, gepflegten Version.** Der Dev-Branch-Pin
  `doctrine/orm dev-bugfix-many2many` ist Geschichte (aufgelöst in 006) — hier folgt der
  eigentliche Versionssprung Richtung ORM 3.

  > **Erledigt, 2026-09-09.** Dieses Kriterium verlangte, vorher zu klären, was der Fork
  > `bugfix-many2many` löste. Story `006-002` hat das beantwortet: Neun gezielte
  > ManyToMany-Tests (`tests/Integration/Api/ManyToManyApiTest.php`) sind gegen ORM **2.20.13**
  > grün. Der Fix ist im Release aufgegangen oder war für `PIM\File.tags` nie relevant. Die
  > Tests bleiben stehen — sie sind jetzt die Absicherung des ORM-3-Sprungs.

  > **Richtiggestellt, 2026-09-09.** Hier stand „samt DBAL". DBAL ist mit Story `009-005`
  > bereits auf 3.10.6 gehoben worden, erzwungen durch `symfony/http-foundation` 7.4. ORM 3.7
  > akzeptiert `dbal ^3.8.2`, also bewegt sich DBAL in diesem Epic **nicht**. Ob DBAL 4
  > folgt, ist eine eigene Entscheidung.
- **Annotationen → PHP-Attribute.** ORM 3 kennt keinen Annotation-Driver mehr. Betrifft `@ORM\*`
  **und** den `@PIM\*`-Rest, der 012 überlebt — gelesen über `AnnotationReader` u. a. in
  `Classes/Api.php:1419`, `Classes/Types/JoinBidirectionalType.php:58`,
  `Controller/ApiController.php`. Das abandoned `doctrine/annotations` fällt damit weg.
  Reihenfolge: erst 012 die UI-Anteile streichen, dann hier den Rest umstellen — sonst werden
  Formular-Annotationen auf Attribute portiert, die gleich danach gelöscht werden.
- **`Base` und die eigenen Doctrine-Typen bleiben die öffentliche API** des Frameworks — sie sind
  der Grund, warum Bestandsprojekte überhaupt migrierbar sind. Änderungen daran werden bewusst
  getroffen und in 007 dokumentiert.
- **Krypto: `StringType` von AES-CBC-ohne-MAC auf AEAD** (XChaCha20-Poly1305). CBC ohne MAC ist
  unauthentifiziert und manipulierbar; der Umbau ist die Gelegenheit, das zu beenden.
  Dazu gehört ein **ausführbarer Re-Encrypt-Befehl** mit Trockenlauf und Rollback-Weg — er läuft
  später in jedem Bestandsprojekt auf dessen eigenen Daten (007), nicht hier.
- **Konstanten in Attributen geprüft:** Die heutigen Annotationen nutzen Konstanten
  (`APPCMS_ID_TYPE` …). Der Weg, wie diese Konfigurierbarkeit unter Attributen erhalten bleibt,
  ist Teil der Abnahme. UI-Konstanten wie `APP_CMS_SHOW_ID_IN_LIST` entfallen mit 012.
- **Die Ausnahmelisten der drei Gates sind leer.** Epic `009` hat 31 PHPStan-Befunde über acht
  benannte Muster übergeben — alle aus Doctrine —, dazu zwei abandoned Pakete
  (`doctrine/annotations`, `doctrine/cache`). `tools/ci/audit.sh` steht deshalb auf
  `--abandoned=ignore` mit dem Vermerk „auf `fail` umstellen, sobald Epic 010 durch ist". Beides
  gehört eingelöst, nicht verlängert.

- **Vier Altbefunde, die Epic `009` mit benanntem Auflöser hierher gegeben hat:**
  die `rowCount()`-Stellen, die zum Zählen den ganzen Treffersatz in den Speicher holen statt
  `SELECT COUNT(*)` zu fragen; der fehlende Index `modified_index`, weil der
  `LoadMetadata`-Listener nur bei `is_installed` registriert wird und `appcms:install` genau
  dann läuft, wenn das falsch ist; die ungültige Zuordnung in `BaseI18nTree`, deren Join-Spalten
  `id, lang` nicht abdecken; und die Frage, ob der PHP-8.4-Job blockierend wird — der grüne Lauf
  dafür liegt seit `009-004-0002` vor.

- Das Testnetz aus 008 bleibt grün; Migration und Schema-Diff sind reproduzierbar.

## Abgrenzung
- **DBAL bewegt sich nicht.** Siehe oben. Ein Sprung auf DBAL 4 ist ein eigenes Vorhaben.
- **Keine Bundle-Struktur.** Wie in Epic `009` bleibt `lib/contentfly/` liegen, wie es ist. Der
  Grund ist derselbe: Die Charakterisierungstests sollen eine Variable messen.
- **Der Re-Encrypt-Befehl läuft hier auf keinen echten Daten.** Er entsteht in diesem Epic mit
  Trockenlauf und Rückweg; angewandt wird er je Bestandsprojekt in Epic `007`.
- **Kein Envelope-Umbau, keine Auth-Änderung** — `011` beziehungsweise `013`.

## Der Schnitt, und warum er so aussieht
Entschieden am 2026-09-09, zwei Punkte davon mit dem Auftraggeber:

**Erst Attribute unter ORM 2.20, dann der Versionssprung.** Der Annotation-Driver fällt in ORM 3
weg, man könnte also beides in einem Zug tun. Dagegen spricht die Abnahmegrundlage: Wenn Umbau
und Versionssprung zusammenfallen, ist jede Abweichung mehrdeutig — liegt sie an den Attributen
oder an ORM 3? Getrennt ist die Antwort in beiden Fällen eindeutig. Das ist dieselbe Überlegung,
die in `009-005` DBAL vor den Kernel-Schnitt gezogen hat, und dort hat sie 173 Fehlschläge
sauber einer Ursache zugeordnet.

**`doctrine/cache` fällt vor dem Sprung, nicht mit ihm.** ORM 3 nimmt nur noch PSR-6, aber ORM
2.20 nimmt es **auch schon**. Damit ist es dieselbe Trennung wie oben und nebenbei ein Paket
weniger im Sprung.

**Die Krypto-Umstellung ist eine eigene Story in diesem Epic.** Sie berührt kein Doctrine-Thema,
sondern `StringType` und `TextareaType` — aber sie hängt am selben Entity-Layer und wäre als
eigenes Epic ein Vorhaben mit einer einzigen Story.

## Stories
<!-- Die Stories dieses Epics. Wird von /new-story synchron gehalten. -->
- [x] 010-001-0000 — Annotationen auf PHP-Attribute — noch unter ORM 2.20
- [x] 010-002-0000 — doctrine/cache durch einen PSR-6-Cache ersetzen
- [x] 010-003-0000 — Der ORM-3-Sprung
- [x] 010-004-0000 — StringType von AES-CBC auf AEAD heben
- [x] 010-005-0000 — Die Gates leeren und die Altbefunde aus Epic 009 abräumen

## Ergebnis (2026-09-10)

**Doctrine steht auf ORM 3.7, die Entities tragen PHP-Attribute, die Caches laufen über PSR-6,
und die Feldverschlüsselung ist authentifiziert.** Die Suite wächst von 268 auf **282**, auf
PHP 8.3 wie auf 8.4.

### Die Erfolgskriterien, gemessen

| Kriterium | Stand |
|---|---|
| ORM auf einer releasten, gepflegten Version | **3.7.0**; DBAL unverändert 3.10.6 |
| Annotationen → PHP-Attribute | 202 Angaben umgestellt; im Baum steht keine Annotation mehr als Metadatum |
| `doctrine/annotations` weg | ja, mit `010-001-0005` |
| `Base` und die eigenen Typen bleiben öffentliche API | unverändert; was sich ändert, steht in `breaking-changes.md` |
| Krypto auf AEAD | XChaCha20-Poly1305, mit `appcms:security:reencrypt` samt Trockenlauf |
| Konstanten in Attributen | funktionieren **besser** als vorher — ein Attribut ist PHP-Code |
| Ausnahmelisten der Gates leer | `composer audit` 0 abandoned, PHPStan **eine** Ausnahme (aus DBAL) |
| Testnetz grün | `OK (282 tests, 692 assertions)`, 0 übersprungen |

### Was der Schnitt getragen hat

Vier von fünf Stories folgten derselben Regel: **vorbereiten unter der alten Version, dann
umlegen.** `010-001` brachte die Attribute unter ORM 2.20, `010-002` den PSR-6-Cache ebenfalls,
`010-003-0001` alles, was ORM 3 nicht brauchte. Erst danach der Sprung.

Der Nutzen ist an einer Zahl abzulesen: Beim Sprung selbst standen **196 von 268** Tests rot,
und die Ursachen liessen sich einzeln benennen, weil alles andere schon gemessen war.

### Sechs Befunde, die ohne dieses Epic nicht sichtbar geworden wären

1. **Ein Trait trug `@ORM\Column` ohne `use`-Import** und funktionierte nur, weil
   `getDeclaringClass()` bei einer Trait-Eigenschaft die benutzende Klasse liefert. Als Attribut
   fiel das Feld **still** aus dem Schema (`010-001-0003`).
2. **Der Metadaten-Cache hat nie gegriffen** — er wurde nach dem EntityManager gesetzt, und der
   liest ihn genau einmal (`010-002-0005`).
3. **Der `apc`-Treiber konnte auf keiner unterstützten PHP-Version laufen**, und `memcached`
   hatte **nie einen Server** (`010-002-0002`).
4. **Ein Metadaten-Cache aus ORM 2 macht ORM 3 unbrauchbar** — mit einer Meldung, die nirgends
   auf den Cache zeigt (`010-003-0002`).
5. **AES-CBC ohne MAC ist manipulierbar**, an einem Beispielsatz vorgeführt: ein Block Müll, der
   Rest intakt, und die Anwendung liefert es aus (`010-004-0001`).
6. **Der `modified_index` wurde nie angelegt**, weil sein Listener nur bei `is_installed`
   registriert wurde (`010-005-0002`).

### Ein Muster, dreimal derselbe Fehler

Drei der sechs Befunde haben dieselbe Form: **Eine Angabe, die für jeden EntityManager gelten
muss, stand neben der Factory statt darin.**

| Fall | Task |
|---|---|
| Der Metadaten-Cache erreichte die `ClassMetadataFactory` nie | `010-002-0005` |
| Der Installer wiederholte den Mapping-Block, und die Wiederholung wich ab | `010-003-0002` |
| Der `modified_index`-Listener griff beim Installieren nicht | `010-005-0002` |

Die Regel steht jetzt im Code der Factory. Sie war teurer zu lernen als aufzuschreiben.

### Regel 3 hat sich sieben Mal gemeldet

Keine der acht PHPStan-Ausnahmen wurde weggeräumt. Jede wurde in dem Task gegenstandslos, der
ihre Ursache beseitigte, und der Lauf wurde rot, bis sie verschwand. Genau dafür gibt es die
Regel — die Liste räumt sich nicht von allein, aber sie meldet sich.

### Was das Epic verlässt, mit benanntem Auflöser

- **`000-000-0025`** — `BaseI18nTree`: Die Beziehung passt nicht zum zusammengesetzten
  Schlüssel. Nicht behoben, weil niemand von der Klasse erbt und die Frage fachlich ist:
  Trägt ein Elternknoten dieselbe Sprache? Semantik für unbenutzten Code zu erfinden und dabei
  ein Tabellenschema zu ändern, ist die falsche Reihenfolge.
- **`000-000-0024`** — Eine Ausnahme aus dem Bootstrap antwortet mit einer leeren 500, weil sie
  anfällt, bevor der Kernel existiert.
- **Die eine PHPStan-Ausnahme** für `ReservedWordsCommand` kommt aus **DBAL**, nicht aus dem
  ORM. Epic `010` löst sie nicht auf; sie steht mit ihrem Vermerk in `phpstan.neon.dist`.
- **20 ungenutzte Imports im `ApiController`** (`010-001-0002`) — ein Aufräum-Task, kein Befund.
- **Die Doppelung des Mapping-Blocks in `InstallCommand`** ist entschärft, aber nicht
  aufgelöst (`010-003-0002`).
