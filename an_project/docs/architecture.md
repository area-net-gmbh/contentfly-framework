<!-- PURPOSE: Architektur DIESES Projekts — Komponenten, Grenzen, Datenfluss und die Entscheidungen dahinter. -->

# Contentfly Framework — Architecture

<!-- Die Architektur des Frameworks selbst steht in
     .an_framework/docs/framework-architecture.md. Diese Datei beschreibt das Produkt. -->

## Overview

<!-- Was das System ist, in einem Absatz. Ein Diagramm, wenn es hilft. -->

## Components

<!-- Jeder größere Teil: was er tut, was er besitzt, mit wem er spricht. -->

## Boundaries & contracts

<!-- Die Schnittstellen, die stabil bleiben müssen. -->

## Key decisions

<!-- Entscheidung · erwogene Alternativen · warum diese. -->

### 2026-09-15 — `BaseI18nTree` bleibt; ein Elternknoten hat dieselbe Sprache

**Entscheidung.** `BaseI18nTree` und `BaseI18nSortable` bleiben im Framework. Die Beziehung
`treeParent` zeigt auf den Elternknoten **in derselben Sprache** und trägt dafür eine Join-Spalte je
Schlüsselspalte: `parent_id → id` und `parent_lang → lang`. `orm:validate-schema` ist damit in
beiden Hälften grün, zum ersten Mal, seit der Befund in `009-005` aufkam (`000-000-0025`).

#### Warum dieselbe Sprache

Die Frage stand nirgends geschrieben, aber das Framework hat sie an drei Stellen längst
beantwortet:

| Stelle | was sie tut |
|---|---|
| `JoinType::toDatabase()` | referenziert ein i18n-Ziel über `id` **und die Sprache des geschriebenen Objekts** |
| `Api::getTree()` | verbindet `treeParent` mit `parent.lang = :lang`, der Sprache der gelisteten Kinder |
| `Api::getTree2()` | verbindet `pim_i18n_tree` über `t.lang = e.lang` und baut die Hierarchie innerhalb einer Sprache |

Ein Kind, dessen Elternknoten in einer anderen Sprache läge, hätte keiner dieser Wege je
gefunden. Die Entscheidung erfindet also keine Semantik, sie schreibt die vorhandene ins Mapping.

Einen Pfad gab es, der davon abwich: Beim Anlegen einer Übersetzung kopierte die API universelle
Felder vom Hauptsprachen-Objekt, also auch dessen Elternknoten **in der Hauptsprache**. Mit nur
`parent_id` fiel das nicht auf. Dieser Pfad bindet jetzt ebenfalls an die geschriebene Sprache.

#### Erwogene Alternative: `BaseI18nTree` und `BaseI18nSortable` ersatzlos streichen

Dafür sprach: Im Framework und in der Vorlage erbt niemand von der Klasse, und die Absicht lässt
sich an keinem Nutzer prüfen.

**Verworfen, weil die Klasse nicht allein steht.** `Api` hat eigene i18n-Zweige für Bäume in
`getTree()`, `getTree2()` und `getCount()`, `LoadMetadata` nimmt sie aus. Das Streichen hätte eine
Funktion entfernt, die ein Bestandsprojekt nutzen kann, und dafür eine Bruchstelle ohne
Nachfolger erzeugt. Die Reparatur dagegen kostet eine Spalte in einer Tabelle, die im Framework
leer ist.

#### Was es kostet

- **Eine Schemaänderung** an `pim_i18n_tree`: Spalte `parent_lang`, Index und Fremdschlüssel über
  beide Spalten. Der Datenbankvergleich einer frischen Installation zeigt genau das und sonst
  nichts.
- **Eine Übersetzung kann nur unter einen Elternknoten, dessen Übersetzung existiert.** Der
  Fremdschlüssel erzwingt jetzt, was die Lesewege schon voraussetzten.
- **Ein Bestandsprojekt mit eigener Unterklasse migriert.** Der Weg ist durchgespielt und steht in
  `an_project/docs/breaking-changes.md`.

