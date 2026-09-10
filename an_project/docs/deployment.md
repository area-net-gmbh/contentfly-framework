<!-- PURPOSE: Umgebungen, CI/CD-Pipeline und Release-Prozess. Das lokale Setup nach dem Checkout steht im runbook.md, nicht hier. -->

# Deployment

## Aus diesem Repo wird nichts produktiv deployt

Dieses Repo ist die Werkbank für das Update des Frameworks (siehe *Scope* in
`an_project/project-description.md`). Es gibt hier keine Produktivumgebung, kein Rollout und
keine Ausfallzeit zu schützen. „Deployment" heißt in diesem Projekt: **Wie eine neue
Framework-Version an Bestandsprojekte ausgeliefert wird** — und wie diese darauf migrieren.

## Abhängigkeiten: Composer als Quelle

**Entschieden am 2026-09-04, hergestellt am 2026-09-09 mit Story `006-003`.** Composer ist die
einzige Quelle der Wahrheit für Abhängigkeiten.

- Im Repo liegen `composer.json` und `composer.lock` — **kein `vendor/`**. Beide Bäume sind seit
  `006-003-0001` aus dem Index gelöst (11 208 Dateien) und werden von `.gitignore` gefasst.
- Wo ein Zielsystem kein Composer hat, wird der `vendor/`-Baum in der Pipeline gebaut
  (`composer install --no-dev --optimize-autoloader`) und als Teil des Deployment-Artefakts
  ausgeliefert. In `006-003-0002` gegen einen frischen Klon geprüft: **49 Pakete, 2 694 Dateien,
  22 MB**, Console und HTTP booten daraus.
- `composer audit --locked` läuft als **blockierendes** CI-Gate gegen den Lock (Story `006-005`).
  Was es prüft und was bei einem Fund zu tun ist, steht unten unter *Die Gates*.

**Was das für jeden Checkout heisst:** Ohne `composer install` ist er nicht lauffähig. Der
Ablauf steht in `an_project/docs/runbook.md`, Schritt 1.

> **Offen, und ein Blocker für die Pipeline:** Das CI-Image `php:8.3-cli` kann `composer install`
> heute nicht ausführen — es hat weder die `zip`-Extension noch `unzip`, `7z` oder `git`.
> Festgehalten in Task `000-000-0021`.

Der committete `vendor/`-Baum von früher wäre **kein** gültiger Ersatz für den gebauten gewesen:
kein Manifest, kein Lock, kein Upgrade-Pfad, und 27 von 78 Paketen lagen als `source` ohne
`.git` (siehe `an_project/docs/technical.md`).

## Auslieferung an Bestandsprojekte

Wie Projekte auf die neue Version kommen, ist Epic `007-000-0000` — Migrationspfad, Upgrade-Doku
und Werkzeuge. Bis dahin offen:

- Wird das Framework künftig als **Composer-Paket** (`areanet/contentfly`) bezogen statt als
  kopierter `lib/`-Baum? Das ist die naheliegende Antwort auf „migrierbar" und sollte in 007
  entschieden werden.
- Welche PHP-Version bringen die Bestandsprojekte mit? Zielplattform ist PHP 8.5 — das ist die
  Untergrenze, die ein migrierendes Projekt erreichen muss.

## Umgebungen

Zwei, und keine davon produktiv:

- **Lokal** — Datenbank aus `docker-compose.yml`, PHP von der Maschine. Der Ablauf steht in
  `an_project/docs/runbook.md`.
- **CI** — `.gitlab-ci.yml`, siehe unten.

## Die Pipeline

**Angelegt am 2026-09-08 mit Story `008-005`.** GitLab CI, weil der Remote GitLab ist.

| Job | Stage | Was er tut |
|---|---|---|
| `check:template-config` | `check` | Verhindert, dass eine installierte `custom/config.php` in die Historie gerät |
| `check:audit` | `check` | `composer audit --locked` gegen die Ausnahmeliste — **blockierend** |
| `check:phpstan` | `check` | Statische Analyse gegen die Ausnahmeliste — **blockierend seit `009-003-0003`** |
| `test:php8.3` | `test` | Beide Testsuiten gegen eine frisch installierte Instanz — **pflicht** |
| `test:php8.4` | `test` | Derselbe Lauf auf PHP 8.4, `allow_failure: true` |

