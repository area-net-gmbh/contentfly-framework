<!-- PURPOSE: Der Migrationsleitfaden — der Weg vom alten Contentfly auf die neue Version, geordnet nach dem, was ein Projekt zu tun hat. Das Register der Einzelheiten ist breaking-changes.md. -->

# Migration auf die neue Framework-Version

**Dieser Leitfaden ist der Weg. `an_project/docs/breaking-changes.md` ist das Register.**

Das Register hat **116 Einträge in 14 Abschnitten** (Stand 2026-09-16) und ist nach Epic und
Story geordnet — also danach, *wann wir etwas geändert haben*. Das ist die richtige Ordnung zum
Nachschlagen und die falsche zum Arbeiten. Hier steht die andere: **was ein Projekt tut, und in
welcher Reihenfolge.**

---

## Woran dieser Leitfaden erprobt ist

**An der Vorlage und an einem echten Bestandsprojekt.** Die Vorlage `custom/` hat jede Phase einmal
durchlaufen (Epic `007`). Danach ist der Leitfaden an **UFP** gegangen worden (Story `007-005`): Contentfly
1.6.0 auf PHP 7.4, 65 Entities, 40 Controller, sechs eigene LoginManager mit OAuth 2.0 und
Community-Profil, eine eingecheckte Framework-Kopie mit 14 eigenen Patches.

**Das Ergebnis, gemessen:** Alle neun Phasen durchlaufen. Von 164 aufgezeichneten Aufrufen der App
antworten 124 identisch mit dem alten Backend, jede der 40 Abweichungen ist beabsichtigt und im Register
oder im Bericht von `007-005-0004` erklärt. Der Anmeldeweg samt SSO läuft, und die Anwendung ist über alle
Aufrufe rund 30 % schneller als vorher.

**Was das gekostet hat, steht jetzt hier:** Jede Hürde, die UFP gefunden hat, ist entweder im Framework
behoben (`000-000-0038` bis `0047`), in diesem Leitfaden als Schritt aufgenommen oder im Register
beschrieben. Die Stellen, an denen das passiert ist, tragen den Verweis auf `007-005`.

**Was UFP nicht geprüft hat**, weil es das nicht benutzt: eigene `Type`-Klassen, Plugins, Sprachen und
i18n, verschlüsselte Felder. Dort gilt der Leitfaden, wie er an der Vorlage erprobt ist.

---

## Bevor Sie anfangen: was Sie verlieren

**Die PIM-Oberfläche ist ersatzlos gestrichen** (Epic `012`). Wer sie heute benutzt, um Inhalte
zu pflegen, verliert sie mit diesem Update — es gibt keinen Nachfolger im Framework und keine
Übergangsfrist.

**Was an ihre Stelle tritt:**

- **Die API.** Alles, was die Oberfläche tat, tut sie über `/api` — lesen, schreiben, löschen,
  Dateien. Ein Projekt, das eine Pflegeoberfläche braucht, baut sie darauf.
- **Die Console.** Installation, Schema, Aufräumarbeiten, der Abgleich mit Fremdsystemen.

**Was nicht an ihre Stelle tritt:** eine fertige Oberfläche. Contentfly ist nach `012` reine
Datenhaltung plus Core-Funktionen.

**Das ist die Entscheidung, die vor allen anderen steht.** Wer sie nicht treffen will, migriert
nicht — der Rest dieses Leitfadens setzt sie voraus.

---

## Die Phasen

<!-- Ausgefüllt mit 007-004-0001; der Text je Phase kommt mit 007-004-0002. -->

Die Reihenfolge ist nicht Geschmack. Wo sie erzwungen ist, steht der Grund dabei.