`lang` selbst kann nicht zugleich Join-Spalte sein: Es ist ein Identifier-Feld, und Doctrine lehnt
eine doppelt gemappte Spalte ab.

### 2026-09-11 — Der `$app[...]`-Zugriff bleibt, mit fester Schlüsselliste

**Entscheidung.** Der `ArrayAccess`-Zugriff auf den Container — `$app['orm.em']`,
`$app['db']`, `$app['routeManager']` — ist **dauerhaft** Teil der öffentlichen Framework-API.
Er ist keine befristete Migrationshilfe. Dazu gehört eine **feste Liste** der Schlüssel, auf
die ein Projekt sich verlassen darf; ohne sie ist die Zusicherung leer.

**Ein Bestandsprojekt muss seine Controller dafür nicht anfassen.** Das ist der Punkt: Die
Frage stand offen, und solange sie offen stand, konnte kein Projekt den Aufwand seiner
Migration abschätzen.

#### Der gemessene Stand

Nachgezählt am 2026-09-11, nicht geschätzt:

| | |
|---|---|
| Registrierte Schlüssel | 24, alle in `lib/contentfly/bootstrap.php` |
| Lesezugriffe | 134 im Framework, 25 in der Suite, 15 in der Vorlage |
| Häufigster | `$app['orm.em']`, 35-mal |
| Unbekannter Schlüssel | wirft `InvalidArgumentException` und nennt den Namen |
| Typisierter Zugang | gibt es nicht; `ArrayAccess` ist der einzige Weg |

#### Erwogene Alternative: befristet, mit Deprecation und Frist

Der Zugriff wäre zur Migrationshilfe erklärt worden, mit benanntem Nachfolger, Frist und einem
Lauf, der einem Projekt zeigt, welcher Aufruf betroffen ist.

**Verworfen, und der Grund ist eine Zahl: 134.** So oft benutzt das Framework die Bridge
**selbst**. Eine Deprecation, die für Projekte gilt, müsste im Framework zuerst durchgezogen
werden — sonst meldet der eigene Code bei jedem Request die Warnung, und das Deprecation-Gate
aus `006-005` wäre ab dem ersten Tag rot. Das ist ein eigener Umbau mit eigenem Aufwand, kein
Migrationsschritt; ihn in ein Epic zu packen, das Bestandsprojekten den Weg bahnen soll, hiesse
diesen Weg zu verlängern statt ihn zu bahnen.

**Der zweite Grund ist der fehlende Nachfolger.** Eine Deprecation ohne benannten Ersatz ist
keine Migrationshilfe, sondern eine Drohung. Der Ersatz wäre Konstruktorinjektion in 134
Aufrufstellen plus in jedem Projekt-Controller — und *diese* Entscheidung steht nirgends an.

#### Erwogene Alternative: dauerhaft, aber ohne Liste

Am wenigsten Arbeit, und sie beantwortet die Frage der Story nicht. Ein Projekt wüsste weiterhin
nicht, worauf es sich verlassen darf: Ob `$app['schema']` morgen noch existiert oder ob es nur
interne Verdrahtung war, stünde nirgends. **Eine Zusicherung ohne Gegenstand ist keine.**

#### Warum diese

1. **Sie bestätigt eine Zusage, die schon gegeben ist.**
   `an_project/docs/breaking-changes.md` führt seit Epic `009` unter *Was sich für ein Projekt
   nicht ändert*: „`$app['schlüssel']` — lesen und setzen. **Zugesichertes API**." Diese
   Entscheidung erfindet die Frage nicht neu, sie schliesst sie ab. Das gehört gesagt, damit
   niemand später glaubt, sie sei nie gestellt worden.
2. **Der Preis ist bekannt und klein.** Das Muster bleibt, was es ist: ein Container ohne
   Typen. Wer typisierten Zugang will, kann ihn *daneben* stellen, ohne diese Entscheidung zu
   brechen — sie sichert zu, dass `$app[...]` funktioniert, nicht dass es der einzige Weg bleibt.
3. **Der Nutzen fällt sofort an.** Ein Bestandsprojekt kann seine Controller unverändert
   übernehmen, und das ist der grösste Einzelposten, den Epic `007` ihm ersparen kann.