**Der 8.4-Job ist eine Frühwarnung, kein Gate.** Zielplattform ist PHP 8.5; was hier rot wird,
ist die Liste dessen, was auf dem Weg dorthin zu erledigen bleibt — aber es darf die Pipeline
heute nicht anhalten. Beim Anlegen lief er grün, mit einer einzigen Deprecation aus Silex; eine
zweite aus eigenem Code (`Classes\Config::__construct()`) kam mit `000-000-0021` dazu. Beide
sind weg, und **der Job ist heute grün, gemessen** — siehe *Was die Gates heute melden*. Ob er
deshalb blockierend wird, ist eine offene Entscheidung.

### Was die Pipeline voraussetzt
- **Ein Runner mit Docker-Executor.** Ohne ihn funktionieren weder `image:` noch `services:`.
  Steht nur ein Shell-Runner zur Verfügung, muss der Job Datenbank und PHP selbst mitbringen —
  ein anderer Zuschnitt, keine kleine Änderung. Die Annahme steht im Kopf der
  `.gitlab-ci.yml`.
- **`CONTENTFLY_TEST_ADMIN_PASS`** als CI-Variable (Settings → CI/CD → Variables). Bewusst
  nicht in der YAML: Auch für eine flüchtige Datenbank gehört ein Passwort nicht ins Repo.

### Warum die Schritte in `tools/ci/` stehen
Eine Pipeline-Definition, deren Schritte man nur in der Pipeline ausprobieren kann, ist beim
Suchen eines Fehlers nutzlos. `install-php-extensions.sh` und `prepare-test-environment.sh`
laufen deshalb lokal in Docker mit demselben Aufruf — so wurde die Definition auch abgenommen,
bevor sie je in einem Runner lief.

### Der Baum entsteht im Job
**Seit `006-002-0004`, und seit `006-003` ohne Rückfallebene.** PHPUnit liegt im
Root-`require-dev`, die Pipeline ruft `./vendor/bin/phpunit`. Die Vorsorge aus `008-005-0001` —
den Pfad als Variable an *einer* Stelle zu halten — hat sich ausgezahlt: Es war genau eine Zeile.

Der Job baut den Abhängigkeitsbaum selbst (`composer install`). Bis `006-003` lag daneben noch
der committete Baum im **alten** Stand ohne PHPUnit; der Installationsschritt überschrieb ihn.
Jetzt ist er weg, und der Schritt ist die einzige Quelle.

Was das kostet, ist in `006-003-0002` im Pipeline-Image gemessen:

| Schritt | Dauer |
|---|---|
| `tools/ci/install-php-extensions.sh` | 14 s |
| Composer selbst installieren | 1 s |
| `composer install --prefer-dist`, kalter Cache | **5 s** |

Der Installationsschritt ist der kleinste Posten. Ein Composer-Cache in der Pipeline lohnt an
dieser Stelle nicht — was der Lauf kostet, kostet das Herrichten des Images.

> **Erledigt mit `000-000-0021`.** Der Schritt lief eine Zeit lang gar nicht: `php:8.3-cli` hat
> weder die `zip`-Extension noch `unzip`, `7z` oder `git`, und `install-php-extensions.sh` baute
> nur `pdo_mysql` und `gd` — damit waren beide Wege zu. Der Bruch entstand mit `006-002-0004`
> und fiel erst in `006-003-0002` auf, weil dort das Image zum ersten Mal seit `008-005-0001`
> wieder von Null gefahren wurde. `unzip` gehört seitdem zum Skript; `git` bewusst nicht, die
> Begründung steht dort.

## Der Cache beim Deployment

**Beide Doctrine-Caches müssen beim Ausrollen geleert werden.** Das war vorher nur zur Hälfte
wahr und ist es seit `010-002` ganz.

| Cache | Ort bei `APP_CACHE_DRIVER = 'filesystem'` | Inhalt |
|---|---|---|
| Abfrage | `data/cache/query` | übersetzte DQL |
| Metadaten | `data/cache/metadata` | die Zuordnung Entity → Tabelle |