| # | Phase | Warum hier |
|---|---|---|
| 1 | **Voraussetzungen und Sicherung** | Alles Folgende setzt PHP 8.3, `ext-sodium` und `ext-openssl` voraus. Und die Sicherung gehört vor den ersten Schritt, nicht vor den letzten. |
| 2 | **Bezugsweg: von der Kopie auf das Paket** | **Erzwungen.** Die Rector-Regel für Phase 3 kommt mit dem Paket. Wer die Entities zuerst migriert, hat das Werkzeug noch nicht. |
| 3 | **Entities migrieren** | Nach dem Paket, vor allem anderen: Ohne lesbare Metadaten startet nichts, was danach kommt. |
| 4 | **Datenbankschicht nachziehen** | **Teilweise erzwungen.** Der Metadaten-Cache muss *vor* dem ORM-Upgrade geleert werden — ein Cache im alten Format bringt den ersten Start zu Fall. |
| 5 | **Konfiguration nachziehen** | Vor dem ersten Start: Entfallene Felder melden sich erst beim Start, und dann mit einer Meldung, die von der Klasse handelt und nicht vom Feld. |
| 6 | **Code nachziehen** | Controller, Provider, Commands. Erst jetzt sinnvoll, weil die Anwendung bis hierhin nicht startet. |
| 7 | **Authentifizierung nachziehen** | Nach dem Code, weil sie an ihm hängt — und vor der API-Prüfung, weil jeder API-Aufruf sich ausweisen muss. |
| 8 | **Den API-Vertrag prüfen** | Was die Clients merken. Zuletzt im Code, weil erst hier eine laufende Anwendung antwortet. |
| 9 | **Daten migrieren** | **Erzwungen.** Der Re-Encrypt-Lauf braucht eine laufende Anwendung mit funktionierendem Schema. |

## Welcher Register-Abschnitt in welcher Phase

**Jeder der 14 Abschnitte gehört genau einer Phase.** Das ist die Zusicherung, die
`tests/…` hält: Kein Abschnitt fällt heraus.

**Ein Abschnitt gehört in die Phase, in der seine Handlung liegt** — nicht in jede, die er
berührt. *Feldverschlüsselung* etwa fordert `ext-sodium` schon in Phase 1; sein eigentlicher
Schritt ist der Re-Encrypt-Lauf, also steht er in Phase 9. Wo ein Abschnitt früher hineinragt,
sagt es der Text der Phase.

| Phase | Register-Abschnitt |
|---|---|
| 1 | *(keiner — Phase 1 ist Vorarbeit)* |
| 2 | Paketgrenze (Epic `007`) |
| 3 | Annotationen · Entity-Layer (Story `010-001`) |
| 4 | Doctrine ORM 3 (Story `010-003`) · Doctrine (Story `009-005`) |
| 5 | Konfiguration |
| 6 | Kernel (Epic `009`) |
| 7 | Authentifizierung (Story `013-001`) · Authentifizierung, Teil 2 (Story `013-002`) · Authentifizierung, Teil 3 (Story `013-003`) · Authentifizierung, Teil 4 — der LoginManager (Story `013-004`) · Authentifizierung, Teil 5 — LDAP und OIDC (Story `013-005`) |
| 8 | API |
| 9 | Feldverschlüsselung (Story `010-004`) |

### Die Granularität, und ihr Preis

**Zugeordnet wird abschnittsweise, nicht eintragsweise** — 14 Zuordnungen statt 100
Markierungen. Entschieden am 2026-09-11.

**Der Preis:** Ein neuer Eintrag in einem bereits zugeordneten Abschnitt gilt damit automatisch
als abgedeckt. Das ist vertretbar, solange das Register die Einzelheiten trägt und dieser
Leitfaden den Weg — aber es ist eine Entscheidung und keine Selbstverständlichkeit. Wer einen
Bruch aufnimmt, der in keine der neun Phasen passt, hat einen gefunden, den dieser Leitfaden
nicht führt: Dann gehört die Phasenliste erweitert und nicht der Eintrag hineingezwängt.

---

# Der Weg

<!-- Ausgefüllt mit 007-004-0002. -->

