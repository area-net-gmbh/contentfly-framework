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

## Wo das Repository liegt

**Seit `011-002-0001` (2026-09-16) auf GitHub:**
`git@github.com:area-net-gmbh/contentfly-framework.git`, privat im Company-Account. Die
Begründung steht in `architecture.md` unter *Key decisions* — kurz: Ein Zugang für eine externe
Security-Prüfung lässt sich dort vergeben und wieder entziehen, auf einem internen GitLab nicht.

Das alte Remote `gitlab.in.area-net.de` ist in diesem Klon unter dem Namen `gitlab` erhalten und
wird abgeschaltet, sobald die Pipeline auf GitHub einmal grün gelaufen ist. Bis dahin ist es die
einzige Stelle, an der die Prüfungen nachweislich laufen.

## Die Pipeline

**Angelegt am 2026-09-08 mit Story `008-005`** als GitLab CI — damals, weil der Remote GitLab war.
**Sie zieht mit `011-002-0002` auf GitHub Actions um;** bis dahin beschreibt dieser Abschnitt den
Stand, der läuft, und nicht den, der kommt.

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

Seit `000-000-0029` gilt das auch für den Composer-Bootstrap: Er stand dreimal wörtlich gleich
in der YAML und steht jetzt in `tools/ci/install-composer.sh`.

### Ein Schritt, der scheitert, sagt woran
**Eingeführt mit `000-000-0029`.** Jeder Schritt, der seine Ausgabe wegschiebt, läuft über
`schritt` aus `tools/ci/schritt.sh`:

```sh
. "$(dirname "$0")/schritt.sh"
schritt "gd konfigurieren" docker-php-ext-configure gd --with-freetype
```

Solange es gutgeht, steht eine Zeile im Log. Scheitert der Befehl, kommen der Name des
Schritts, der Exit-Code und die **letzten 40 Zeilen** seiner Ausgabe dazu
(`CONTENTFLY_CI_LOGZEILEN` verstellt die Zahl), und das Skript endet mit demselben Code.

**Der Anlass** war `013-005-0004`: Ein Job brach mit Exit 2 und null Zeilen Ausgabe ab.
`composer install` verlangte `ext-ldap`, sagte das auch — nur schrieb der Schritt nach
`/tmp/i.log`, und `set -eu` beendete das Skript, bevor jemand die Datei ausgeben konnte.

**Die Umleitung bleibt.** Eine Pipeline, die jeden `apt-get`-Fortschritt ausgibt, liest
niemand — ein volles Log verdeckt den Fehler so zuverlässig wie ein leeres.

**Was still sein darf, steht in einem Test, nicht in einem Kommentar:**
`tests/Unit/Ci/CiStepsTest.php` fährt einen absichtlich scheiternden Schritt und prüft, dass
dessen Meldung zu sehen ist; danach prüft er, dass kein Schritt in `tools/ci/` an der Funktion
vorbei schweigt. Die Ausnahmen stehen dort mit Begründung — und eine Ausnahme, die nichts mehr
trifft, macht den Lauf rot. Dieselbe Regel wie bei den Gates aus `006-005`.

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
| Proxies | `data/cache/proxies/<Kennung>` | Doctrines Proxy-Klassen, je Version von `areanet/contentfly` und `doctrine/orm` (`000-000-0049`); der frühere Ort `data/cache/doctrine` darf gelöscht werden |

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

## Verschlüsselte Felder umschlüsseln

Betrifft nur Projekte, die Felder mit `#[PIM\Config(encoded: true)]` haben. **Das Framework
selbst hat keines** — ein Lauf hier meldet folgerichtig, dass es nichts zu tun gibt.

Seit `010-004-0002` schreibt das Framework XChaCha20-Poly1305 und liest beide Formate. Ein
Bestandswert bleibt also lesbar und wird beim nächsten Schreiben nebenbei umgestellt. Wer nicht
warten will, bis jeder Datensatz einmal angefasst wurde, lässt den Befehl laufen.

**Der Ablauf, in dieser Reihenfolge:**

1. **Sicherung der Datenbank anlegen.** Das ist der Rückweg, und es ist der einzige — ein
   umgeschlüsselter Wert lässt sich nicht zurückrechnen, ohne den alten Schlüssel erneut
   anzuwenden.
2. **Trockenlauf:** `php bin/console.php appcms:security:reencrypt --dry-run`. Er zählt je Feld,
   was er täte, und fasst nichts an.