**Warum es jetzt zählt:** Bis `010-002-0005` hat der Metadaten-Cache **nie gegriffen**. Der
Bootstrap setzte ihn auf der Konfiguration, nachdem der EntityManager schon gebaut war — und
`EntityManager::__construct()` liest ihn genau einmal. Wer eine Entity änderte, bekam die
Änderung sofort, weil die Metadaten bei jedem Request neu gelesen wurden. **Das ist vorbei.**
Ein Deployment, das den Cache stehen lässt, arbeitet danach gegen die alte Zuordnung.

Ausserdem hat sich mit `010-002-0001` das **Format** geändert — Symfonys Adapter statt
`doctrine/cache`. Alte Dateien werden weder gelesen noch aufgeräumt.

**Beim Sprung auf ORM 3 war das Leeren keine Empfehlung, sondern die Bedingung.** Ein
Metadaten-Cache aus ORM 2 wird von ORM 3 gelesen und liefert Unsinn — der Fehler lautet
`TypeRegistry::get(): Argument #1 ($name) must be of type string, null given` und zeigt nirgends
auf den Cache. 191 von 268 Tests standen rot; nach dem Leeren drei.

*Zwei Wege, den Cache zu räumen:* Die Verzeichnisse löschen, oder `POST /system/do` mit
`method=flushSchemaCache` aufrufen. Der Endpunkt leert beide Caches und die Datei
`data/cache/schema.cache`.

Im **Debug-Modus** und auf der **Konsole** ist kein Cache aktiv — dort soll niemand gegen
veraltete Metadaten arbeiten. Das gilt unverändert und ist der Grund, warum `appcms:install`
nichts in die Cache-Verzeichnisse schreibt.

## Die Gates

Vier Prüfungen, verankert mit Story `006-005`. Zwei blockieren, zwei melden:

| Prüfung | prüft | blockiert | wo |
|---|---|---|---|
| `composer audit --locked` | den Lock gegen die Advisory-Datenbank | **ja** | `tools/ci/audit.sh` |
| abgelaufene Audit-Ausnahmen | ob jede Ausnahme noch greift | **ja** | `tools/ci/audit-ausnahmen-pruefen.sh` |
| Deprecations zur Laufzeit | das Serverlog nach dem Testlauf | **ja** auf PHP 8.3, melden auf 8.4 | `tools/ci/deprecations-pruefen.sh` |
| PHPStan | deprecated APIs ohne Ausführung | nein (`allow_failure`) | `phpstan.neon.dist` |

Alle vier laufen mit demselben Aufruf lokal in Docker. Eine Pipeline-Definition, deren Schritte
man nur in der Pipeline ausprobieren kann, ist beim Suchen eines Fehlers nutzlos.

Die beiden Ausnahmelisten liegen getrennt, weil sie Verschiedenes ausnehmen:
`config.audit.ignore` in `composer.json` für die CVEs, `tools/ci/deprecations-ausnahmen.txt`
für die Deprecations. Beide werden nach demselben Muster geprüft — und in beiden macht ein
Eintrag, der nicht mehr greift, den Lauf rot.

### Wenn ein Gate anschlägt

**In dieser Reihenfolge fragen.** Wer gleich bei Frage 3 anfängt, schafft das Gate ab.

1. **Gibt es ein Release, das die Meldung behebt?** Dann Constraint anheben — und die
   Constraint-Kette aus `006-001-0003` gegenrechnen, bevor irgendetwas committet wird. Der
   Symfony-Deckel bei 4.4 ist mit Epic `009` gefallen; geblieben ist der von Doctrine —
   `doctrine/orm` 2.20 hält DBAL auf 3.x, und `doctrine/annotations` hängt am Metadaten-Weg.
   Wer daran vorbeigeht, bricht die Suite. Auflöser ist Epic `010`.
2. **Kein Release, aber ein Weg um die Nutzung herum?** Dann ist es ein Code-Ticket, kein
   Manifest-Ticket. Die drei `null`-Übergaben aus `000-000-0023` sind so ein Fall.
3. **Weder noch → Ausnahme.** Einzeln nach Kennung, **nie paketweise**, mit Begründung und dem
   Ticket oder Epic, das sie auflöst.

> **Eine Ausnahme ohne benannten Auflöser ist keine Ausnahme, sondern ein abgeschaltetes Gate.**