## Phase 1 — Voraussetzungen und Sicherung

**Zu tun:**

1. **PHP 8.3 oder neuer.** Die Zielplattform ist 8.5; die Suite läuft grün auf 8.3 und 8.4.
2. **`ext-sodium` und `ext-openssl`** müssen vorhanden sein. Beide stehen jetzt im `require` —
   ohne sie schlägt schon `composer install` fehl, und das ist Absicht: Die Feldverschlüsselung
   hängt daran.
3. **Eine Sicherung der Datenbank.** Sie ist der Rückweg für Phase 9, und sie gehört *vor* den
   ersten Schritt, nicht vor den letzten.
4. **Eine Bestandsaufnahme — mit dem Werkzeug, nicht aus dem Kopf:**

   ```sh
   php tools/migration/inventory.php <backend-verzeichnis-des-projekts>
   ```

   Es liest nur und meldet: Entities und ihre Annotationen, eigene Types, Plugins, LoginManager,
   Controller, die benutzten `$app[...]`-Schlüssel, Silex-Verweise, Traits mit Doctrine-Mapping und
   fehlenden Importen (Phase 3), Annotationen mit unausgeglichenen Klammern (Phase 3), die Einordnung der
   Konfigurationsschlüssel (Phase 5) und das DBAL-2-Statement-Muster (Phase 6).
5. **Die eigenen Patches an der Framework-Kopie, je mit Nachfolger.** Wer `lib/contentfly` im Repo
   hat, hat es oft auch geändert — das Inventar liest die Commits nach dem Import aus der Git-Historie.
   **Jeder Patch verschwindet in Phase 2 mit `lib/`, ohne Meldung.** Bei UFP waren es 14 Dateien,
   darunter eine Upload-Whitelist, eine feste CORS-Herkunft und eine **Rollensperre** für die generischen
   Routen `/api` und `/file`. Die ersten beiden hat das Framework übernommen (`000-000-0038`/`0039`); die
   Rollensperre musste das Projekt nachbauen — ohne sie konnte ein anonymes Token Dateien auflisten und
   löschen. Für jeden Patch vor Phase 2 festhalten: vom Framework abgedeckt, im Projekt nachzubauen, oder
   entfällt.
6. **Das Ist-Verhalten aufzeichnen,** solange das alte Backend läuft — es ist der Massstab für Phase 8:

   ```sh
   php tools/migration/record-api.php record szenario.json aufzeichnung-alt --vars=vars.json
   ```

   Ein Szenario sind die Aufrufe, die die eigenen Clients machen, mit Sitzungen je Rolle. Passwörter
   stehen in der `vars.json`, ausserhalb des Repos.

**Fertig, wenn:** `php -m` zeigt beide Erweiterungen, die Sicherung liegt, jeder Patch hat einen
Nachfolger, und eine Aufzeichnung des alten Backends ist zweimal hintereinander identisch.

## Phase 2 — Bezugsweg: von der Kopie auf das Paket

**Das Framework kommt ab jetzt über Composer.** Wer `lib/` im eigenen Repo liegen hat, bekommt
eine neue Version nur durch Hineinkopieren — und verliert dabei jede eigene Änderung.

**Zu tun:** Die fünf Schritte in `an_project/docs/breaking-changes.md`, Abschnitt *Paketgrenze
(Epic `007`)*, Unterabschnitt *Der Ablauf, Schritt für Schritt*. Sie sind mit `007-001-0005` an der
Vorlage gegangen worden und mit `007-005-0003` an UFP, einem Projekt mit eingecheckter Framework-Kopie.

**Drei Dinge, die dabei umfallen und die man einzeln merkt:**

- `ROOT_DIR` gibt es nicht mehr; der Einstiegspunkt setzt `CONTENTFLY_PROJECT_DIR`.
- Der Einstiegspunkt lädt den Autoloader und ruft `Start` — nicht mehr umgekehrt.
- Ein liegengebliebenes `custom/vendor/` **bricht den Start ab**. Das ist Absicht: still
  übergangen sähe es aus wie etwas, das benutzt wird.