**Revidieren, wenn** ein typisierter Zugang gebaut wird und sich im Framework durchsetzt. Dann
ist neu zu entscheiden, ob `$app[...]` *daneben* bestehen bleibt oder eine Frist bekommt — und
dann gibt es den Nachfolger, der heute fehlt. Vorher nicht: Eine Deprecation ohne Ersatz
verschiebt Arbeit, statt sie zu ersparen.

### 2026-09-11 — Das Framework wird ein Bibliothekspaket, und dieses Repo ist zugleich das Skeleton

**Entscheidung.** `lib/contentfly/` wird zum Composer-Paket `areanet/contentfly` mit
`type: library` und **einem** Namensraum, `Areanet\PIM\`. Ein Projekt bezieht es über
`composer require` statt den Baum zu kopieren. Ein zweites, eigens gepflegtes Skeleton-Paket
entsteht **nicht**: Die Wurzel dieses Repos — abzüglich der Einträge, die nur der Entwicklung
dienen — **ist** das Skeleton.

#### Die Zuordnung, Eintrag für Eintrag

Alle 26 versionierten Top-Level-Einträge, Stand 2026-09-11. Kein Eintrag bleibt offen.

| Eintrag | wohin | warum |
|---|---|---|
| `lib/` | **Paket** | der Frameworkcode |
| `index.php` | Projekt | Einstiegspunkt, gehört dem, der ausliefert |
| `bin/` | Projekt | `console.php` und `cli-config.php`, aus demselben Grund |
| `custom/` | Projekt | Projektcode und Konfiguration; bleibt die Vorlage |
| `plugins/` | Projekt | der Slot des Projekts, künftig in **dessen** Manifest deklariert |
| `data/` | Projekt | beschreibbare Laufzeitverzeichnisse; versioniert sind nur vier `.gitkeep` |
| `.htaccess` · `robots.txt` · `favicon.ico` | Projekt | Artefakte des Webservers, nicht der Bibliothek |
| `composer.json` · `composer.lock` | beide, getrennt | das Paket deklariert seine Abhängigkeiten, das Projekt seine — inklusive des Pakets |
| `LICENSE` | beide | ein Paket ohne Lizenz ist keines |
| `README.md` | beide, verschieden | das Paket beschreibt das Paket, das Projekt das Projekt |
| `tests/` | Entwicklungs-Repo | die Suite prüft das Framework **durch eine Installation**; sie gehört zur Entwicklung, nicht in den Lieferumfang |
| `tools/` | Entwicklungs-Repo | CI-Werkzeuge |
| `phpunit.xml.dist` · `phpstan.neon.dist` · `.gitlab-ci.yml` · `docker-compose.yml` · `build.xml` · `apidoc.json` | Entwicklungs-Repo | dito |
| `an_project/` · `.an_framework/` · `.claude/` · `CLAUDE.md` | Entwicklungs-Repo | Arbeitsorganisation, kein Lieferbestandteil |
| `.gitignore` | je Repo | — |

#### Warum kein eigenes Skeleton-Paket

**Erwogen: ein zweites Paket `areanet/contentfly-skeleton`** für `composer create-project`.
Verworfen aus zwei Gründen. Erstens liefe es auseinander: Ein Skeleton, das niemand fährt, ist
dieselbe Fehlerart wie eine Anleitung, der niemand folgt — und gefahren wird hier die Wurzel
dieses Repos, gegen die auf jedem Commit die volle Suite läuft. Zweitens verdoppelte es die
Release-Fläche von Epic `011`, das ohnehin eine neue Hauptversion vergibt.

**Erwogen: nur das Bibliothekspaket, das Projektgerüst bloss beschrieben.** Verworfen, weil
dann jedes Projekt den Rahmen selbst nachbaut und jedes ein bisschen anders — und weil eine
Beschreibung keinen Test hat.

**Was bleibt zu tun, wenn `create-project` gewünscht ist:** Die Wurzel wird als Skeleton
veröffentlicht. Das ist ein Verpackungsschritt für `011`, kein Bau.

#### Wie das Paket die Konfiguration des Projekts findet

**Der Einstiegspunkt übergibt das Projektverzeichnis, ausdrücklich.** Das Paket erwartet die
Konfiguration darunter an der bisherigen Stelle, `custom/config.php`. Fehlt sie, bricht der
Start mit einer Meldung ab, die **den erwarteten Pfad** nennt.

**Erwogen: eine Umgebungsvariable.** Verworfen — sie ist im Code unsichtbar und lässt sich
global falsch setzen, mit einer Wirkung, die niemand an der Aufrufstelle sieht.

**Erwogen: nach oben suchen, bis eine `composer.json` auftaucht.** Verworfen, und zwar mit
Nachdruck: Das ist Raten, und Raten ist genau das, was `ROOT_DIR` getan hat. Ein geratener Pfad
existiert entweder zufällig oder erzeugt eine Meldung über die falsche Sache.

#### Wie ein Projekt Frameworkverhalten überschreibt

**Über die Registrierungsstellen, die es schon gibt** — `custom/app.php` für Routen und
Login-Provider, die Konfigurationskonstanten für Typen (`APP_SYSTEM_TYPES`,
`APP_CUSTOM_TYPES`), die Plugin-Schnittstelle. **Nicht mehr durch Danebenlegen im selben Baum**;
das ist ab jetzt unmöglich, weil der Frameworkcode in `vendor/` bei jedem Update überschrieben
wird.

**Das kostet weniger als befürchtet, und das ist nachgemessen:** Das Framework verweist auf
**keine** Projektklasse. Die beiden einzigen Verweise waren tote Importe —
`Classes/Types/OnejoinType` importierte `Custom\Entity\TestMeta`,
`Controller/SystemController` importierte `Custom\Entity\Ansprechpartner`, und **beide Klassen
existieren nicht**, in keinem der beiden Bäume. Ein ungenutztes `use` wertet PHP nie aus,
deshalb ist es nie aufgefallen. Sie fallen mit `007-001-0004`.

**Fehlt für einen Fall eine Registrierungsstelle,** ist das eine Lücke des Frameworks und wird
als Task aufgeschrieben — nicht durch Kopieren umgangen. Eine Umgehung, die funktioniert,
verhindert, dass die Lücke je geschlossen wird.

#### Was aus `custom/vendor/` und „Framework schlägt Projekt" wird

**Beides fällt.** Ein Projekt hat künftig **einen** Composer-Baum, in dem `areanet/contentfly`
als Abhängigkeit liegt. Damit gibt es keine zwei Bäume mehr, zwischen denen eine Rangfolge zu
zusichern wäre — die Frage stellt sich nicht mehr, statt anders beantwortet zu werden.

**Der Ersatz ist stärker als die Zusicherung, die er ablöst.** Die alte Regel hielt eine
Überschneidung *fern*, solange ein Test die Bedingung prüfte. Composer *verweigert* unvereinbare
Constraints beim Auflösen — der Fall `psr/log` in 1.1.3 und 3.0.2 gleichzeitig im Prozess kann
gar nicht mehr entstehen.

**`AutoloaderOverlapTest` wird umgedreht, nicht gelöscht.** Er prüft danach, dass es
genau einen Baum gibt. Ein Test, der eine Bedingung bewacht hat, die weggefallen ist, bewacht
danach, dass sie weggefallen bleibt.

> **Umgesetzt mit `007-001-0003`.** Der zweite Autoloader wird nicht mehr geladen; ein
> liegengebliebenes `custom/vendor/` weist `Classes\Kernel\Start` zur Laufzeit ab, statt es
> stillschweigend zu übergehen. Der Test prüft drei Dinge: dass der Baum des Projekts das
> Framework führt, dass es keinen zweiten gibt, und dass das Framework seinen eigenen
> Autoloader nicht mehr lädt.
>
> **Dabei ist ein Fall aufgefallen, den diese Entscheidung nicht bedacht hatte:**
> `Classes/Plugin::initComposer()` lädt den eigenen `vendor/`-Baum **jedes Plugins**. „Ein Baum
> je Projekt" gilt damit nicht ausnahmslos, und die Überschneidungsgefahr aus `006-004` ist je
> Plugin zurück — ungeprüft. Der Test führt es als benannte Ausnahme; entschieden wird es mit
> `007-001-0004`, zusammen mit der Frage, was aus `plugins/` wird.

#### `plugins/` bleibt der Slot des Projekts — mit einer benannten Ausnahme

**Entschieden mit `007-001-0004`.** Der Namensraum `Plugins\` steht jetzt im Manifest des
Projekts, nicht mehr in dem des Frameworks. Das Verzeichnis ist im Entwicklungs-Repo leer;
versioniert liegt dort keine Datei.

**Die Ausnahme ist der eigene Composer-Baum je Plugin.** `Classes/Plugin::initComposer()` lädt
`plugins/<key>/vendor/autoload.php`, wenn es existiert. Damit gilt „ein Baum je Projekt" nicht
ausnahmslos, und die Überschneidungsgefahr aus `006-004` ist je Plugin zurück.

**Sie bleibt trotzdem, und zwar begründet.** Ein Plugin ist kein Bestandteil des Projekts im
Sinne von Composer: Es wird nicht aufgelöst, sondern als Verzeichnis abgelegt. Ihm seine
Abhängigkeiten zu nehmen hiesse, jedes Plugin in das Manifest des Projekts zu zwingen — und
damit genau die Vermischung wiederherzustellen, die diese Entscheidung auflöst, nur an anderer
Stelle.

**Was dafür der Preis ist, steht hier und nicht nur im Code:** Lädt ein Plugin ein Paket, das
das Projekt in einer anderen Version führt, gewinnt der zuerst geladene Baum — und das ist der
des Projekts. Ein Plugin, dessen Pakete kollidieren, ist ein Fehler des Plugins.

**Ungeprüft, und das wird ausdrücklich gesagt.** `plugins/` ist leer; es gibt hier nichts, wogegen
sich das messen liesse. `tests/Unit/AutoloaderOverlapTest.php` führt den Fall als
benannte Ausnahme mit Begründung, damit er sichtbar bleibt statt unterzugehen.

**Revidieren, wenn** ein Projekt Pakete braucht, die es dem Framework *vorenthalten* muss —
dann wäre ein zweiter Baum wieder ein Mittel. Heute gibt es diesen Fall nicht: Nach
`006-001-0004` gehört kein einziges der ehemals neun `custom/`-Pakete dorthin, und
`custom/composer.json` hat ein leeres `require`.

### 2026-09-09 — Der Antwort-Envelope wird vereinheitlicht, aber erst mit dem Release

**Entscheidung.** Die sieben verschiedenen Antwortformen der API werden auf eine gebracht:
`data`, `errors`, `meta`. Der Umbau gehört in Epic `011`, nicht in `009`. Form und Zeitpunkt
stehen in `an_project/docs/api-envelope.md`, samt der Tabelle *vorher → nachher* je Endpunkt.

**Erwogene Alternativen.** *Jetzt vereinheitlichen* — verworfen, weil die
Charakterisierungstests aus Epic `008` die Abnahmegrundlage für den Kernel-Wechsel sind. *Mit
dem Kernel-Wechsel in `009`* — verworfen aus demselben Grund, verschärft: Wären beide Seiten
des Vergleichs neu, liesse sich eine Abweichung nicht mehr dem Umbau oder der Absicht
zuordnen. *Gar nicht vereinheitlichen* — verworfen, weil ein Client sonst für jeden Endpunkt
eine eigene Auswertung braucht.

**Warum diese.** Erst der Kernel unter unverändertem Vertrag, dann der Vertrag als eine
bewusste Bruchstelle mit Migrationsleitfaden. `011` vergibt ohnehin eine neue Hauptversion —
ein Bruch braucht eine Nummer, an der man ihn festmachen kann.

**Der `ApiResponseService` der Vorlage ist das Vorbild, aber nicht wörtlich.** Übernommen:
gleiche Form für Erfolg und Fehler, `data` immer vorhanden, `meta` für alles, was keine
Nutzlast ist, `errors` als Liste. Nicht übernommen: `success` und `status`, weil sie den
HTTP-Statuscode im Rumpf wiederholen und damit zwei Quellen für dieselbe Aussage schaffen; und
`i18n`, weil ein Übersetzungsschlüssel eine Anforderung des Projekts ist, nicht des Frameworks.

**Revidieren, wenn** ein Bestandsprojekt vor `011` auf den neuen Kernel muss und dabei den
neuen Vertrag braucht. Dann ist die Reihenfolge zu tauschen und der Vergleich für `009` anders
abzusichern — nicht stillschweigend.

### 2026-09-09 — Zwei Composer-Bäume, Root vor `custom/` (nicht ein Autoloader)

> **REVIDIERT AM 2026-09-11 durch die Entscheidung darunter** (*Das Framework wird ein
> Bibliothekspaket*). Sie hat es selbst so vorgesehen — siehe *Revidieren, wenn* am Ende dieses
> Eintrags. Der Text bleibt stehen, weil er die Begründung des Zustands trägt, den ein
> Bestandsprojekt heute noch vorfindet.

**Entscheidung.** Framework und Projekt behalten **je ein eigenes Manifest**.
`lib/contentfly/bootstrap.php` lädt `vendor/autoload.php` zuerst und
`custom/vendor/autoload.php` danach, falls es existiert. Bei einem PSR-4-Präfix, das beide
führen, **gewinnt der Root** — ab jetzt als Zusicherung, nicht als Nebenwirkung der
Ladereihenfolge. Bedingung dafür ist, dass sich die Bäume nicht überschneiden, und diese
Bedingung wird **geprüft** (`006-004-0003`).

**Erwogene Alternative: ein Autoloader.** `custom/` bekäme kein eigenes `vendor/` mehr;
projektspezifische Pakete stünden im Root-Manifest.

**Warum zwei Bäume.**

1. **Epic `007` braucht die Grenze.** Ein migrierendes Bestandsprojekt muss unterscheiden
   können, was es mitgeliefert bekommt und was es selbst deklariert. Ein gemeinsames Manifest
   löscht genau diese Linie — und mit ihr die Antwort auf die Frage, was beim nächsten
   Framework-Update überschrieben werden darf.
2. **`custom/` ist die Vorlage, nicht ein halbes Projekt** (`an_project/docs/technical.md`).
   Ein leerer, aber vorhandener Slot lehrt, wohin projektspezifische Pakete gehören. Ein
   fehlender lehrt nichts.
3. **Der Preis ist klein geworden.** Nach `006-001-0004` gehört **kein einziges** der neun
   `custom/`-Pakete dorthin; nach `006-003` wird der zweite Baum nicht mehr gebaut. Die
   Doppelung, gegen die die Regel schützt, existiert heute nicht — die Regel hält sie fern.

**Der Preis, ausgesprochen.** Eine Bedingung, die niemand prüft, ist keine Zusicherung. Genau
daran ist der alte Zustand gescheitert: `psr/log` lag in 1.1.3 und 3.0.2 gleichzeitig im
Prozess, dazu zwei `symfony/polyfill-*` in unvereinbaren Ständen und ein handkopiertes
`PHPMailer\PHPMailer\`, das in keiner `installed.json` stand. Jahrelang, ohne dass es jemandem
auffiel. Die Zusicherung dieser Entscheidung hängt deshalb **vollständig** an der Prüfung aus
`006-004-0003`; fällt die weg, fällt die Entscheidung mit.

**Wie ein neues Paket eingeordnet wird.** Nicht hier, sondern in
`tools/dependency-assignment.json` — dort steht die fünfstufige Regel (benutzt `lib/` es? ·
benutzt die ausgelieferte Vorlage es? · nur Projektcode? · nur Tests? · niemand?) samt der
Begründung für jedes bereits eingeordnete Paket. Eine zweite Fassung hier würde auseinanderlaufen.

**Revidieren, wenn:** Epic `007` entscheidet, das Framework als Composer-Paket auszuliefern
statt als kopierten `lib/`-Baum. Dann verschiebt sich die Grenze vom Verzeichnis auf die
Paketgrenze, und ein zweites Manifest im Projekt hat einen anderen Zuschnitt als heute.

### 2026-09-04 — Ziel-Kernel: Symfony 7.4 LTS (nicht Symfony 8.x)

**Entscheidung.** Der neue Kernel wird auf **Symfony 7.4 LTS** gebaut. Verbindlich
dazu: von Tag 1 deprecation-frei entwickeln, mit Deprecation-Log und PHPStan als CI-Gate.
Geplanter Nachfolger ist **Symfony 8.4 LTS** (erwartet Nov 2027) — als Constraint-Bump, nicht
als zweites Migrationsprojekt.

**Erwogene Alternative: Symfony 8.1** (im Sept 2026 die aktuelle stabile Zeile).

**Warum 7.4.**

1. Symfony 8.0 erschien am selben Tag wie 7.4 und hat **denselben Funktionsumfang** — der Major
   löscht nur deprecated Code und hebt das PHP-Minimum von 8.2 auf 8.4. Es gibt in 8.x kein
   Feature, das in 7.4 fehlt; ein Verzicht entsteht nicht.
2. **Support-Fenster passt zum realen Wartungsverhalten.** Silex ist seit 2018 EOL und läuft
   hier immer noch; Doctrine ORM hängt auf einem Dev-Branch. 7.4 trägt ohne Zutun bis Nov 2029.
   Die 8.x-Zeile verlangt alle 6 Monate ein Minor-Upgrade (8.1 → 8.2 → 8.3 → 8.4) — wer den
   Takt nicht mitgeht, sitzt ab Jan 2027 wieder ohne Security-Fixes da, also exakt in der
   Situation, aus der diese Migration herausführen soll.
3. **PHP-Entscheidung bleibt entkoppelt.** 7.4 verlangt nur PHP ≥ 8.2 und läuft auf 8.3 wie auf
   dem Zielstand 8.5. Solange Alt- und Neustand nebeneinander laufen, ist die PHP-Anhebung
   dadurch kein Vorab-Blocker. Symfony 8.x setzt PHP ≥ 8.4 in *jeder* Umgebung voraus — lokal
   läuft heute 8.3, und Bestandsprojekte bringen mit, was sie mitbringen.
4. **Der Migrationszeitraum selbst bleibt geschützt.** Mit 8.1 fiele mitten in den Kernel-Umbau
   (009) ein Pflicht-Upgrade auf 8.2, bevor das Testnetz aus 008 überall grün ist.

**Was die Entscheidung nicht beeinflusst.** Der eigentliche Aufwandstreiber ist versionsneutral:
Doctrine ORM 2 → 3 (Annotationen entfallen ersatzlos, alle Entities auf PHP-Attribute), das
Auflösen des Dev-Branch-Pins `doctrine/orm dev-bugfix-many2many` und der Ersatz des abandoned
`doctrine/annotations` — siehe Epic 010.

**Revidieren, wenn:** PHP ≥ 8.4 bei allen Bestandsprojekten gesetzt ist, das Versprechen „läuft
auf Standard-Providern" aus dem README entfällt **und** ein verbindliches Upgrade-Fenster alle
6 Monate eingeplant ist.

**Eingelöst mit Epic `009` (2026-09-09).** Der Kernel steht auf Symfony 7.4, Silex und Pimple
sind aus dem Baum, und die Zusicherung „deprecation-frei" ist gemessen statt zugesagt: 0
Deprecations auf PHP 8.3 **und** 8.4, PHPStan blockierend und ohne Befund im eigenen Code, 0
Advisories. Der Text oben bleibt so stehen, wie er am 2026-09-04 geschrieben wurde — das Argument
„Silex läuft hier immer noch" war zu diesem Zeitpunkt richtig und ist der Grund, warum die
Entscheidung so ausfiel. Eine Begründung nachträglich in die Vergangenheitsform zu setzen, macht
sie unlesbar.