`symfony/http-foundation` als Ganzes auszunehmen wäre bequem und falsch: Es verschluckt auch
jede **künftige** Meldung dieses Pakets. Die CVE-Kennung ist die kleinste Einheit, die den Zweck
erfüllt; beim Deprecation-Gate ist es das Paar aus Datei und Meldung.

### Die Ausnahmen als Präzedenzfall — und wie sie verschwunden sind

Wer nur die Listen sieht, hält Ausnehmen für den Normalweg. Deshalb hier, wie es ausgegangen
ist. Als die Gates entstanden (`006-005`), standen neun Einträge in zwei Listen, **acht davon an
einer einzigen Ursache**: einem Stack, der bis zum Kernel-Tausch festlag. Genau so soll eine
Ausnahmeliste aussehen — mit einem benannten Auflöser, der sie als Ganzes räumt.

| Liste | Einträge damals | Ursache | heute |
|---|---|---|---|
| `config.audit.ignore` | 5 CVEs in `symfony/http-foundation`, `-routing`, `-validator` | Symfony 4.4 seit Nov 2023 EOL; jede Meldung betraf die **gesamte** 4.x-Linie | **0** — mit Epic `009` gefallen |
| `deprecations-ausnahmen.txt` | 1 aus `silex/silex` | `ReflectionParameter::getClass()`, deprecated seit PHP 8.0 | **0** — mit Epic `009` gefallen |
| `deprecations-ausnahmen.txt` | 3 aus eigenem Code | `null` an `strtolower()`, `explode()`, `method_exists()` | **0** — mit `000-000-0023` behoben |
| `phpstan.neon.dist` | — (später dazugekommen) | 31 Doctrine-Befunde über acht benannte Muster | **8 Muster**, Auflöser Epic `010` |

**Beide alten Listen sind leer, und das ist der Beleg für Regel 3:** Sie hätten sich nicht von
allein geleert. Jede Ausnahme, die nicht mehr greift, macht den Lauf rot — also musste sie beim
Auflösen mit entfernt werden, sonst wäre die Pipeline stehen geblieben.

Dazu `--abandoned=ignore` beim Audit: Von fünf abandoned Paketen des Ist-Stacks sind **zwei
übrig**, `doctrine/annotations` und `doctrine/cache`. `silex/silex`,
`knplabs/console-service-provider` und `symfony/debug` sind mit Epic `009` aus dem Baum. Das ist
kein Sicherheitsbefund, sondern die Beschreibung des Restbestands.
**Auf `fail` umstellen, sobald Epic `010` durch ist** — dann ist die Liste leer.

### Was die Gates heute melden

Gemessen am 2026-09-09, nach Epic `009`:

| | Stand |
|---|---|
| `composer audit --locked` | grün, **0 Meldungen, 0 ausgenommen**; 2 abandoned Pakete (beide Doctrine) |
| Deprecations auf PHP 8.3 | grün, **0 protokollierte Zeilen bei 0 Ausnahmen** |
| Deprecations auf PHP 8.4 | grün, **0 protokollierte Zeilen bei 0 Ausnahmen** |
| PHPStan | `[OK] No errors`, blockierend; 31 Doctrine-Befunde über 8 benannte Muster ausgenommen (Epic `010`) |
| Suite auf PHP 8.3 | `OK (267 tests, 640 assertions)` |
| Suite auf PHP 8.4 | `OK (267 tests, 639 assertions)`, 0 übersprungen, Postausgang 0 Byte |

**Der 8.4-Lauf beantwortet die Frage, die in der `.gitlab-ci.yml` offen stand.** Dort steht als
Begründung für `allow_failure: true`, der Symfony-7.4-Stand sei auf 8.4 noch nicht gemessen
worden. Er ist es jetzt — im Pipeline-Image `php:8.4-cli` gegen einen `mysql:8.0`-Service, mit
dem Wortlaut des Jobs —, und er ist grün. Den Schalter zu ziehen ist damit eine Entscheidung,
die anliegt; sie gehört in ein eigenes Ticket, nicht in eine Dokumentationsänderung.

## Die Suite ist die Abnahmegrundlage
Was ein roter Test beim Kernel-Tausch bedeutet, ist in `an_project/docs/technical.md`
festgelegt — einschliesslich der Liste dessen, was die Suite **nicht** abdeckt.