- **`vendor/` gehört nicht mehr ins Repo.** Bei UFP waren `vendor/` und `custom/vendor/` versioniert.
  Beide aus Git nehmen und in die `.gitignore`; `composer install` stellt sie her. Eigene Abhängigkeiten
  aus `custom/composer.json` wandern ins Manifest des Projekts — ein abandoned Paket ist der Anlass, es
  zu ersetzen, aber Achtung auf Seiteneffekte: `fakerphp/faker` statt `fzaninotto/faker` erzeugt aus
  demselben Seed andere Namen.

**Fertig, wenn:** `composer install` läuft durch und `vendor/areanet/contentfly` existiert.

## Phase 3 — Entities migrieren

**Ein Lauf über `Entity/` bringt Annotationen auf Attribute und entfernt, was mit Epic `012`
weggefallen ist.**

**Zu tun:** Der Rector-Lauf aus `an_project/docs/pim-annotationen-migration.md`, Abschnitt 7.
Dort steht der Aufruf, was die Regel abdeckt, was von Hand bleibt — und dass sie **zweimal**
laufen muss.

**Die Warnung, die dort ausführlich steht, hier in einem Satz:** Wer nur einmal läuft, hat keinen
halb migrierten Baum, sondern einen kaputten.

**Drei Dinge vor dem Lauf, alle drei meldet das Inventar aus Phase 1** (Abschnitt 7, Punkte 4 bis 6):

- **Traits mit Mapping in den Pfad aufnehmen.** Rector über `Entity/` erreicht `Traits/` nicht; ORM 3
  sieht deren Spalten dann nicht, und Phase 4 plant, sie zu löschen.
- **Fehlende Importe ergänzen.** Ohne `use Doctrine\ORM\Mapping as ORM;` weicht Rector einer Datei
  still aus.
- **Unausgeglichene Klammern korrigieren.** Sonst liest Rector den Rest des Docblocks als Text, und
  Einstellungen gehen ohne Meldung verloren.

**Von Hand bleibt** unter anderem, drei entfallene `Type`-Klassen aus `APP_SYSTEM_TYPES` bzw.
`APP_CUSTOM_TYPES` zu streichen — das ist Phase 5, aber es fällt hier auf.

**Fertig, wenn:** ein Trockenlauf `Rector is done!` meldet.

## Phase 4 — Datenbankschicht nachziehen

**Zuerst, und das ist erzwungen: den Cache leeren** — `data/cache/metadata`, `data/cache/query` und
`data/cache/doctrine`. Ein Metadaten-Cache im alten Format bringt den ersten Start zu Fall, und die
Meldung handelt dann von Doctrine und nicht vom Cache. `data/cache/doctrine` war bis `000-000-0049` der
Ort der Proxy-Klassen; eine Proxy aus dem alten Betrieb dort kostet sonst jeden Aufruf
(`Interface "Doctrine\ORM\Proxy\Proxy" not found`).

**Zu tun:** die Einträge unter *Doctrine ORM 3 (Story `010-003`)* und *Doctrine (Story
`009-005`)*. Die beiden, die am häufigsten treffen:

- **Eine Entity darf ein geerbtes Feld nicht wortgleich wiederholen.** ORM 3 lehnt das ab, wo
  ORM 2 es hinnahm. **Weicht die Wiederholung absichtlich ab** — bei UFP 16 Entities mit
  `userCreated` und `onDelete: 'CASCADE'` —, dann nicht streichen, sondern
  `#[ORM\AssociationOverrides]`; sonst ändert sich das Löschverhalten still.
- **Eigene DQL-Funktionen** müssen `getSql(): string` deklarieren.