3. **Echter Lauf:** derselbe Befehl ohne `--dry-run`. `--batch` setzt die Stapelgrösse, Vorgabe
   500 Zeilen.
4. **Zweiter Trockenlauf** als Prüfung: Er muss `0 re-encrypted` melden.

**Ein abgebrochener Lauf ist kein Schaden.** Jeder Stapel ist eine Transaktion, beide Formate
bleiben lesbar, und der Befehl überspringt, was schon umgestellt ist — ein erneuter Start macht
dort weiter, wo er aufgehört hat.

**Wofür die Sicherung wirklich da ist:** für den Fall, dass jemand mit dem falschen
`SECURITY_CIPHER_KEY` gelaufen ist. Dann sind die Werte nicht kaputt, aber mit einem Schlüssel
verschlüsselt, den niemand wollte. Der Befehl hält an, sobald sich ein Wert nicht entschlüsseln
lässt, und nennt genau diesen Verdacht.

## JWT: was ein Token trägt und wie ein Schlüssel gewechselt wird

Betrifft Projekte, die mit `tokenType: "jwt"` anmelden. **Ohne diese Angabe gibt der Login
weiterhin ein opaques Token aus, und nichts davon ist nötig.**

### Die Umgebungsvariablen

| Variable | nötig | Wert |
|---|---|---|
| `SECURITY_JWT_SECRET` | ja, sobald JWT ausgestellt werden | mindestens 32 Byte |
| `SECURITY_JWT_KEY_ID` | nein, Vorgabe `k1` | ein Name, kein Geheimnis |
| `SECURITY_JWT_SECRET_PREVIOUS` | nur während eines Wechsels | der bisherige Schlüssel |
| `SECURITY_JWT_KEY_ID_PREVIOUS` | nur während eines Wechsels | dessen Name |

Dazu `SECURITY_JWT_TTL` in der Konfiguration — die Lebensdauer eines Access-JWT in Sekunden,
Vorgabe 900.

**Kein Standardwert für das Geheimnis, mit Absicht.** Ein im Repository hinterlegter Schlüssel
ist kein Schlüssel: Jede Installation, die vergisst ihn zu setzen, signierte dann mit einem
öffentlich bekannten Wert — und niemand merkt es, weil alles funktioniert. Ohne Wert verweigert
der Login die Ausstellung und der Prüfzweig jeden Token.

**Mindestens 32 Byte:** `firebase/php-jwt` weist für HS256 kürzere Schlüssel ab, schon beim
Signieren. Ein brauchbarer Wert entsteht mit

```sh
php -r "echo bin2hex(random_bytes(32));"
```

### Was im Token steht

Fünf Claims, und die Liste steht in `Classes/Security/JwtAccessToken::CLAIMS`:

| Claim | trägt |
|---|---|
| `sub` | die Kennung des Benutzers |
| `iss` | den Ausgeber (`contentfly`) |
| `iat` | den Ausstellungszeitpunkt |
| `exp` | den Ablauf |
| `jti` | die Kennung dieses einen Tokens, für den Widerruf |

**Was bewusst nicht drinsteht: Rollen, Gruppen, Berechtigungen.** Sie können sich ändern,
während der Token gilt; stünden sie darin, wirkte eine Rechteänderung erst nach dessen Ablauf.
Der Benutzer wird deshalb bei jedem Request aus `pim_user` geladen — eine Abfrage, für die der
Schreibzugriff auf `pim_token` entfällt.

Ein Token ist **nicht** vertraulich in dem Sinn, dass sein Inhalt geheim wäre: Die Nutzlast ist
base64, nicht verschlüsselt. Wer sie liest, sieht Kennung und Zeiten. Geschützt ist die
**Unveränderbarkeit**, nicht der Inhalt.

### Einen Schlüssel wechseln

Der Grund, warum es überhaupt geht: Ein Signaturgeheimnis, dessen Wechsel alle Sitzungen
beendet, wird nicht gewechselt — und damit ist ein Leak dauerhaft.

1. **Neuen Schlüssel erzeugen** (Befehl oben).
2. **Umhängen, in einem Schritt:** bisheriges `SECURITY_JWT_SECRET` nach
   `SECURITY_JWT_SECRET_PREVIOUS`, bisheriges `SECURITY_JWT_KEY_ID` nach
   `SECURITY_JWT_KEY_ID_PREVIOUS`, den neuen Wert nach `SECURITY_JWT_SECRET` und eine **neue**
   Kennung nach `SECURITY_JWT_KEY_ID`.