**Vor dem Schema-Update die Dateien umziehen,** falls das Projekt von Contentfly 1.6 kommt: Dort lagen
sie unter `data/files/JJJJ/MM/<id>/`, und das Update löscht die Spalte `pim_file.path`, die das festhält.

```sh
php bin/console.php appcms:files:relocate --dry-run
php bin/console.php appcms:files:relocate
```

Meldet der Command, es gebe keine Spalte `path`, war nichts umzuziehen. Details im Register unter
*Entity-Layer*.

**Das Schema-Update erst lesen, dann anwenden — das ist Pflicht, kein Rat:**

```sh
php bin/console.php orm:schema-tool:update --dump-sql   # jede DROP-Zeile erklären können
php bin/console.php orm:schema-tool:update --force      # erst danach
```

Bei UFP hat das Lesen Datenverlust verhindert: Geplant waren `DROP` für die Spalten der nicht migrierten
Traits (Phase 3) und für `pim_file.path` — ohne diese Spalte sind die Dateien aus 1.x unter
`data/files/JJJJ/MM/<id>/` nicht mehr erreichbar. Ein Projekt, das die
Tabelle `pim_navItem` umbenannt hat — oder deren Dump von einem Server mit anderem
`lower_case_table_names` stammt —, sieht dort `DROP` und `CREATE`; der Register-Eintrag unter *Doctrine
ORM 3* nennt das `RENAME TABLE` davor. **Jede `DROP`-Zeile
ist entweder erklärt oder ein Grund, anzuhalten.**

**Fertig, wenn:** `orm:validate-schema` für Mapping und Datenbank `[OK]` meldet und keine `DROP`-Zeile
unerklärt angewendet wurde.

## Phase 5 — Konfiguration nachziehen

**Vor dem ersten Start.** Entfallene Felder melden sich sonst beim Start — mit einer Meldung, die
von der Klasse handelt und nicht vom Feld.

**Die Reihenfolge der Phasen 4 bis 6 hat eine Ausnahme:** Der Bootstrap lädt `custom/app.php` bei
**jedem** Start, auch auf der Konsole. Was `app.php` lädt — ein eigener Controller-Provider, eine
Factory mit Silex-Typ —, muss deshalb schon hier portiert sein, sonst erreicht weder Phase 4 noch diese
Phase ihr „Fertig, wenn“. Bei UFP betraf das den `SecureControllerProvider` und die `mailer`-Factory.
Das Inventar nennt die Dateien unter *Silex/Pimple references*.

**Zu tun:** die Einträge unter *Konfiguration*. Dazu die drei `Type`-Klassen aus Phase 3.

**Die Schlüssel mit dem Inventar einordnen, nicht mit dem Register allein.** Es teilt jeden Schlüssel
aus `custom/config.php` in vier Gruppen: von Contentfly 2 deklariert, aus dem Framework entfernt,
**Projekt-Patch** (in der alten Kopie erst nach dem Import hinzugekommen — sein Verhalten ist mit `lib/`
weg) und eigene Schlüssel des Projekts. Die dritte Gruppe übersieht, wer nur das Register liest; bei UFP
waren es vier (`APP_ROLES_STAFF`, `APP_ROLES_PARTICIPANT`, `APP_PARTICIPANT_DELETE_ENTITIES`,
`FILE_MAX_UPLOAD_SIZE`) — den letzten kennt Contentfly 2 seit `000-000-0042` selbst. **Eigene Schlüssel sind erlaubt** (`000-000-0040`, UFP hatte 35); die Vorlage rät
zu einem Präfix.

**Die `FRONTEND_*`-Felder stehen an zwei Stellen, und das ist keine Doppelung:** Das Register
führt unter *Konfiguration* den Eintrag *Acht `FRONTEND_*`-Felder entfallen*;
`pim-annotationen-migration.md`, Abschnitt 6, nennt **zwei weitere**
(`FRONTEND_SHOW_ID_IN_LIST`, `FRONTEND_SHOW_OWNER_IN_LIST`) samt der beiden daraus abgeleiteten
Konstanten. Wer nur eine der beiden Listen abarbeitet, lässt Zeilen stehen.

**Neu hinzu kommt `SECURITY_JWT_SECRET`,** sobald das Projekt JWT ausstellen will — mindestens
32 Byte, sonst weist die Bibliothek den Schlüssel ab.

**Fertig, wenn:** die Anwendung startet und `/api/config` antwortet.

## Phase 6 — Code nachziehen

**Controller, Provider, Commands.** Erst jetzt sinnvoll: Bis hierhin startet die Anwendung nicht.

**Zu tun:** die Einträge unter *Kernel (Epic `009`)*. Der Abschnitt beginnt mit einer Liste
dessen, was sich **nicht** ändert — die zuerst lesen, sie ist die kürzere Arbeit.

**Was ein Projekt am ehesten trifft:**

- `$app` ist kein Action-Argument mehr.
- `$app['request']` und `$app['controllers_factory']` sind entfallen.
- Ein eigener Controller-Provider hat eine andere Schnittstelle und ruft `connect()` selbst.

**Was ein Projekt mit Roh-SQL trifft — bei UFP der grösste Block dieser Phase:**

- **Das DBAL-2-Statement-Muster** (`execute()`, dann `fetchAll()`/`fetch()` auf dem Statement) gibt es
  nicht mehr. Umbauen mit `php tools/migration/dbal3-statements.php custom/ bin/` — erst ohne, dann
  mit `--write`. Bei UFP 362 Stellen in 39 Dateien, keine von Hand; Rectors DBAL-Satz baut dieses Muster
  falsch um.
- **`$app['twig']`** gibt es nicht mehr. Wer Twig selbst benutzt — etwa für Mail-Vorlagen —, nimmt
  `twig/twig` auf und registriert es in `app.php` (Register, Abschnitt *Kernel*).
- **Zahlen aus Roh-SQL bleiben Strings,** wie unter PHP 7.4 (`000-000-0044`). Kein Handlungsbedarf —
  ausser für eine eigene Verbindung, siehe Register.

**Was läuft, aber mit Symfony 8 bricht:** `Request::get()` ist seit Symfony 7.4 deprecated (UFP: 391
Aufrufe). Das Inventar zählt die Stellen. Nicht Teil dieser Migration, aber der nächsten.

**Was PHP 8 still ändert:** Seit PHP 8.0 sortiert `usort()` stabil. Gleichrangige Einträge einer
sortierten Liste können danach in anderer Reihenfolge stehen — dieselben Elemente, andere Anordnung.

**Belegen, nicht vermuten:** Rechnet das Projekt etwas aus (Scores, Statistiken), dieselbe Rechnung auf
dem alten und dem neuen Backend laufen lassen und vergleichen. Bei UFP waren die Scorecard-Snapshots
byte-gleich.

**Was sich ausdrücklich nicht ändert:** der Zugriff `$app['orm.em']`. Er ist dauerhaft
zugesichert; welche Schlüssel dazugehören, steht in `an_project/docs/dev-guide.md` unter *Die
zugesicherten `$app[...]`-Schlüssel*. **Ein Projekt muss seine Controller deswegen nicht
anfassen.**

**Fertig, wenn:** die eigenen Routen antworten und die eigenen Commands laufen.

## Phase 7 — Authentifizierung nachziehen

**Der grösste zusammenhängende Block** — fünf Register-Abschnitte, Stories `013-001` bis
`013-005`.

**Zu tun, in dieser Reihenfolge:**

1. **`APP_MASTER_PASSWORD` entfernen.** Es gibt den Schalter nicht mehr, und ein Projekt, das
   ihn setzt, bekommt keine Warnung — nur keinen Zugang mehr darüber.
2. **Mit dem Ende aller Sitzungen rechnen.** Bestehende Token verfallen mit dem Update.
3. **Hinter einem Proxy `APP_TRUSTED_PROXIES` setzen.** Sonst trifft `LoginThrottle` den
   Proxy statt den Angreifer.