3. **Anwendung neu laden.** Ab jetzt wird mit dem neuen signiert, angenommen werden beide.
   Niemand muss sich neu anmelden.
4. **`SECURITY_JWT_TTL` abwarten** — bei der Vorgabe 15 Minuten. Dann ist das längste noch mit
   dem alten Schlüssel ausgestellte Access-JWT abgelaufen.
5. **Die beiden `*_PREVIOUS`-Felder leeren** und noch einmal neu laden.

**Die beiden Kennungen müssen sich unterscheiden.** Die Anwendung weist zwei gleiche mit einer
Meldung ab, statt stillschweigend nur einen der beiden Schlüssel zu akzeptieren — mitten in
einem Wechsel wäre das der schlechteste Zeitpunkt für eine stille Überraschung. Dieselbe Meldung
kommt, wenn ein vorheriger Schlüssel ohne Kennung dasteht.

### Abmelden und Widerrufen

`GET /auth/logout` setzt die `jti` des vorgezeigten Access-JWT bis zu dessen `exp` auf die
Sperrliste (`pim_revoked_token`) und löscht das **mitgeschickte** Refresh-Token. Der Client
schickt es als Parameter `refreshToken` mit; ohne ihn wird nur das Access-JWT gesperrt, und die
Refresh-Zeile verfällt über ihr eigenes Zeitlimit.

**Die Sperrliste braucht keine Pflege ausser dem Aufräumlauf.** Ein Eintrag ist gegenstandslos,
sobald das Token ohnehin abgelaufen wäre; `php bin/console.php appcms:token:cleanup` räumt ihn
mit den abgelaufenen Anmeldetoken weg — derselbe Befehl, kein zweiter.

**Eine Benutzersperrung braucht die Liste nicht.** Sie wirkt sofort, weil der Benutzer bei jedem
Request geladen wird und ein inaktiver abgewiesen wird.

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

**Der Audit-Schalter steht seit `010-003-0003` auf `--abandoned=fail`.** Von fünf abandoned
Paketen ist keines übrig:

| Paket | gefallen mit |
|---|---|
| `silex/silex`, `knplabs/console-service-provider`, `symfony/debug` | Epic `009` |
| `doctrine/annotations` | `010-001-0005` |
| `doctrine/cache` | `010-003-0002`, mit dem Sprung auf ORM 3 |

Damit ist ein abandoned Paket wieder eine Aussage statt einer Beschreibung des Altbestands.
Beide Richtungen sind im Pipeline-Image gemessen: Ist-Stand Exit 0, ein künstlich abandoned
Paket im Lock Exit 1 mit Nennung des Namens.

### Was die Gates heute melden

Gemessen am 2026-09-10, nach Epic `010`:

| | Stand |
|---|---|
| `composer audit --locked` | grün, **0 Meldungen, 0 ausgenommen, 0 abandoned**; Schalter auf `fail` |
| Deprecations auf PHP 8.3 | grün, **0 protokollierte Zeilen bei 0 Ausnahmen** |
| Deprecations auf PHP 8.4 | grün, **0 protokollierte Zeilen bei 0 Ausnahmen** |
| PHPStan | `[OK] No errors`, blockierend; **eine** Ausnahme übrig, und die kommt aus DBAL |
| Suite auf PHP 8.3 | `OK (282 tests, 692 assertions)` |
| Suite auf PHP 8.4 | `OK (282 tests, 692 assertions)`, 0 übersprungen, Postausgang 0 Byte |
| `orm:validate-schema` | Datenbank **in sync**; ein Mapping-Fehler übrig (`000-000-0025`) |

**Die Pipeline hat kein `allow_failure` mehr.** Der PHP-8.4-Job ist mit `010-003-0003`
blockierend geworden — die `.gitlab-ci.yml` hatte die Bedingung selbst benannt („Ob der Job
blockierend werden kann, entscheidet ein Lauf auf 8.4"), und der Lauf liegt inzwischen dreimal
vor: mit Symfony 7.4 und ORM 2.20, mit ORM 3.7, und auf dem Endstand von Epic `010`.

## Die Suite ist die Abnahmegrundlage
Was ein roter Test beim Kernel-Tausch bedeutet, ist in `an_project/docs/technical.md`
festgelegt — einschliesslich der Liste dessen, was die Suite **nicht** abdeckt.