4. **Einen eigenen `LoginManager` auf den Provider-Vertrag umstellen** — die Klasse gibt es
   nicht mehr. Das Register beschreibt die Schritte; an UFP erprobt heisst das:
   - **Prüfen im Provider, alles andere im Listener.** Was ein Manager über die Prüfung hinaus tat —
     Felder am Benutzer, `tempData` für den Client —, gehört in einen Listener auf
     `pim.auth.after.login`, registriert mit `$app->on()`. Werte, die nur der Provider kennt, reisen in
     `ExternalIdentity::$attributes` mit.
   - **Unter dem bisherigen Kurznamen registrieren,** dann bleiben die Clients unverändert.
   - **Die Konten umstellen** — das SQL im Register, und für lokale Passwortkonten, die ein Manager
     markiert hat, dessen Ergänzung.
5. **Projektcode, der Tokens selbst nachschlägt, umstellen.** `pim_token.token` enthält jetzt einen
   Hash; nachschlagen über `Token::hash()`, das Token im Klartext steht nur in der Login-Antwort.
6. **Anonyme Provider brauchen eine Rollensperre.** Ein Provider, der ohne Anmeldedaten Tokens ausgibt
   (Umfrage-Teilnahme, Share-Links), macht „hat ein Token“ bedeutungslos. Die generischen Routen `/api`
   und `/file` prüfen nur Gruppenrechte — hat die Gruppe dort Rechte, reicht das anonyme Token. Eine
   Sperre im Projekt (ein Listener auf `kernel.controller` nach der Routen-Authentifizierung) schliesst
   das; UFP hatte sie als Patch in `lib/` (Phase 1, Punkt 5).

**Neu, aber optional:** JWT (`tokenType`), der Refresh-Endpunkt, LDAP und OIDC. Nichts davon ist
nötig, um wie bisher weiterzuarbeiten.

**Fertig, wenn:** Anmeldung und Abmeldung laufen und ein geschützter Endpunkt antwortet.

## Phase 8 — Den API-Vertrag prüfen

**Was die Clients merken.** 24 Einträge unter *API* — der grösste Abschnitt des Registers, und
der einzige, den ein Projekt nicht allein durch Codeänderungen erledigt: Ein Teil davon betrifft
Clients, die es nicht besitzt.

**Vergleichen statt lesen.** Die Aufzeichnung aus Phase 1 gegen das neue Backend wiederholen und
vergleichen:

```sh
php tools/migration/record-api.php record szenario.json aufzeichnung-neu --vars=vars.json --base-url=<neu>
php tools/migration/record-api.php compare aufzeichnung-alt aufzeichnung-neu
```

**Jede Abweichung einordnen:** beabsichtigt (mit Register-Eintrag), Befund, oder Daten, die sich zwischen
den Läufen geändert haben. Bei UFP 124 von 164 identisch; die 40 übrigen waren Aliase aus der
Kontenumstellung, Formänderungen aus dem Register, beendete Sitzungen und ein Dateipfad.

**Ohne `APP_DEBUG` vergleichen.** Contentfly 2 zeigt im Debug-Modus `E_ALL`, 1.x blendete Notices aus —
und PHP 8 hat „Undefined index“ zur Warning gemacht. Altcode schreibt dann Warnungen **vor** das JSON: bei
UFP 47 von 164 Antworten. Das ist ein Hinweis auf alten Code, aber kein Bruch des Vertrags; verglichen
wird, was Produktion ausliefert.

**Zwischen zwei Läufen die Login-Bremse bedenken.** Fehlanmeldungen aus Test-Skripten zählen pro Adresse;
eine Aufzeichnung direkt danach bekommt `429`, und `record-api.php` meldet die Sitzung als gedrosselt.

**Die Statuscodes, die sich geändert haben, zuerst:** 404 bei unbekannter Id, 405 statt 302 auf
unbekannten Pfaden, 409 bei einer `unique`-Verletzung, 429 bei zu vielen Anmeldeversuchen. Sie
stehen ab jetzt **nur noch** in der HTTP-Antwort — `status` ist aus dem Fehlerrumpf verschwunden.

**Und die Formänderungen:** Der `frontend`-Block schrumpft, in `/api/config` fällt er ganz weg;
`export` und `extended` verschwinden aus dem `permissions`-Block.

**Der Envelope: eine Form statt sieben.** Jede Erfolgsantwort unter `/api/*` besteht aus `data`,
`errors` und `meta` — der grösste Einzelposten dieser Phase. Der Register-Eintrag *Jede
Erfolgsantwort unter `/api/*` hat dieselbe Form* führt jeden Endpunkt einzeln auf und sagt, was
davon wirklich umzubauen ist: bei zehn Endpunkten bleibt `body.data` dasselbe wie vorher, nur die
Zusatzschlüssel wandern nach `meta`. Umzubauen sind `insert`, `delete`, `update`, `config` und
`schema`.

**Ein Client, der nichts davon tut, merkt das an `insert`.** Dort ist `body.id` weg; die Id steht
im Objekt. Das ist die Stelle, an der ein nicht angepasster Client still das Falsche tut, statt
einen Fehler zu bekommen — also die erste, die zu prüfen ist.

**Die Fehlerantworten tragen dieselbe Hülle.** `data: null`, `errors` als Liste, `meta` wie beim
Erfolg — ein Client wertet Erfolg und Fehler mit demselben Leser aus und schaut danach auf
`errors`. Die Zuordnung Feld für Feld steht im Register-Eintrag *Jede Fehlerantwort hat dieselbe
Form wie eine Erfolgsantwort*; die kürzeste Fassung:
`message` → `errors[0].code` (verzweigen) bzw. `errors[0].detail` (anzeigen), `message_value` →
`errors[0].context.value`, `debug` → `meta.debug`, `status` ersatzlos.

**`/auth/*`, `/file/*` und `/system/do`** ziehen im selben Release nach.

**Fertig, wenn:** die eigenen Clients gegen die neue Instanz laufen.

## Phase 9 — Daten migrieren

**Nur, wenn das Projekt verschlüsselte Felder hat** (`@PIM\Config(encoded: true)`). Sonst
entfällt diese Phase, und der Lauf sagt es wörtlich:

```
No field with encoded: true — there is nothing to re-encrypt.
```

**Zu tun:** der Ablauf in `an_project/docs/deployment.md` — Sicherung, Trockenlauf, echter Lauf,
zweiter Trockenlauf als Prüfung.

**Warum es zuletzt steht:** Der Lauf braucht eine laufende Anwendung mit funktionierendem
Schema.

**Ein abgebrochener Lauf ist kein Schaden.** Jeder Stapel ist eine Transaktion, beide Formate
bleiben lesbar, und ein Neustart macht dort weiter, wo er aufhörte.

**Fertig, wenn:** ein zweiter Trockenlauf `0 re-encrypted` meldet — oder, ohne verschlüsselte
Felder, die Zeile oben. (Bis `007-005-0005` zitierte dieser Leitfaden beide Meldungen auf Deutsch; der
Lauf antwortet seit Epic `014` englisch.)

---

## Wenn etwas fehlt

**Dieser Leitfaden führt den Weg; die Einzelheiten stehen im Register.** Wer einen Bruch sucht,
den hier niemand erwähnt, findet ihn in `an_project/docs/breaking-changes.md` — die Zuordnung
oben sagt, in welcher Phase sein Abschnitt abgearbeitet wird.

**Wer einen findet, der in keine der neun Phasen passt,** hat einen gefunden, den dieser
Leitfaden nicht führt. Dann gehört die Phasenliste erweitert und nicht der Eintrag
hineingezwängt.
