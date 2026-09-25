<!-- PURPOSE: Sammelstelle für Änderungen, die ein Bestandsprojekt beim Umstieg anfassen muss. Grundlage für den Migrationsleitfaden aus Epic 007. -->

# Breaking Changes für Bestandsprojekte

Was ein Projekt beim Umstieg auf die neue Framework-Version anfassen muss. **Diese Datei wird
laufend ergänzt**, sobald eine Änderung Bestandsprojekte trifft — nicht erst am Ende.

Epic `007` baut daraus den Migrationsleitfaden. Bis dahin ist das hier die Quelle: Jede Zeile
ist im Moment ihres Entstehens geschrieben, wo der Zusammenhang noch bekannt ist.

**Was hier hineingehört:** eine Änderung, die ein Projekt bemerkt, das die Vorlage *nicht*
unverändert übernommen hat. **Was nicht:** Änderungen innerhalb von `lib/`, die nach aussen
nichts ändern.

---

## Konfiguration

### `FILE_MAX_UPLOAD_SIZE` ist neu — und ein Upload über PHPs Grenze antwortet `413`
**Seit `000-000-0042` (2026-09-15).**

**Die Grenze der Anwendung für Uploads, in Bytes, optional.** Ist sie gesetzt, weist
`Classes\File\UploadValidator` eine grössere Datei mit `413` und `contentfly_file_too_large` ab, bevor
etwas geschrieben wird; `message_value` nennt die Grenze. Ohne Wert gilt wie bisher nur PHPs
`upload_max_filesize` — eine Einstellung des Servers, nicht der Anwendung. Die Vorlage liest den Wert aus
`APP_FILE_MAX_UPLOAD_SIZE`.

**Bestandsprojekte kennen den Schlüssel** — als Patch an ihrer Framework-Kopie. Das Bestandsprojekt UFP
(`007-005-0003`) setzte `FILE_MAX_UPLOAD_SIZE = 20971520` mit derselben Meldung und demselben Status; mit
`lib/` war die Prüfung weg, und der Schlüssel wurde nirgends mehr gelesen.

**Eine Änderung am Draht dazu:** Scheitert ein Upload schon an `upload_max_filesize`, antwortete das
Framework `400 contentfly_general_missing_params` — PHP legt dann keine Datei an. Jetzt `413
contentfly_file_too_large` mit PHPs Grenze als `message_value`.

*Was zu tun ist:* Wer den Schlüssel aus einem Patch hatte, lässt ihn stehen — er wirkt wieder. PHPs
`upload_max_filesize` und `post_max_size` müssen mindestens so gross sein, sonst greift die Grenze des
Servers zuerst. Ein Client, der auf `400` bei zu grossen Dateien geprüft hat, prüft auf `413`.

### `APP_AUTOGENERATE_PROXIES`: Proxies werden nur noch bei Bedarf geschrieben
**Seit `000-000-0047` (2026-09-15).**

Der Schalter wurde bisher auf `bool` reduziert, und seine Vorgabe `true` hiess für Doctrine
`AUTOGENERATE_ALWAYS`: **Jeder Request schrieb die Proxy-Klassen neu.** Gemessen am Bestandsprojekt UFP
(`007-005-0004`): zehn Listen- und Auswertungsaufrufe 540 ms statt 382 ms unter Contentfly 1.x, ohne
die Neuerzeugung 330 ms. Parallele Requests schrieben dieselbe Datei und scheiterten gelegentlich daran.

Die Vorgabe ist jetzt `ProxyFactory::AUTOGENERATE_FILE_NOT_EXISTS_OR_CHANGED` — geschrieben wird, wenn
ein Proxy fehlt oder seine Entity-Datei sich geändert hat. Der Schalter nimmt Doctrines Konstanten an;
`true` bleibt „bei jedem Request“, `false` bleibt „nie“. Jeder andere Wert bricht den Start mit einer
Meldung ab (`Classes\ORM\ProxyGeneration`).

*Was zu tun ist:* Nichts, wenn das Projekt den Schalter nicht setzt. Wer `true` gesetzt hat, streicht die
Zeile. Wer `false` setzt, erzeugt die Proxies beim Deployment mit `orm:generate-proxies`.

### `USE_SCSS_COMPILER` und `BASE_SCSS_FILE` entfallen
**Seit `006-001-0001` (2026-09-08).**

Das Framework hat einen SCSS-Compiler im Bootstrap mitgebracht: 54 Zeilen, die aus
`custom/Frontend/scss/` ein CSS samt Source-Map bauten. Er ist ersatzlos entfernt, zusammen
mit beiden Konfigurationsfeldern.

**Betroffen ist ein Projekt, das `USE_SCSS_COMPILER = true` gesetzt hat.** Nach dem Umstieg
wird kein CSS mehr gebaut; die zuletzt erzeugten Dateien unter `custom/Frontend/css/` bleiben
liegen, werden aber nicht mehr aktualisiert.

*Warum:* Der Compiler diente der PIM-Oberfläche, die Epic `012` entfernt hat.
`an_project/docs/tech-stack.md` hält seither fest: „Kein Frontend. Contentfly ist reine
Datenhaltung plus Core-Funktionen." Der Standardwert war `false`, und die ausgelieferte
Vorlage hat kein `custom/Frontend/` — für ein Projekt, das die Vorlage unverändert übernommen
hat, ändert sich nichts.

*Was zu tun ist:* Die SCSS-Kompilierung in den eigenen Build ziehen — `dart-sass` oder ein
`npm`-Skript leisten dasselbe und sind nicht an den Anwendungs-Bootstrap gekoppelt. Die
beiden Konfigurationsfelder sind aus der Projektkonfiguration zu entfernen; sie werden nicht
mehr gelesen und stehen sonst als wirkungslose Schalter herum.

---

### Acht `FRONTEND_*`-Felder entfallen
**Seit `000-000-0010` (2026-09-09).**

`FRONTEND_UI`, `FRONTEND_URL`, `FRONTEND_CUSTOM_LOGIN_BG`, `FRONTEND_TITLE`,
`FRONTEND_WELCOME`, `FRONTEND_LOGIN_REDIRECT`, `FRONTEND_FORM_IMAGE_SQUARE_PREVIEW` und
`FRONTEND_CUSTOM_LOGO`. Die vollständige Aufstellung mit Begründung je Feld steht in
`an_project/docs/pim-annotationen-migration.md`, Abschnitt 6.

**`FRONTEND_ITEMS_PER_PAGE` und `FRONTEND_CUSTOM_NAVIGATION` bleiben.** Sie tragen `FRONTEND_`
im Namen, steuern aber API-Verhalten beziehungsweise einen datengetriebenen Zweig — die
Benennung ist ein Erbe, kein Hinweis auf ihren Zweck. **`FRONTEND_CUSTOM_NAVIGATION` entfällt seit
`000-000-0077` doch**, mit den Entities, die er ausgelesen hat — siehe *API*.

*Was zu tun ist:* Die acht Zeilen aus `custom/config.php` entfernen; sie werden nicht mehr
gelesen.

### CORS erlaubt nur noch eingetragene Herkünfte — `APP_ALLOW_ORIGIN`
**Seit `000-000-0039` (2026-09-15).**

Die Anwendung setzte `Access-Control-Allow-Origin` auf den `Origin` **jeder** Anfrage, dazu
`Access-Control-Allow-Credentials: true`. Gemessen: `Origin: https://evil.example` kam als erlaubte
Herkunft zurück. Jede fremde Seite durfte im Browser eines angemeldeten Benutzers Anfragen mit dessen
Credentials stellen und die Antwort lesen. `APP_ALLOW_ORIGIN` war deklariert und wurde nie gelesen.

**Was sich ändert:**

- **Ohne Eintrag gibt es kein `Access-Control-Allow-Origin`** und kein
  `Access-Control-Allow-Credentials` — auch nicht im OPTIONS-Preflight. Ein Browser-Client auf
  einer anderen Herkunft bekommt keine lesbare Antwort mehr.
- **`APP_ALLOW_ORIGIN`** nimmt die erlaubten Herkünfte, exakt (Schema, Host, Port), als Array oder
  kommagetrennt. Nur eine davon wird zurückgegeben, mit Credentials. Die Vorlage liest den Wert aus
  der Umgebungsvariable `APP_ALLOW_ORIGIN`.
- **`*`** erlaubt jede Herkunft, aber ohne Credentials — Browser lehnen die Kombination ohnehin ab.
- Jede Antwort trägt **`Vary: Origin`**.

*Was zu tun ist:* **Jede Herkunft eintragen, von der ein Browser-Client die API aufruft** — eine
Web-App auf eigener Domain, und bei Ionic/Capacitor die App selbst (`capacitor://localhost` unter
iOS, `http://localhost` unter Android; beim Entwickeln zusätzlich `http://localhost:8100`):

```sh
APP_ALLOW_ORIGIN=https://app.example.com,capacitor://localhost,http://localhost
```

Ohne diesen Eintrag meldet die Browser-Konsole des Clients einen CORS-Fehler, obwohl der Server mit
`200` antwortet. Server-zu-Server-Aufrufe und native HTTP-Clients sind nicht betroffen: CORS ist eine
Regel des Browsers.

### Das Framework erzwingt `display_errors=Off` in Produktion
**Seit `000-000-0018` (2026-09-09).**

Bisher setzte das Framework die Fehlerausgabe **nur im Debug-Modus**; ohne `APP_DEBUG` galt,
was die `php.ini` der Maschine sagte. In einer Umgebung ohne `php.ini` — das offizielle
`php:*`-Image lädt keine — gilt dann der Compile-Default `display_errors=On`, und eine
Produktionsinstanz liefert Deprecations, Warnings und Dateipfade an jeden Aufrufer aus.

Ab jetzt setzt das Framework bei `APP_DEBUG=false` ausdrücklich `display_errors=0`,
`display_startup_errors=0` und `log_errors=1`.

**Betroffen ist ein Projekt, das sich darauf verlassen hat, die Ausgabe selbst zu steuern** —
etwa über `php.ini` oder `ini_set()` vor dem Bootstrap. Diese Einstellungen werden jetzt
überschrieben.

*Was zu tun ist:* Wer Fehlerausgabe im Browser braucht, setzt `APP_DEBUG=true` — dafür ist der
Schalter da. Wer sie in Produktion braucht, hat ein anderes Problem. Fehler stehen weiterhin im
Log; `log_errors` wird ausdrücklich eingeschaltet.

*Nebeneffekt, der als Verbesserung gemeint ist:* Statuscodes stimmen wieder. Eine Deprecation
im Antwortstrom schickte die Header los, bevor Silex den Code setzen konnte — die Antwort trug
dann `200`, obwohl die Anwendung `405` oder `500` meinte. Beim ersten CI-Lauf sind daran sechs
Tests gescheitert, die lokal grün waren.

### Das Paket-Repository ist öffentlich — HTTPS statt SSH, `preferred-install` entfällt
**Seit `000-000-0087` (2026-09-23), wirksam ohne Release — gilt für jede Version im Paket-Repository.**

`area-net-gmbh/contentfly-framework-dist` ist öffentlich. Ein Projekt braucht **keinen Zugang
mehr** — keinen Deploy Key, kein Organisationskonto, keinen SSH-Schlüssel.

In der `composer.json` eines Projekts steht künftig

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/area-net-gmbh/contentfly-framework-dist.git" }
],
"require": { "areanet/contentfly": "^2.0" }
```

**Betroffen ist jedes Projekt, das Contentfly 2 schon bezieht.** Seine `composer.json` trägt eine
`git@github.com:…`-URL und einen `config.preferred-install`-Block für `areanet/contentfly`.

*Was zu tun ist:* Die URL auf `https://…` umstellen und den `preferred-install`-Block für
`areanet/contentfly` streichen — er erzwingt sonst weiterhin einen vollen Klon, wo ein Zip genügt.
Beides ist keine Eile: Die SSH-URL funktioniert weiter, solange ein Schlüssel vorhanden ist. Wer
umstellt, kann den Deploy Key für das Paket-Repository aus dem Projekt entfernen.

*Nebenbei gelöst:* Ein `github-oauth`-Token in `~/.composer/auth.json` brach die Installation
bisher mit `Repository not found` ab, obwohl ein gültiger SSH-Schlüssel vorlag — Composer schrieb
die SSH-URL auf HTTPS um und meldete sich mit dem Token an, das auf das private Repository keinen
Zugriff hatte. Bei einem öffentlichen Repository kann das nicht mehr passieren.

### Der Host-Block entscheidet über `display_errors` und `is_installed`
**Seit `000-000-0085` (2026-09-23), ausgeliefert mit `v2.2.1`.**

Das Versprechen des Eintrags darüber galt bisher nur dem **Default-Block**. `bootstrap.php` wählte
den Host-Block erst hinter den beiden Stellen, die ihn brauchen; bis dahin stand `Adapter::$host`
auf `'default'`, und `Factory::getConfig()` fällt für einen unbekannten Host auf den Default-Block
zurück. Beide Entscheidungen lasen deshalb den falschen Block:

- **`display_errors`** folgte dem `APP_DEBUG` des Default-Blocks. Ein Projekt, das dort für die
  lokale Entwicklung `true` stehen hat — der Normalfall —, lieferte auf **jedem** Server
  Fehlerausgabe, auch auf Staging und Live mit eigenem `APP_DEBUG = false`.
- **`is_installed`** folgte dem `DB_HOST` des Default-Blocks statt dem des Hosts.

Gemessen am Bestandsprojekt UFP (Staging, mittwald, PHP 8.4, `v2.2.0`): `APP_DEBUG` ist im
Host-Block `false`, `ini_get('display_errors')` war trotzdem `'1'`. Jede Deprecation landete als
HTML im Antwortrumpf, mit absoluten Serverpfaden; über `output_buffering` gingen die Header
vorzeitig hinaus, und die Antwort verliess den Server als `200 text/html` ohne `Cache-Control` und
ohne `Access-Control-Allow-Origin`.

**Betroffen ist jedes Projekt mit Host-Blöcken**, dessen Default-Block `APP_DEBUG = true` setzt.

*Was zu tun ist:* Nichts, wenn das Projekt keine Host-Blöcke benutzt — dann gilt der Default-Block
wie bisher. Wer Fehlerausgabe auf einem bestimmten Server sehen will, setzt `APP_DEBUG = true`
**in dessen Block**. Wer sich bisher darauf verlassen hat, dass ein Server trotz `APP_DEBUG = false`
Fehler in die Antwort schreibt, findet sie ab jetzt nur noch im Log.

### `error_reporting` steht auf `E_ALL`, auch im Debug-Modus
**Seit `000-000-0018` (2026-09-09).**

Vorher `E_ALL ^E_NOTICE ^E_DEPRECATED` — Deprecations wurden ausgerechnet im Debug-Modus
unterdrückt, also dort, wo ein Entwickler sie sehen will.

**Betroffen ist, wer im Debug-Modus entwickelt:** Es erscheinen jetzt Notices und Deprecations,
die vorher unsichtbar waren. Das ist kein neuer Code, nur neu sichtbarer.

*Was zu tun ist:* Sie abarbeiten. `an_project/docs/tech-stack.md` macht deprecation-freies Bauen
zur Pflicht — der spätere Sprung auf Symfony 8.4 LTS ist nur dann ein reiner Constraint-Bump.

### `POST /system/do` hat eine Erlaubnisliste statt `method_exists`
**Seit `000-000-0015` (2026-09-09).**

Erreichbar war alles, was `method_exists()` bejahte — auch `doAction` selbst (das den
Controller in eine Endlosrekursion schickte) und `setEM`/`__construct` aus `BaseController`.
Jetzt nennt eine ausgeschriebene Liste, was aufgerufen werden darf: `flushSchemaCache`,
`updateDatabase`, `deleteToken`, `generateToken`, `listTokens`, `addToken`.

**Betroffen ist, wer eine eigene Methode in einen abgeleiteten `SystemController` gelegt und
über `/system/do` aufgerufen hat.** Sie wird jetzt abgewiesen.

*Was zu tun ist:* Die Methode in die Liste aufnehmen. Dass das ein bewusster Schritt ist, ist
der Zweck der Änderung — vorher war jede neue Methode automatisch ein Endpunkt.

### Token-Vorgänge stehen mit `Log`-Konstanten in `pim_log`
**Seit `000-000-0015` (2026-09-09).**

`addToken` schrieb `mode = 'Erstellt'`, `deleteToken` `'Gelöscht'`. Die Konstanten heissen
`Log::INSERTED` (`'INS'`) und `Log::DELETED` (`'DEL'`). In `pim_log.mode` standen damit zwei
Vokabulare, und wer nach `Log::INSERTED` filterte, fand die Token-Vorgänge nicht.

**Der Altbestand bleibt unverändert — bewusst.** `pim_log` ist ein Protokoll; alte Zeilen
nachträglich umzuschreiben hiesse, die Aufzeichnung zu ändern. Eine Migration wäre technisch
einfach und fachlich falsch.

*Was zu tun ist:* Wer historisch auswertet, sucht für Token-Vorgänge **vor** diesem Stand nach
`'Erstellt'`/`'Gelöscht'` und danach nach `'INS'`/`'DEL'`. Ein Stichtag lässt sich aus
`pim_log.created` ablesen.

### `deleteToken` funktioniert
**Seit `000-000-0015` (2026-09-09).**

Kein Breaking Change, sondern das Gegenteil — aber erwähnenswert, weil sich Verhalten ändert:
Die Methode suchte in `Areanet\Contently\Entity\Token` („Contently" statt „PIM") und endete
vor ihrer ersten fachlichen Zeile. **Ein API-Token liess sich über die API nicht löschen.**

*Was zu tun ist:* Nichts. Wer einen Workaround gebaut hat — etwa Löschen direkt in der
Datenbank —, kann ihn ablegen.

### Der ImageMagick-Prozessor entfällt, `FILE_IMAGE_MAX_PIXELS` ist neu
**Seit `000-000-0068` (2026-09-21), ausgeliefert mit `v2.2.0`.**

**`Classes\File\Processing\ImageMagick` und `IMAGEMAGICK_EXECUTABLE` sind entfernt.** Der Prozessor
war mit der Voreinstellung nicht lauffähig — `is_executable('convert')` ist für einen relativen Namen
immer falsch, jeder Bild-Upload endete damit in einer Exception —, und er baute seine Aufrufe als
Shell-Zeile ohne `escapeshellarg()`. Kein Test erreichte ihn (0 %). Den Standard-Prozessor `Image`
(GD) betrifft das nicht.

**Neu: `FILE_IMAGE_MAX_PIXELS`**, Voreinstellung `24000000` (24 Megapixel, etwa 6000 × 4000). Ein Bild
mit mehr Pixeln lehnt der Upload mit **413** ab, bevor es dekodiert wird; `null` hebt die Grenze auf.
GD braucht rund vier Byte je Pixel, unabhängig von der Dateigrösse.

*Was zu tun ist:* Wer `FILE_PROCESSORS` auf den ImageMagick-Prozessor gesetzt hatte, stellt auf
`\Areanet\PIM\Classes\File\Processing\Image` zurück oder bringt einen eigenen Prozessor mit.
Wer grössere Bilder annehmen muss, setzt `FILE_IMAGE_MAX_PIXELS` hoch und `memory_limit` mit —
rund fünf Byte je zugelassenem Pixel.

## API

### `/api/deleted` meldet auch Löschungen aus Contentfly 1.x
**Seit `000-000-0094` (2026-09-25).**

Contentfly 1.x schrieb für eine Löschung `'Gelöscht'` in `pim_log.mode`. `/api/all` meldet solche Zeilen
als Löschung — entschieden am 2026-09-14 (`014-003-0002`), damit Sync-Clients sie weiter bekommen.
**`/api/deleted` fragte nur `DEL` und `USERDEL` ab:** Ein Client, der Löschungen dort holt, erfuhr von
einer Alt-Löschung nie, und das Objekt blieb bei ihm stehen.

*Was ein Client merkt:* `/api/deleted` liefert für ein Bestandsprojekt mit Daten aus 1.x **mehr**
Zeilen — dieselbe Form (`model_name`, `model_id`), dasselbe Leserecht, dasselbe `lastModified`. Ein
Client, der eine Löschung doppelt bekommt, löscht etwas, das schon weg ist; das ist folgenlos. Mit
`lastModified` kommen alte Alt-Löschungen nicht nach; ein Client, der vollständig sein muss, fragt
einmal ohne.

### `GROUP` bei `/api/all` und `/api/count`: auch die über `users` freigegebenen Datensätze
**Seit `000-000-0093` (2026-09-25).**

Die Stufe `GROUP` erreicht, was `OWN` erreicht — angelegt oder in `users` eingetragen —, und dazu, was
für die eigene Gruppe freigegeben ist. `/api/list`, `/api/single` und die Baum-Routen hielten sich
daran. **`/api/all` und `/api/count` liessen `users` weg:** Ein Datensatz, der einem `GROUP`-Leser nur
über `users` freigegeben war, erschien in `/api/list`, fehlte aber in `/api/all` und wurde in
`/api/count` nicht gezählt. Ein Sync-Client bekam ihn nie.

*Was ein Client merkt:* Ein Benutzer mit Leserecht `GROUP` bekommt bei `/api/all` und `/api/count`
**mehr** als bisher — genau die Datensätze, die er über `/api/list` schon sah. Nach dem Update liefert
der nächste Sync-Lauf sie nach. Mit `lastModified` kommen sie nur, wenn sie seitdem geändert wurden;
ein Client, der vollständig sein muss, synchronisiert einmal ohne `lastModified`.

### `/api/query` nur noch für Admins — `apiQueryEnabled` wirkt nicht mehr
**Seit `000-000-0097` (2026-09-25), ausgeliefert mit `v2.4.0`.**

**Ein Sicherheitsfix.** `/api/query` baut die Abfrage aus den Teilen des Requests und reicht sie als SQL
an die Datenbank. Bisher erreichten ihn Nicht-Admins, deren Gruppe `apiQueryEnabled = 'enabled'` trug;
eine Rechteprüfung verengte dabei die Entities des Schemas. Das war keine Grenze: Über die als SQL
durchgereichten Teile konnte eine solche Gruppe Daten lesen, auf die ihre Rechte keinen Zugriff geben.

| | Vorher | Jetzt |
|---|---|---|
| Admin | darf abfragen | unverändert |
| Nicht-Admin, Gruppe mit `apiQueryEnabled = 'enabled'` | darf abfragen, verengt nach Leserecht | **403** `contentfly_general_access_denied` |
| Nicht-Admin ohne das Feld | **500** mit `contentfly_general_access_denied` | **403**, derselbe Code |
| Feld `apiQueryEnabled` in `pim_group` | öffnete den Endpunkt | bleibt als Spalte, wird **nicht mehr gelesen** — kein Schema-Update |

**Getroffen ist ein Projekt, das `apiQueryEnabled` für eine Gruppe gesetzt hat** — nachsehen mit
`SELECT name FROM pim_group WHERE apiQueryEnabled = 'enabled'`. Ohne Treffer merkt das Projekt nichts.

*Was zu tun ist:* Clients dieser Gruppen fragen über die Lese-Endpunkte ab (`/api/list`, `/api/single`,
`/api/count`), die nach dem Leserecht verengen, oder über einen eigenen Endpunkt im Projekt, der genau
die Abfrage ausführt, die er braucht. Eine Abfrage über `/api/query` braucht einen Admin-Zugang.

### `customNavigation` entfällt — mit `PIM\Nav`, `PIM\NavItem` und `FRONTEND_CUSTOM_NAVIGATION`
**Seit `000-000-0077` (2026-09-25), ausgeliefert mit `v2.4.0`.**

**Der letzte Rest der gestrichenen Oberfläche im Schema.** `customNavigation` baute die Menüs der
PIM-Oberfläche aus `PIM\Nav` und `PIM\NavItem`: Routen `#/list/<entity>`, Glyphicon-Icons. Mit Epic
`012` ist die Oberfläche weg; `000-000-0010` hatte den Schlüssel noch als „Daten“ stehen lassen.
Schlüssel, beide Entities und der Schalter gehen jetzt zusammen — einer allein wäre ein halber Rest.

| Was | Vorher | Jetzt |
|---|---|---|
| `meta.frontend` in `/api/schema` und im Login mit `withSchema` | `customNavigation` und `languages` | nur `languages` |
| `PIM\Nav`, `PIM\NavItem` | Entities des Frameworks | **gestrichen**; `/api/list` antwortet `404` |
| `FRONTEND_CUSTOM_NAVIGATION` | schaltete `customNavigation.items` frei | wird nicht mehr gelesen |

**Das Schema-Update löscht die Tabellen — mit ihren Zeilen.** Gemessen an einer Installation mit
einer Zeile Navigation:

```
ALTER TABLE pim_nav DROP FOREIGN KEY …;      -- fünf Zeilen dieser Art
DROP TABLE pim_nav;
DROP TABLE pim_navItem;
```

Getroffen ist ein Projekt, das die Navigation für einen eigenen Client pflegt. Ein Projekt ohne
Zeilen in `pim_nav` verliert nichts.

*Was zu tun ist:*
- **Ein Client, der `frontend.customNavigation` liest,** baut seine Menüs künftig selbst.
- **Wer die Navigationsdaten braucht,** übernimmt **vor** dem Schema-Update beide Entities nach
  `custom/Entity/` — Klassen aus Contentfly 2.3 kopieren, Namespace anpassen, Tabellennamen
  `pim_nav` und `pim_navItem` behalten, im `ManyToOne` von `NavItem` die eigene `Nav` eintragen.
  Danach meldet `--dump-sql` für beide nichts mehr, und die Zeilen sind erhalten (gemessen).
- **Wer sie nicht braucht,** lässt das Update die Tabellen löschen — oder sichert sie vorher mit
  `mysqldump contentfly pim_nav pim_navItem`.
- `FRONTEND_CUSTOM_NAVIGATION` aus `custom/config.php` streichen. Stehen gelassen stört er nicht:
  `Config` erlaubt eigene Schlüssel (`000-000-0040`).

### Rechte verwalten nur noch Admins — auch beim Schreiben
**Seit `000-000-0090` (2026-09-24), ausgeliefert mit `v2.3.0`.**

Beim Lesen galt das schon lange: Die Rechte einer Gruppe zeigt die API nur Admins. Beim Schreiben
prüfte sie nichts dergleichen. Ein Nicht-Admin mit Schreibrecht auf einer von drei Entities war
damit faktisch Admin:

| Schreibrecht auf | Weg | Wirkung |
|---|---|---|
| `PIM\Group` | `permissions` der eigenen Gruppe setzen | beliebige Rechte |
| `PIM\Permission` | eine Rechte-Zeile für die eigene Gruppe anlegen | beliebige Rechte |
| `PIM\User` | am eigenen Datensatz `isAdmin` setzen — `OWN` genügt | Admin |
| `PIM\User` | das Passwort eines **anderen** Benutzers setzen, bestätigt wird nur das eigene | Anmeldung als dieser Benutzer, auch als Admin |

Jetzt antwortet die API einem Nicht-Admin mit `403` `contentfly_general_permission_denied`, wenn er

- `permissions` an `PIM\Group` schreibt, beim Anlegen wie beim Ändern,
- `PIM\Permission` anlegt, ändert oder löscht,
- `isAdmin` oder `group` eines Benutzers ändert, auch beim Anlegen,
- `pass`, `salt`, `loginManager` oder `externalId` eines **anderen** Benutzers ändert.

`context.value` nennt Entity und Feld. Ein Wert, der sich nicht ändert, ist keine Änderung: Wer
seinen eigenen Datensatz so zurückschickt, wie er ihn gelesen hat, bekommt weiter `200`. Das gilt
für `/api/insert`, `/api/update`, `/api/multiupdate` und `/api/delete`. Die Ablehnung kommt vor
jeder Prüfung des Inhalts: Schickt ein Nicht-Admin fehlerhafte `permissions`, antwortet die API mit
`403`, nicht mit dem `400` aus dem Eintrag darunter.

**Weiterhin erlaubt:** eine Gruppe umbenennen, das eigene Passwort mit Bestätigung des aktuellen
ändern, einen Benutzer ohne Gruppe und ohne Admin-Recht anlegen.

*Was zu tun ist:* Nichts, solange Benutzer, Gruppen und Rechte nur von Admins verwaltet werden. Ein
Projekt, in dem ein Nicht-Admin das tun soll, braucht dafür einen Admin-Zugang — eine feinere Regel
gibt es nicht. Wo ein Nicht-Admin bisher fremde Passwörter zurückgesetzt hat, übernimmt das ein Admin.

### `permissions` einer Gruppe: fehlerhafte Einträge antworten mit 400, ohne die Rechte anzufassen
**Seit `000-000-0088` (2026-09-24), ausgeliefert mit `v2.3.0`.**

`PermissionsType::toDatabase()` prüfte die Einträge nicht, und es **löscht die Rechte der Gruppe,
bevor es die neuen schreibt**. Was dabei herauskam, erhoben am unveränderten Code:

| Request | vorher | jetzt |
|---|---|---|
| Eintrag ohne `name`, `readable`, `writable` oder `deletable`, oder kein Objekt | 500 — beim Update **alle Rechte der Gruppe gelöscht** | 400 |
| `permissions: null` oder ein String | 200 — alle Rechte gelöscht, PHP-Warnung im Log | 400 |
| dieselbe Entity zweimal | 200 — zwei Zeilen, welche galt, war Zufall | 400 |
| Eintrag ohne `export` | 500 — wie oben | 200, `export` = 0 |

Jede Ablehnung ist `400` `contentfly_general_invalid_params`, `context.value` nennt den Eintrag. Geprüft
wird der ganze Request, bevor etwas geschrieben wird: Die Rechte bleiben unverändert, und ein
abgelehnter Insert legt keine Gruppe an. `export` wirkt seit `000-000-0012` nicht mehr und ist
deshalb optional; Pflicht war es nur aus Versehen, als fehlender Array-Schlüssel.

*Was zu tun ist:* Wer die Rechte einer Gruppe leeren will, schickt `permissions: []` statt `null`.
Ein Client, der eine Entity zweimal schickt, fasst die Einträge zusammen. Ein Client, der auf
`500` geprüft hat, prüft auf `400`.

### Gruppenrechte bekommen keinen stillen Vollzugriff auf `PIM\Tag` mehr
**Seit `000-000-0070` (2026-09-23), ausgeliefert mit `v2.3.0`.**

`PermissionsType::toDatabase()` legte bei **jedem** Schreiben der Rechte einer Gruppe zusätzlich
eine Zeile für `PIM\Tag` an — lesen, schreiben und löschen auf `ALL`, unabhängig davon, was der
Request verlangte. Wer eine Gruppe über `/api/insert` oder `/api/update` mit `permissions` anlegte
oder änderte, gab ihr damit ungefragt vollen Zugriff auf alle Tags.

**Schwerer wog die zweite Wirkung:** Die Zeile stand neben einem ausdrücklich mitgeschickten
`PIM\Tag`, und `Classes\Permission::is()` lieferte den **ersten** Treffer zum Entitätsnamen. Die
Assoziation trägt kein `ORDER BY`. Bei GUID-Ids liefert MySQL die Zeilen einer Gruppe nach der Id,
also zufällig: Je Gruppe entschied der Zufall, ob die Einschränkung oder die `ALL`-Zeile galt.
Gemessen mit `000-000-0088` an 20 Gruppen mit denselben zwei Zeilen: Die zuerst eingefügte kam in 5
Fällen zuerst. Ein Aufrufer konnte Tags **nicht verlässlich einschränken, auch wenn er es verlangte**.

> **Korrektur.** Bis `000-000-0088` stand hier, die Reihenfolge sei „die der Einfügung“ und die
> Einschränkung damit **nie** wirksam. Das gilt nur für ganzzahlige Ids; mit GUIDs war es Zufall.

Ein Überbleibsel der mit Epic `012` gestrichenen PIM-Oberfläche, die Tags an Dateien brauchte. Die
Zeile setzte weder `export` noch `extended` — anders als die angeforderten; sie war nie Teil des
Vertrags.

**Betroffen ist jedes Projekt, das Gruppenrechte über die API schreibt** und sich — wissentlich
oder nicht — darauf verlassen hat, dass Tags immer lesbar sind.

**Bestandsgruppen mit ausdrücklichem `PIM\Tag`** hatten zwei Zeilen — die `ALL`-Zeile und die
Einschränkung. Seit `000-000-0088` gilt bei mehreren Zeilen für dieselbe Entity die
**restriktivste** (`NONE` vor `OWN` vor `GROUP` vor `ALL`), je Recht einzeln. Die Einschränkung
wirkt damit ab dem Deployment, ohne dass die Gruppe neu gespeichert werden muss. Die Zeilen selbst
bleiben stehen, bis die Rechte der Gruppe das nächste Mal geschrieben werden.

*Was zu tun ist:* Wer Tag-Zugriff braucht, nimmt `PIM\Tag` ausdrücklich in die `permissions` des
Requests auf. Ab jetzt wirkt der Wert auch. **Achtung bei Bestandsgruppen ohne ausdrückliches
`PIM\Tag`:** Ihre vorhandene `ALL`-Zeile ist ihre einzige und gilt weiter, bis ihre Rechte das
nächste Mal geschrieben werden — dann verschwindet sie. Wer das nicht will, ergänzt vorher
`PIM\Tag` in den Rechten der Gruppe. Welche Gruppen zwei Zeilen tragen, zeigt:

```sql
SELECT group_id, entityName, COUNT(*) FROM pim_permission
GROUP BY group_id, entityName HAVING COUNT(*) > 1;
```

### `@PIM\Select` prüft jetzt beim Schreiben
**Seit `000-000-0017` (2026-09-09).**

Die Optionen der Annotation standen bisher nur im Schema; **niemand verglich einen Schreibwert
damit**. Ein Wert ausserhalb der Liste wurde angenommen und landete unverändert in der Spalte.
Ab jetzt wird er mit `contentfly_general_invalid_params` abgewiesen.

**Betroffen ist ein Projekt, dessen Daten heute Werte ausserhalb der Liste enthalten** — oder
dessen Clients solche schreiben. Der Fehler tritt beim nächsten Schreiben auf, nicht beim
Update selbst: Bestehende Zeilen bleiben unangetastet, sie lassen sich nur nicht mehr mit
demselben Wert zurückschreiben.

*Warum jetzt:* Eine veröffentlichte Werteliste, die nichts zusichert, ist schlechter als keine
— ein Client baut seine Auswahl daraus und verlässt sich darauf. Der einzige Konsument der
Liste war früher die gelöschte Oberfläche; seitdem war sie eine Zusicherung ohne Wirkung.

*Was zu tun ist:* Vor dem Umstieg prüfen, welche Werte tatsächlich in den betroffenen Spalten
stehen:

```sql
SELECT DISTINCT <spalte> FROM <tabelle>;
```

Was nicht in der Optionsliste steht, gehört entweder in die Liste aufgenommen oder in den Daten
korrigiert.

**`null` und der leere String gehen weiterhin durch.** Ob ein Feld leer sein darf, entscheidet
`nullable` an der Spalte, nicht die Optionsliste — sonst wäre nebenbei jedes Select-Feld zum
Pflichtfeld geworden.

### Ein Feld mit unbekanntem Spaltentyp meldet sich
**Seit `000-000-0017` (2026-09-09).**

Griff keiner der registrierten Typen auf eine Entity-Eigenschaft, fiel sie **still** aus dem
API-Schema: kein Eintrag, keine Warnung. Lesen lieferte das Feld nicht, Schreiben scheiterte mit
`contentfly_general_unknown_property` — und niemand erfuhr, warum. Jetzt gibt es dabei eine
`E_USER_WARNING`, die Entity, Eigenschaft und Spaltentyp nennt.

**Geworfen wird nicht.** Ein Projekt mit einem exotischen Spaltentyp soll nach dem Umstieg sein
Schema noch aufbauen können. Die Warnung landet im Log; in einer Testsuite mit `failOnWarning`
fällt sie sofort auf.

*Was zu tun ist:* Nach dem Umstieg einmal ins Log sehen. Wer eine solche Warnung findet, hat ein
Feld, das die API noch nie kannte — `json` ist mit dieser Version dazugekommen, für andere Typen
braucht es einen eigenen `Type`.

### Binärspalten fallen ohne Warnung aus dem Schema
**Seit `000-000-0046` (2026-09-15).**

Die Warnung aus dem Eintrag davor gilt weiter — außer für `blob` und `binary`. JSON kann keine
beliebigen Bytes tragen; so eine Spalte ist Datenhaltung des Servers (ein Embedding-Vektor, ein
Hash), kein Feld der API. Sie fehlt im Schema wie bisher, nur ohne Meldung
(`Classes\Type\UntypedColumn`).

**Warum das nicht kosmetisch ist:** Mit `APP_DEBUG` setzt der Bootstrap `display_errors=1`, und die
Warnung stand als HTML **vor** dem JSON jeder Antwort, die das Schema baut — beim Bestandsprojekt UFP
(`007-005-0004`, `AI\VectorDocument::embedding`) vor jedem Login. Auf Instanzen ohne Debug landete
sie nur im Log.

*Was zu tun ist:* Nichts. Ein anderer unbekannter Spaltentyp meldet sich weiterhin — und steht mit
`APP_DEBUG` weiterhin in der Antwort, bis er einen `Type` bekommt.

### `/file/upload` weist ausführbare Dateien mit `415` ab
**Seit `000-000-0038` (2026-09-15).**

Ein Upload behielt die Endung, die der Client schickte, und `data/files/` wird direkt vom Webserver
ausgeliefert. Eine hochgeladene `.php`-Datei wurde beim Abruf **ausgeführt** — gemessen an einer
frischen Installation. Jeder mit einem gültigen Token konnte das auslösen.

**Was sich ändert:**

- **Abgewiesen mit `415`** (`contentfly_file_invalid_type`) wird jeder Name, der in irgendeinem
  Punkt-Segment eine ausführbare Endung trägt (`.php`, `.phtml`, `.phar`, `.pht`, `.cgi`, …, auch
  `shell.php.jpg`), und jeder Server-Konfigurationsname (`.htaccess`, `.user.ini`, …). Es entstehen
  weder Datei noch Zeile. Die Liste ist Code, nicht Konfiguration.
- **Der gespeicherte Name wird vom Framework gebildet:** kleingeschrieben, Sonderzeichen und
  Leerzeichen zu `-`, keine Pfadbestandteile, **die Endung kleingeschrieben**. Aus
  `Report (2026).TXT` wird `report-2026.txt`; vorher wurden Sonderzeichen entfernt und die Endung
  behielt ihre Schreibweise (`report-2026.TXT`). Ein Client, der den gespeicherten Namen aus dem
  Upload-Namen vorhersagt, liest ihn aus der Antwort.
- **`FILE_ALLOWED_TYPES` ist neu und optional.** Gesetzt, gilt eine Whitelist: Die Endung muss
  darin stehen, und der aus dem **Inhalt** ermittelte Typ (ext-fileinfo) muss passen. Ohne Eintrag
  bleibt alles erlaubt, was die Sperrliste passiert.
- **Ein Datensatz aus der Zeit davor** mit ausführbarem Namen wird beim erneuten Hochladen
  umbenannt, die alte Datei entfernt; `/file/overwrite` lehnt ihn mit `415` ab.
- `FileController::sanitizeFileName()` (protected) ist entfallen; die Namensbildung liegt in
  `Classes/File/UploadValidator`.

*Was zu tun ist:* **Den Bestand prüfen** — `SELECT id, name FROM pim_file WHERE name REGEXP
'\\.(php[0-9s]?|phtml?|phar|pht|inc|shtml?|stm|pl|pm|py|rb|cgi|fcgi|sh|bash|zsh|jspx?|aspx?|ashx|asmx|cfml?|htaccess|htpasswd)(\\.|$)'` —
und gefundene Dateien unter `data/files/<id>/` löschen oder umbenennen. Wer nur bestimmte
Dateitypen braucht, setzt `FILE_ALLOWED_TYPES` in `custom/config.php`.

### `POST /api/mail` entfällt ersatzlos
**Seit `000-000-0016` (2026-09-09).**

Der Endpunkt ist entfernt, samt Route, Methode und dem Konfigurationsfeld `APP_MAILFROM`. Ein
Aufruf endet ab jetzt mit „Method Not Allowed" statt mit einem Serverfehler.

**Betroffen ist praktisch niemand.** Der Endpunkt hat seit dem Sprung auf PHP 8 **nichts mehr
verschickt**: `mailAction()` las die Absenderadresse als nackte Konstante `APP_MAILFROM`, die
nirgends per `define()` gesetzt war. Unter PHP 8 ist das ein `Error`, und er trat auf, bevor
`mail()` überhaupt drankam. Wer den Endpunkt aufrief, bekam einen 500 — und das seit Jahren,
ohne dass es jemandem auffiel.

*Warum entfernen statt reparieren:* Der Fehler wäre in einer Zeile behoben gewesen, und
dieselbe Zeile hätte ein **offenes Mail-Relais hinter einem Token** freigeschaltet. `mailto`,
`subject` und der Rumpf kamen unverändert vom Aufrufer, ohne Empfängerprüfung, ohne
Rate-Begrenzung, ohne Protokolleintrag. Jeder angemeldete Benutzer hätte an jede Adresse mit
beliebigem Inhalt senden können, mit der Absenderdomäne der Installation.

*Was zu tun ist:* Wer Mail verschicken will, benutzt `$app['mailer']` — den PHPMailer-Dienst,
den `bootstrap.php` registriert und der von dieser Änderung **nicht** betroffen ist. Er bleibt
samt aller `MAILER_*`-Konfigurationsfelder. Der Unterschied: Der Versand liegt dann im
Projektcode, wo die Empfängerprüfung hingehört, statt hinter einem generischen Endpunkt.

Ein `APP_MAILFROM` in der eigenen `custom/config.php` kann entfernt werden; es wird nicht mehr
gelesen.

### Die Vorlage antwortet im Envelope — `success`, `status` und `i18n` entfallen
**Seit `011-003-0001` (2026-09-17).**

Betrifft ein Projekt, das `custom/Classes/Service/Core/ApiResponseService.php` aus der Vorlage
übernommen hat. Der Dienst antwortete mit `success`, `status`, `i18n`, `data`, `errors`, `meta`
und `timestamp`; jetzt mit `data`, `errors`, `meta` — derselben Hülle wie jeder Endpunkt des
Frameworks.

| vorher | jetzt |
|---|---|
| `success`, `status` | entfallen — der Statuscode steht in der HTTP-Antwort |
| `i18n` (Erfolgsfall) | entfällt — der `200` sagt es, und zwar übersetzungsfest |
| `i18n` (Fehlerfall) | zieht nach `errors[].context`; `ApiResponseService::fault()` baut den Eintrag |
| `timestamp` | entfällt — `meta.ts` trägt ihn, im selben Format wie überall |

**Warum die Vorlage das überhaupt anders machte:** Der Dienst stammt aus dem Kundenprojekt, aus dem
die Vorlage geschnitten wurde, und war das **Vorbild** für den Envelope des Frameworks — „als
Vorbild ja, wörtlich nein" (`an_project/docs/api-envelope.md`). Solange das Framework keine eigene
Form hatte, war das in Ordnung. Seit `011-001` widerspricht es ihm.

*Was zu tun ist:* Wer den Dienst übernommen und angepasst hat, behält seine Fassung — sie ist
Projektcode. Wer ihn unverändert benutzt, zieht die Auswertung nach: `body.success` entfällt
(der Statuscode genügt), `body.i18n.key` wird zu `body.errors[0].context.i18nKey`,
`body.timestamp` zu `body.meta.ts`.

### Eine tote Twig-Vorlage ist aus `custom/Views/` entfernt
**Seit `011-003-0001` (2026-09-17).**

`custom/Views/partials/_email_layout.twig` band `_header.twig`, `_workspace_bar.twig` und
`_footer.twig` ein — **keine davon existierte**, und Twig steht seit Epic `012` in keinem der
beiden Manifeste mehr. Die Datei konnte nicht gerendert werden, seit die Oberfläche entfallen ist;
sie stand nur noch da. Mit ihr sind die leeren Verzeichnisse `custom/Views/`, `custom/Provider/`
und `custom/i18n/` gegangen, die von nichts referenziert wurden.

*Was zu tun ist:* Nichts, wenn Sie sie nie benutzt haben. Wer sie kopiert hat und rendert, bringt
Twig selbst mit — dann ist es Projektcode und bleibt unberührt.

### Der `frontend`-Block schrumpft, in `/api/config` entfällt er ganz
**Seit `000-000-0010` (2026-09-09).**

Zwei Änderungen am **Antwortformat**, beide Reste der mit Epic `012` entfernten Oberfläche.

**`/api/config` — der öffentliche Endpunkt.** Er ist die einzige Route des
`ApiControllerProvider` ohne Token-Pflicht; was sie preisgibt, sieht jeder. Sie lieferte
bisher:

```json
{"frontend":{"customLogo":false},"devmode":false,"version":"...","hash":"..."}
```

Der Schlüssel `frontend` ist **ganz entfallen**, nicht geleert — ein Schlüssel, der nichts
mehr trägt, lädt dazu ein, wieder etwas hineinzulegen.

**`/api/schema` und die Login-Antwort.** Ihr `frontend`-Block geht von sieben Schlüsseln auf
zwei. Entfallen sind `customLogo`, `formImageSquarePreview`, `title`, `welcome` und
`login_redirect`. Geblieben sind `customNavigation` (liest `PIM\Nav` und `PIM\NavItem`, also
Daten) und `languages` (aus `APP_LANGUAGES`, bestimmt die Hauptsprache). **`customNavigation`
entfällt seit `000-000-0077`** — eigener Eintrag weiter oben.

**Betroffen ist ein Client, der einen dieser Schlüssel liest.** Ein Client, der nur
`devmode`, `version` und die Entity-Beschreibungen auswertet, merkt nichts.

*Was zu tun ist:* Die Auswertung dieser Schlüssel entfernen. Ersatz gibt es nicht — sie
beschrieben eine Oberfläche, die nicht mehr existiert. Wer einen Wert weiterhin braucht, legt
ihn im eigenen Projekt ab, nicht im Framework-Schema.

### `multipe` und `multiple` verschwinden aus dem Typ-Schema
**Seit `000-000-0010` (2026-09-09).**

Neun Typ-Klassen setzten einen dieser beiden Schlüssel, sechs davon unter dem Tippfehler
`multipe`. **Gelesen wurde er an keiner Stelle** — ein reiner Widget-Hinweis für die
gelöschte Oberfläche. Im ausgelieferten Schema waren es 59 Vorkommen.

*Was zu tun ist:* Nichts, sofern der eigene Client sie nicht auswertet. Tut er es, ist der
Tippfehler ein guter Anlass, es zu lassen: Auf welchen der beiden Schlüssel ein Typ hörte,
war reiner Zufall.

### `POST /api/multiupdate` ist ganz oder gar nicht
**Seit `000-000-0009` (2026-09-09).**

Der Endpunkt lief ohne Transaktion. Scheiterte das dritte von fünf Objekten, blieben zwei
geändert, drei unberührt, und die Antwort war ein Fehler ohne Angabe, wie weit der Stapel kam.
Jetzt läuft er in einer Datenbanktransaktion: Ein Fehler an irgendeiner Stelle rollt **alles**
zurück, auch die Zeilen, die `pim_log` nebenbei geschrieben hätte.

**Betroffen ist ein Projekt, das sich auf den Teilerfolg verlassen hat** — etwa eines, das
einen langen Stapel schickt, den Fehler hinnimmt und beim nächsten Versuch nur den Rest
nachreicht. Dieses Muster schreibt jetzt gar nichts mehr.

*Was zu tun ist:* Den Stapel als Ganzes wiederholen. Das ist seit der Änderung gefahrlos, denn
der gescheiterte Versuch hat nichts hinterlassen. Wer bewusst Teilerfolge will, schickt die
Objekte einzeln über `POST /api/update`.

### `POST /api/multiupdate` antwortet mit `ts` und `data`
**Seit `000-000-0009` (2026-09-09).**

Der Erfolgsfall lieferte den dünnsten Rumpf aller Endpunkte: nur `version` und `hash`. Jetzt
kommen `ts` und `data` dazu; `data` listet je Objekt aus dem Request `entity` und `id`, in
dessen Reihenfolge. Die Mitschriften in die übrigen Sprachen einer I18N-Entität sind Folge
desselben Eintrags und stehen nicht einzeln in der Liste.

**Ein bestehender Client bricht daran nicht** — es kommt etwas dazu, es fällt nichts weg.

### `POST /api/multiupdate` ohne `objects` ist ein Fehler
**Seit `000-000-0009` (2026-09-09).**

Fehlte `objects` oder war es kein Array, lief die Schleife über `null` und der Aufruf endete
mit `200`. Jetzt kommt `contentfly_general_invalid_params`. Solange die Antwort leer war, fiel
der Unterschied nicht auf; seit sie aufzählt, was geschrieben wurde, wäre eine leere Liste auf
einen kaputten Request hin eine falsche Auskunft.

*Was zu tun ist:* `objects` mitschicken — auch für einen leeren Stapel, als `[]`. Der leere
Stapel bleibt ausdrücklich erlaubt.

### Eine unbekannte Id beantwortet die API mit 404
**Seit `000-000-0006` (2026-09-09).**

`Api::getSingle()` gab bei „nicht gefunden" eine fertige `JsonResponse` zurück — eine
HTTP-Antwort aus einer Klasse, die kein Controller ist. Jeder interne Aufrufer prüft mit
`if(!$object)`, und ein Objekt ist wahr; die Prüfung lief also ins Leere. Die Folgen, je nach
Endpunkt:

- `POST /api/single` antwortete mit **200** und `data: {"headers": {}}` — der serialisierten
  Antwort, die der Endpunkt als Nutzlast weiterreichte.
- `POST /api/update` und `POST /api/delete` liefen an ihrer eigenen 404-Prüfung vorbei und
  starben weiter unten an einem Typfehler. Beim Aufrufer kam **500** an.

Jetzt liefert `getSingle()` `null`, und alle drei antworten mit **404** und
`contentfly_general_not_found`.

**Betroffen ist jeder Client, der auf 200 oder 500 prüft**, um „gibt es nicht" zu erkennen —
insbesondere einer, der `data.headers` als Erkennungsmerkmal benutzt hat.

*Was zu tun ist:* Auf 404 prüfen. Das `headers`-Artefakt gibt es nicht mehr.

### `GET /` und unbekannte Pfade antworten mit 405 statt 302
**Seit `000-000-0006` (2026-09-09).**

Der Fehlerhandler leitete jede Anfrage ohne JSON-Content-Type auf `/` um. Das stammt aus der
Zeit, als unter `/` die PIM-Oberfläche lag: Ein Browser, der irgendwo einen Fehler auslöste,
wurde nach Hause geschickt. Die Oberfläche ist mit Epic `012` entfallen — die Umleitung zeigte
seither auf sich selbst, `GET /` beantwortete die Anwendung mit einer endlosen Kette von
`302`.

Die Umleitung ist entfallen. Wer ohne JSON-Content-Type anfragt, bekommt die JSON-Antwort der
API; im Debug-Modus weiterhin die Ausnahme im Klartext. Zugleich kommt der Statuscode jetzt aus
`getStatusCode()`, wenn die Ausnahme keinen eigenen trägt — `GET /` ergibt damit **405** statt
**500**.

*Was zu tun ist:* Nichts, sofern der Client kein HTML erwartet. Ein Browser, der auf die
Umleitung gebaut hat, findet unter `/` ohnehin nichts mehr.

### Der Statuscode steht nicht mehr im Fehlerrumpf
**Seit `011-001-0003` (2026-09-16).** Ersetzt die Zwischenstufe aus `000-000-0006`.

`000-000-0006` hat das Feld `status` in Ordnung gebracht: Für alles, was weder
`ContentflyException` noch `ContentflyI18NException` war — also für jeden PHP-Fehler — stand der
Code als **schlüsselloser** Eintrag im Rumpf und kam als `"0"` beim Client an.

Jetzt ist das Feld **ganz weg**. Der Statuscode steht in der HTTP-Antwort, dort gehört er hin,
und ein Rumpf, der ihn wiederholt, lädt dazu ein, dass beide auseinanderlaufen — genau das war
vor `000-000-0006` der Fall: Bei einer `ContentflyException` nannte der Rumpf `getCode()` und die
Antwort etwas anderes.

*Was zu tun ist:* Den Statuscode der HTTP-Antwort lesen statt `body.status`.

### Ein Fehler beim Start antwortet mit JSON statt mit leerem Rumpf
**Seit `000-000-0024` (2026-09-15).**

Eine Ausnahme, die fällt, bevor der Kernel steht — ein unerfüllbarer `APP_CACHE_DRIVER`, eine
fehlende `custom/config.php` —, erreichte keinen Fehlerhandler. Die Antwort war **HTTP 500 mit
0 Byte**; die Meldung stand nur im Serverlog.

Jetzt fängt `Kernel\Start::web()` sie ab und antwortet im Format der übrigen Fehlerantworten —
**seit `011-001-0003` im Envelope**, also `data: null`, `errors` und `meta`, bei `APP_DEBUG`
zusätzlich `meta.debug` mit Datei, Zeile und Trace. Ohne `APP_DEBUG` ersetzt die Antwort Projekt-
und Paketverzeichnis durch `<project>` bzw. `<package>`. Die Meldung steht weiterhin im Serverlog,
dort mit vollen Pfaden. Die Konsole ist unverändert: Dort endet ein Startfehler wie bisher mit der
Ausnahme auf `stderr`.

**`meta.version` ist hier `null`, und das ist eine Aussage:** Diese Antwort entsteht, bevor
`version.php` gelesen ist — zu diesem Zeitpunkt weiss niemand, welche Version nicht starten
konnte.

*Was zu tun ist:* Nichts. Wer die leere 500 als Zeichen für eine Fehlkonfiguration ausgewertet
hat, liest jetzt `errors[0].detail`.

### Die Dateiauslieferung leitet auf einen Pfad ab `WEB_ROOT` um
**Seit `000-000-0006` (2026-09-09).**

`bootstrap-web.php` hat `Config::WEB_ROOT` bei jedem Request aus `$_SERVER['PHP_SELF']`
überschrieben. Das traf unter Apache mit der mitgelieferten `.htaccess` zu und sonst nirgends;
unter dem eingebauten PHP-Server zeigte `GET /file/get/<id>` anschliessend auf
`/index.php/file/get/data/files/…` — ins Leere. Jetzt kommt der Wert aus der Konfiguration,
Vorgabe `/`.

**Betroffen ist eine Installation in einem Unterverzeichnis.** Bisher hat PHP_SELF das
zufällig richtig geraten, solange die `.htaccess` griff.

*Was zu tun ist:* In `custom/config.php` `WEB_ROOT` auf `'/unterverzeichnis/'` setzen, mit
Schrägstrich am Ende. Wer im Wurzelverzeichnis liegt, muss nichts tun.

### `export` und `extended` verschwinden aus dem `permissions`-Block
**Seit `000-000-0012` (2026-09-09).**

Beide standen im Schema und wurden **an keiner Stelle geprüft**. Ein Benutzer mit `export = 0`
las, schrieb und löschte unverändert; ein `extended`-Eintrag schränkte keine Antwort ein.
`canExport`s Konsument war der `ExportController`, gelöscht in `012-001-0003`; `getExtended`
hatte nie einen Durchsetzungspunkt im Framework.

Die drei Möglichkeiten waren entfernen, durchsetzen oder als Client-Metadaten behalten.
**Entfernt.** Durchsetzen hätte für `canExport` einen Endpunkt gebraucht, den es nicht mehr
gibt, und für `getExtended` eine neue Funktion — kein Aufräumen. Behalten wäre das
gefährlichste gewesen: Ein Recht, das der Server veröffentlicht und nicht durchsetzt, sieht wie
eine Zusicherung aus. Wer `export: false` liest und den Knopf ausblendet, hält sich für
abgesichert; wer die API direkt ruft, ist davon unberührt.

**Die Spalten `pim_permission.export` und `pim_permission.extended` bleiben.** In einem
Bestandsprojekt stehen dort möglicherweise Werte, und Daten wegzuwerfen ist die nicht umkehrbare
Richtung. Lesbar sind sie weiterhin über `Areanet\PIM\Entity\Permission` — nur ohne Behauptung
des Frameworks darüber, was sie bewirken. Sie bewirken nichts.

`Areanet\PIM\Classes\Permission::canExport()` und `::getExtended()` sind mit entfallen. Ein
Projekt, das sie aufruft, bekommt einen Fehler — laut, nicht still.

**Der Schema-Hash ändert sich dadurch.** Ein Client, der ihn zwischenspeichert, lädt das Schema
einmal neu; genau dafür gibt es ihn.

*Was zu tun ist:* Die Auswertung der beiden Schlüssel entfernen. Wer sie braucht, liest die
Spalten im eigenen Projekt und setzt sie an einem eigenen Endpunkt durch — dort, wo es einen
gibt.

### Der Stufen-Kollaps von `canExport` wurde nicht repariert
**Seit `000-000-0012` (2026-09-09).**

Zur Einordnung, falls jemand den Export zurückholt: `canExport` lautete
`return ($permission->getExport() == 2)` — nur `ALL` galt als erlaubt. Weil die Konstanten
nicht aufsteigend geordnet sind (`NONE` 0, `OWN` 1, `ALL` 2, `GROUP` 3), ergab ausgerechnet
`GROUP` ein `false`: Wer „mehr als ALL" meinte, sperrte sich aus.

Repariert wurde das **nicht**, sondern mit dem Feld entfernt. Jede Reparatur hätte entschieden,
welche Benutzer künftig dürfen — für ein Recht, das niemand prüft. Wer einen Export baut,
entscheidet das für einen Endpunkt, den es dann gibt.

### Sieben PIM-Entities setzen `excludeFromSync`
**Seit `000-000-0013` (2026-09-09).**

`Api::getAll()`, `getCount()` und `getDeleted()` trugen jede eine fest verdrahtete
Ausschlussliste im Code — dieselben Namen, dreimal geschrieben, in keiner Annotation und in
keiner Konfiguration. Ein Projekt konnte nicht erkennen, warum eine Entity nie synchronisiert
wird.

`PIM\Folder`, `PIM\Group`, `PIM\Log`, `PIM\Nav`, `PIM\NavItem`, `PIM\Permission` und
`PIM\ThumbnailSetting` tragen jetzt `@PIM\Config(excludeFromSync=true)`, jede mit ihrer
Begründung an der Klasse. Zwei Einträge der alten Liste waren tot: `PIM\Token` steht nicht im
Schema, `PIM\PushToken` gibt es im Baum nicht.

**Am ausgeschlossenen Bestand ändert sich nichts** — dieselben Entities wie vorher. Was sich
ändert: `settings.excludeFromSync` steht für diese sieben jetzt auf `true` statt auf `false`,
und der Schema-Hash ändert sich dadurch.

*Was zu tun ist:* Nichts. Wer eine eigene Entity aus der Synchronisation nehmen will, setzt das
Flag jetzt selbst, statt sich zu fragen, warum es nicht wirkt.

### `/api/deleted` prüft `excludeFromSync`
**Seit `000-000-0013` (2026-09-09).**

`getDeleted()` hat das Feld **nie** geprüft, es hatte nur seine eigene Liste. Eine Entity, die
aus dem Bestand ausgeschlossen ist, aber ihre Löschungen meldet, ergibt keinen Sinn: Ein
Sync-Client bekäme Löschmeldungen zu Objekten, die er nie erhalten hat.

**Betroffen ist ein Projekt, das `excludeFromSync` auf einer eigenen Entity gesetzt hat** und
sich darauf verlässt, deren Löschungen trotzdem zu bekommen. Das dürfte niemand sein — bis
`000-000-0007` wirkte das Feld ausschliesslich auf die Bestandsstatistik.

### `/api/deleted` behandelt die Grenzsekunde inklusiv
**Seit `000-000-0013` (2026-09-09).**

`pim_log.created` ist ein `datetime` mit Sekundenauflösung. Zwei Löschungen in derselben
Sekunde tragen denselben Zeitstempel. Der Filter lautete `created > ?`: Ein Sync-Client verlor
damit **jede** Löschung aus der Sekunde, deren Zeitstempel er sich gemerkt hatte. Sie ist nicht
grösser, also kam sie nie — und nichts wies je darauf hin.

Jetzt `created >= ?`. Die Grenzsekunde wird erneut geliefert. Eine Löschung doppelt zu melden
ist folgenlos, der Client löscht etwas, das schon weg ist; eine zu verlieren ist es nicht.
`getAll()` filtert seit jeher mit `modified >= ?` — die beiden Hälften derselben
Synchronisation lagen auf verschiedenen Seiten der Grenze.

**Betroffen ist ein Client, der jede gemeldete Löschung als neu behandelt** und daraus etwas
ableitet, das kein zweites Mal passieren darf.

*Was zu tun ist:* Löschmeldungen idempotent verarbeiten. Die eigentliche Lösung wäre eine
höhere Auflösung oder eine monoton steigende Sequenz; beides braucht eine Spalte und damit eine
Migration und gehört zum Kernel-Wechsel.

### `sortBy` und `sortOrder` sind Angaben für den Client
**Klargestellt mit `000-000-0013` (2026-09-09) — keine Verhaltensänderung.**

`an_project/docs/pim-annotationen-migration.md` führte beide unter „Sortierung der
API-Antworten". Das trifft nicht zu: `Api::getList()` wertet sie nicht aus. Sortiert wird allein
nach dem `order`-Parameter des Requests; fehlt er, bleibt es bei `ORDER BY id DESC`. Nur
`sortRestrictTo` hat einen echten Leser.

**Angewandt werden sie ausdrücklich nicht.** `id` ist eindeutig, `created` — die Vorgabe für
jede Entity ohne eigene Angabe — ist es nicht. Sie anzuwenden hätte eine stabile
Blätterreihenfolge gegen eine unstabile getauscht, still, für jeden Client, der kein `order`
schickt.

*Was zu tun ist:* Wer die deklarierte Reihenfolge will, liest sie aus dem Schema und schickt sie
als `order` mit.

### `/api/list` antwortet auf eine leere Menge mit 200
**Seit `000-000-0014` (2026-09-09).**

Lieferte die Abfrage keine Treffer, antwortete der Endpunkt mit `404 {"message":"Not found"}` —
einer achten Antwortform, die mit keiner der sieben anderen etwas zu tun hatte. Für einen
Client waren **„keine Treffer" und „diese Route gibt es nicht" nicht unterscheidbar**: gleicher
Statuscode, gleicher Rumpf.

Jetzt antwortet eine bekannte Entity ohne Treffer mit `200`, `data: []` und `totalItems: 0` —
so wie eine Abfrage mit einem Treffer mit `200` und einer Liste mit einem Eintrag antwortet.
Eine **unbekannte** Entity bleibt ein `404`, jetzt aber unterscheidbar, mit
`contentfly_general_unknown_entity` im Rumpf.

**Betroffen ist ein Client, der den 404 als „leer" auswertet.** Er bekommt jetzt `200` und muss
`data` prüfen — was er ohnehin tut, sobald es Treffer gibt.

*Was zu tun ist:* Die Sonderbehandlung des 404 entfernen. Wer weiterhin unterscheiden will,
liest `message`: Bei einer unbekannten Entity steht dort `contentfly_general_unknown_entity`.

### Jede Erfolgsantwort unter `/api/*` hat dieselbe Form: `data`, `errors`, `meta`
**Seit `011-001-0002` (2026-09-16).** Angekündigt mit `000-000-0014`.

**Das ist die grösste Formänderung des Release.** Siebzehn Antwortstellen unter `/api/*` trugen
sieben verschiedene Formen: hier `ts` und `data`, dort `lastModified` und `data`, bei `insert` die
`id` **neben** dem Objekt, bei `list` `totalItems` dazwischen, bei `schema` das Schema auf der
obersten Ebene. Ein Client brauchte einen Leser je Endpunkt. Jetzt gibt es einen:

```json
{"data": …, "errors": null, "meta": {"ts": "…", "version": "…", "projectVersion": "…", "hash": "…"}}
```

`data` ist die Nutzlast des Endpunkts, `errors` ist im Erfolgsfall `null` (nicht abwesend), und
alles, was die Antwort **beschreibt** statt sie zu sein, steht in `meta`.

| Endpunkt | vorher | jetzt |
|---|---|---|
| `POST /api/all` | `lastModified`, `data` | `data`; `meta.lastModified` |
| `GET /api/config` | `devmode`, `version`, `hash` | `data = {devmode}` |
| `POST /api/count` | `ts`, `data` | `data` |
| `POST /api/delete` | `ts`, `id` | `data = {id}` |
| `POST /api/deleted` | `ts`, `data` | `data` |
| `POST /api/insert` | `ts`, `id`, `data` | `data` — das Objekt trägt die `id` |
| `POST /api/list` | `data`, `totalItems`, ggf. `itemsPerPage`, `lastModified` | `data`; diese drei in `meta` |
| `POST /api/list` (`count: true`) | `data` = Anzahl | `data` = Anzahl |
| `POST /api/multiupdate` | `ts`, `data` | `data` |
| `POST /api/query` | `ts`, `params`, `data` | `data`; `meta.params` |
| `POST /api/replace` | erbt von `insert`/`update` | erbt mit |
| `GET /api/schema` | Schema auf oberster Ebene, dazu `permissions`, `i18nPermissions`, `frontend`, `devmode` | `data` = Schema; die vier anderen in `meta` |
| `POST /api/single` | `ts`, `data` | `data` |
| `POST /api/tree`, `/api/tree2` | `ts`, `data` | `data` |
| `POST /api/translations` | `data` | `data` |
| `POST /api/update` | `ts`, `id` | `data = {id}` |

**Drei Änderungen sind mehr als ein Umhängen von Schlüsseln:**

- **`/api/insert` liefert die `id` nur noch einmal.** Sie stand doppelt in der Antwort — einmal
  oben, einmal im Objekt. Die Nutzlast ist jetzt das Objekt, und es trägt seine `id`.
- **`/api/all` antwortet auf eine leere Menge mit `200` statt `204`.** Eine `204` hat keinen Rumpf
  und damit auch keinen Envelope — genau der Fall, für den die Vereinheitlichung da ist.
  `/api/list` hat dasselbe mit `000-000-0014` abgelegt.
- **`meta` trägt zwei Versionen statt einer.** `version` ist die des Frameworks,
  `projectVersion` die des Projekts. Bisher **verlor `/api/config` die Projektversion**: Die
  Aktion übergab `APP_VERSION.'/'.CUSTOM_VERSION`, und `renderResponse()` überschrieb den
  Schlüssel danach mit `APP_VERSION` allein. Kein Client hat sie je gesehen.

**Betroffen ist jeder Client**, der eine dieser Antworten auswertet.

*Was zu tun ist:* Eine Leserfunktion für den Envelope schreiben und die Auswertung darauf
umstellen. `body.data` ist bei `single`, `list`, `count`, `deleted`, `tree`, `tree2`,
`translations`, `query`, `multiupdate` und `schema` dasselbe wie vorher — dort genügt es, die
Zusatzschlüssel aus `meta` zu lesen. Wirklich umzubauen sind `insert` (`body.id` →
`body.data.id`), `delete`/`update` (`body.id` → `body.data.id`), `config` (`body.devmode` →
`body.data.devmode`) und `schema` (`body.permissions` → `body.meta.permissions`).

**`/auth/*`, `/file/upload`, `/file/overwrite` und `/system/do` sind mit `011-001-0004` nachgezogen**
— siehe den übernächsten Eintrag. Die vollständige Tabelle über alle Endpunkte steht in
`an_project/docs/api-envelope.md`.

### Jede Fehlerantwort hat dieselbe Form wie eine Erfolgsantwort
**Seit `011-001-0003` (2026-09-16).**

Die Fehlerform war die achte neben den sieben Erfolgsformen — und sie war in sich nicht einheitlich:

```json
{"message":"contentfly_file_too_large","type":"…\\ContentflyException","message_value":1048576,"status":413}
```

**Welche Schlüssel ankamen, hing von der Ausnahmeklasse ab.** `message_value` gab es nur bei einer
`ContentflyException`, `message_entity` und `message_lang` nur bei einer `ContentflyI18NException`,
bei einem PHP-Fehler keines von beiden. Ein Client musste also die Ausnahmen des Frameworks kennen,
um einen Fehler überhaupt lesen zu können.

Jetzt:

```json
{"data": null,
 "errors": [{"code": "contentfly_file_too_large",
             "detail": "contentfly_file_too_large",
             "type": "Areanet\\PIM\\Classes\\Exceptions\\ContentflyException",
             "context": {"value": 1048576}}],
 "meta": {"ts": "…", "version": "…", "projectVersion": "…", "hash": "…"}}
```

**Vier feste Schlüssel je Eintrag, immer vorhanden:**

| Feld | Bedeutung | vorher |
|---|---|---|
| `code` | Worauf ein Client **verzweigt**: der `Messages`-Schlüssel einer Contentfly-Ausnahme — und `null` bei allem anderen | `message`, sofern es ein Schlüssel war |
| `detail` | Für einen Menschen: `getMessage()`. Bei einer Contentfly-Ausnahme derselbe String wie `code`, weil die Meldung dort der Schlüssel **ist** | `message` |
| `type` | Die Ausnahmeklasse | `type`, unverändert |
| `context` | Was die Ausnahme darüber hinaus weiss: `{"value": …}` bzw. `{"entity": …, "lang": …}`, sonst `null` | `message_value` bzw. `message_entity`/`message_lang` |

**`code: null` ist keine Lücke, sondern die Aussage:** Das ist ein unvorhergesehener Serverfehler,
hier gibt es nichts Stabiles zum Verzweigen. Wer solche Fälle doch unterscheiden muss, liest `type`
— und trägt das Risiko, dass eine Klasse umbenannt wird.

**`errors` ist eine Liste, obwohl heute immer genau ein Eintrag darin steht.** Der Handler sieht
eine Ausnahme. Die Liste ist der Platz für die Feldvalidierung, die mehrere Fehler auf einmal melden
können muss, ohne die Form noch einmal zu ändern.

**Der Stacktrace steht im Debug-Modus unter `meta.debug`**, nicht mehr auf oberster Ebene: Er
beschreibt diese Antwort, nicht den Fehler — dieselbe Ebene wie `ts` und `hash`. Wie bisher nur bei
`APP_DEBUG`.

**Auch der Hinweis „nicht installiert" ist jetzt ein Envelope** (`503`) und trägt den neuen
`Messages`-Schlüssel `contentfly_general_not_installed`. Bisher war er ein blosses
`{"message": …}` — die neunte Form.

**Betroffen ist jeder Client**, der Fehlerantworten auswertet.

*Was zu tun ist:* `body.message` → `body.errors[0].code` (zum Verzweigen) bzw.
`body.errors[0].detail` (zum Anzeigen), `body.message_value` → `body.errors[0].context.value`,
`body.message_entity`/`body.message_lang` → `body.errors[0].context.entity`/`.lang`,
`body.debug` → `body.meta.debug`. `body.status` ersatzlos — der Statuscode steht in der
HTTP-Antwort.

### `/auth/*`, `/file/*` und `/system/do` antworten wie alle anderen
**Seit `011-001-0004` (2026-09-16).** Damit ist der Envelope vollständig.

Diese drei Gruppen hatten je eine eigene Form, und keine davon ging durch den Trichter der API.

| Endpunkt | vorher | jetzt |
|---|---|---|
| `POST /auth/login` | `message`, `token`, `user`, optional `data`, `refreshToken`, `expiresIn`, `schema`, `hash` | `data` = `{token, user, …}` |
| `POST /auth/refresh` | `message`, `token`, `refreshToken`, `expiresIn` | `data` = `{token, refreshToken, expiresIn}` |
| `GET /auth/logout` | `message` | `data` = `null` |
| `POST /file/upload` | `message`, `data` | `data` = das Dateiobjekt |
| `POST /file/overwrite` | `message`, `sourceId`, `destId` | `data` = `{sourceId, destId}` |
| `POST /system/do` | `method`, `datetime`, `message` | `data` = `{method, message}` |

**`message` entfällt überall.** „Login successful", „File uploaded", „File overwritten",
„Logout successful" — Sätze, die ein Client nur wörtlich vergleichen konnte, neben einem `200`,
das dasselbe schon sagte.

**Vier Stellen sind mehr als ein Umhängen:**

- **`/auth/login`: aus `data` wird `tempData`.** Was ein Projekt dem Client bei der Anmeldung
  mitgibt, hiess auf oberster Ebene `data`. Unter dem Envelope wäre das `body.data.data` gewesen;
  jetzt heisst es wie das Entity-Feld, aus dem es kommt.
- **`/auth/login?withSchema` antwortet wie `/api/schema`.** Beide lieferten bisher den ganzen
  `getExtendedSchema()`-Block am Stück. `/api/schema` ist mit `011-001-0002` aufgeteilt worden —
  `data` ist die Entity-Karte, Rechte und Rest stehen in `meta`. Hier gilt jetzt dasselbe:
  `data.schema` ist die Entity-Karte, `meta.permissions`, `meta.i18nPermissions`, `meta.devmode`
  und `meta.frontend` stehen daneben. Sonst bräuchte ein Client zwei Leser für dasselbe Schema, je
  nachdem, woher es kam. Das mitgelieferte `hash` entfällt: `meta.hash` trägt es in **jeder**
  Antwort.
- **`/system/do`: `datetime` entfällt ersatzlos.** `meta.ts` ist derselbe Wert im selben Format —
  und steht in jeder Antwort, nicht nur in dieser.
- **Die Ablehnungen der Anmeldung tragen endlich einen Code.** `401`, `429` und der `500` bei
  fehlender JWT-Konfiguration entstehen im `AuthController` selbst und erreichten den Fehlerhandler
  nie; sie waren die letzte Gruppe mit eigenem Rumpf. Neu sind dafür vier `Messages`-Schlüssel:
  `contentfly_general_invalid_credentials`, `contentfly_general_invalid_refresh_token`,
  `contentfly_general_too_many_attempts` und `contentfly_general_jwt_not_configured`. Bisher musste
  ein Client den Satz erkennen, um „warte" von „falsches Passwort" zu unterscheiden — beides `4xx`.

  **`…_invalid_credentials` gilt bewusst für *jeden* `401` der Anmeldung** — unbekannter Benutzer,
  falsches Passwort, abgelehnter Provider. Die Antwort darf kein Orakel dafür sein, welche Konten
  existieren.

**Die letzte Antwort ausserhalb des Envelopes ist damit auch weg:** Eine `FileNotFoundException`
beantwortete der Fehlerhandler mit einem **Klartext-404** — `text/html`, im Rumpf nur die Meldung.
Das traf nicht nur die Dateiauslieferung, sondern auch `/file/overwrite`. Die Ausnahme erbt jetzt
von `ContentflyException`, geht den normalen Weg und trägt ihren `Messages`-Schlüssel als `code`.

*Was zu tun ist:* `body.token` → `body.data.token`, `body.user` → `body.data.user`,
`body.data` (Projektdaten der Anmeldung) → `body.data.tempData`, `body.message` (Dateiobjekt bzw.
Ergebnis von `/system/do`) → `body.data`. Auswertungen von `message`, `datetime` und dem `hash` der
Anmeldung entfernen.

### Eine `unique`-Verletzung antwortet mit 409 statt 500
**Seit `009-003-0002` (2026-09-09).**

`Api::doUpdate()` warf bei einer Verletzung von `@PIM\Config(unique=true)`
`Messages::contentfly_general_record_already_exists`. **Diese Konstante gibt es nicht** — sie
heisst `…_ressource_…`. Die Zeile war kein Fehlerbericht, sondern ein Fatal; über HTTP
gemessen kam `{"message":"Undefined constant …","type":"Error","status":500}` an.

Jetzt antwortet der Endpunkt mit **409** und `contentfly_status_ressource_already_exists`, wie
die drei anderen Fälle in derselben Methode es immer schon taten.

*Was zu tun ist:* Auf 409 prüfen statt auf 500. Ein Client, der den 500 als „gibt es schon"
gelesen hat, liest ihn jetzt falsch.


### `/api/tree` und `/api/tree2` prüfen das Leserecht
**Seit `000-000-0061` (2026-09-18), ausgeliefert mit `v2.1.0`.**

Beide Routen prüften nur, **ob** jemand angemeldet ist — nicht, was er lesen darf. Gemessen: Ein
Benutzer ohne jedes Leserecht bekam bei `/api/list` 403, bei beiden Tree-Routen 200 **mit** den
fremden Daten. Jetzt gilt dieselbe Regel wie bei `/api/list`: ohne Leserecht **403**, bei `OWN`
und `GROUP` nur die erreichbaren Knoten. **Ein Knoten unter einem unsichtbaren Elternknoten ist
ebenfalls unsichtbar** — bei beiden Routen gleich.

*Was zu tun ist:* Jede Gruppe, deren Clients einen Baum lesen, braucht eine `Permission`-Zeile mit
`readable` für diese Entity (z. B. `PIM\Folder`). **Das ist die Stelle, an der ein Update still
etwas wegnimmt:** Wer bisher ohne Recht gelesen hat, bekommt jetzt 403 oder einen kleineren Baum.

### `/api/deleted` meldet nur noch Entities, die der Benutzer lesen darf
**Seit `000-000-0061` (2026-09-18), ausgeliefert mit `v2.1.0`.**

Das Löschprotokoll lieferte die gelöschten Ids **aller** Entities. Jetzt fehlen die Entities ohne
Leserecht. Verengt wird auf Entity-Ebene, nicht auf Eigentümerschaft — der Datensatz ist gelöscht,
die Log-Zeile ist alles, was von ihm übrig ist.

*Was zu tun ist:* Nichts, solange ein Sync-Client nur Entities synchronisiert, die er auch lesen
darf — und nur solche kann er über `/api/all` überhaupt bekommen.

### Unbekannte Feldnamen in `where`, `order` und `groupBy` antworten mit 400
**Seit `000-000-0063` (2026-09-18), ausgeliefert mit `v2.1.0`.**

Feldnamen aus dem Request gingen **ungeprüft als Text** in die Abfrage — DQL-Injection. Jetzt
müssen sie ein Property der Entity benennen:

| Stelle | unbekannter Name |
|---|---|
| `where` in `/api/single` | **400** `contentfly_general_unknown_property` |
| `order` in `/api/list` | **400** `contentfly_general_unknown_property`; die Richtung nur `ASC`/`DESC` (Gross-/Kleinschreibung egal), sonst **400** `contentfly_general_invalid_sort_direction` |
| `groupBy` in `/api/list` | **400** `contentfly_general_unknown_property` |
| `properties` in `/api/tree` | wird **verworfen**, wie es `/api/list` mit `properties` immer tat |

Vorher endeten dieselben Aufrufe mit **500**.

*Was zu tun ist:* Nichts für einen korrekten Client. Ein Tippfehler in einem Feldnamen, der bisher
als 500 durchging, zeigt sich jetzt als 400 mit dem Namen in `errors[0].context.value`.

### `/file/overwrite` prüft die Eigentümerschaft von Ziel und Quelle
**Seit `000-000-0060` (2026-09-18), ausgeliefert mit `v2.1.0`.**

Das Schreibrecht auf `PIM\File` genügte: Mit `writable = OWN` überschrieb ein Benutzer jede fremde
Datei gleichen Namens — und liess eine fremde Quelle verschwinden, weil sie verschoben und nicht
kopiert wird. Jetzt müssen **beide** Dateien für den Benutzer schreibbar sein, nach der Regel von
`/api/update`; sonst **403**.

*Was zu tun ist:* Nichts, solange Clients nur eigene oder freigegebene Dateien überschreiben.

### Übersetzungen: wieder anlegbar, und auf Eigentümerschaft verengt
**Seit `000-000-0059` und `000-000-0064` (2026-09-18), ausgeliefert mit `v2.1.0`.**

**Eine Übersetzung liess sich in `v2.0.0` nicht anlegen.** Jeder Insert mit der `id` eines
bestehenden Datensatzes scheiterte mit `unknown_property …::id`: `id` fehlte im Schema **jeder**
übersetzbaren Entity (`BaseI18n`, `BaseI18nSortable`, `BaseI18nTree`) — eine Folge des Umbaus auf
ORM 3. `/api/schema` führt `id` für diese Entities jetzt wieder.

Dabei gilt jetzt, was vorher nur hätte gelten sollen: Die Übersetzung eines Datensatzes darf nur
anlegen, wer **diesen** Datensatz schreiben darf (`OWN`/`GROUP` wie bei `/api/update`), sonst
**403**. Und `/api/translations` zählt nur noch Datensätze, die der Benutzer erreicht, wie
`/api/count`.

**Ohne konfiguriertes `APP_LANGUAGES`** gibt es keine Hauptsprache; eine Übersetzung erbt dann
keine `i18n_universal`-Felder und trägt genau, was gesendet wurde. Die Warnung
`Undefined array key 0`, die dabei vorher entstand, ist weg.

*Was zu tun ist:* Ein Projekt mit übersetzbaren Entities kann Übersetzungen wieder über die API
anlegen. Wer das in `v2.0.0` anders gelöst hat, prüft, ob der Umweg noch nötig ist.

### Beziehungsfelder: `onejoin` in Listen, `multifile` und `permissions` mit den richtigen Ids
**Seit `000-000-0067` (2026-09-21), ausgeliefert mit `v2.2.0`.**

Vier Fehler in Feldtypen, die bis dahin kein Test erreichte:

| Aufruf | vorher | jetzt |
|---|---|---|
| `/api/list` auf eine Entity mit `OneToOne`-Feld | das Feld ist **immer `null`**, `/api/single` liefert es | das Feld enthält den verknüpften Datensatz, mit `flatten` `{"id": …}` |
| `/api/list` mit `properties` auf ein `multifile`-Feld | je Datei ein leeres Objekt `{}` | je Datei der Datensatz aus `PIM\File` |
| `permissions` von `PIM\Group` mit `flatten` bzw. `properties` | je Rechtezeile die **Id der Gruppe** | die Id der Rechtezeile |
| `/api/list` auf `PIM\Group` mit `properties: ["permissions"]` | **500** | 200 |

*Was zu tun ist:* Nichts für einen korrekten Client. Wer die Lücken umgangen hat — etwa jedes
`OneToOne`-Feld über `/api/single` nachgeladen —, kann den Umweg streichen.

### Bild-Uploads: kaputte und getarnte Bilder antworten 415 statt 500, GIF funktioniert wieder
**Seit `000-000-0068` (2026-09-21), ausgeliefert mit `v2.2.0`.**

Was ein Bildprozessor verarbeiten würde (`image/jpeg`, `image/png`, `image/gif`), prüft der Upload
jetzt am Dateikopf, **bevor** etwas gespeichert wird:

| Upload | vorher | jetzt |
|---|---|---|
| Kopf nicht als Bild lesbar (z. B. Text als `image/jpeg`) | **500**, Datensatz und Datei blieben liegen | **415** `contentfly_file_invalid_type`, nichts gespeichert |
| anderer Typ als gemeldet (PNG als `image/jpeg`) | **500** | **415** |
| mehr Pixel als `FILE_IMAGE_MAX_PIXELS` | GD versucht, den Speicher zu belegen | **413** `contentfly_file_too_large` |
| Kopf lesbar, Bilddaten kaputt | **500** | **415**, der neue Datensatz samt Verzeichnis wird entfernt |
| jedes **GIF** | **500** (`imagegif()` nimmt seit PHP 8 keine Qualität) | 200 mit Vorschaubildern |

Dateien ohne Bildprozessor (`.txt`, `.pdf` …) sind nicht betroffen.

*Was zu tun ist:* Ein Client, der auf 500 geprüft hat, prüft auf 415 bzw. 413. **Offen bleibt ein
Fall:** Beim erneuten Upload auf eine bestehende Id (`id` im Request) wird eine Datei mit lesbarem
Kopf und kaputten Daten nicht zurückgerollt — der Kopf-Check greift auch dort, nur dieser Rest nicht.


### Rechtestufe `GROUP`: `/api/count` und `/api/query` antworten wieder
**Seit `000-000-0072` (2026-09-21), ausgeliefert mit `v2.2.0`.**

Für jeden Benutzer, dessen Gruppe eine Entity mit `readable = GROUP` liest, endeten `/api/count` und
`/api/query` mit **500** (`SQLSTATE[42000] … 1064`): Beide bauen rohes SQL und schrieben `groups`
ohne Backticks — in MySQL 8 ein reserviertes Wort. Jetzt 200, verengt auf eigene und der Gruppe
freigegebene Datensätze, wie `/api/list`.

*Was zu tun ist:* Nichts. Wer für `GROUP`-Benutzer auf `/api/list` ausgewichen ist, kann zurück.

### Unerwartete Fehler zeigen ihren Text nicht mehr — `/system/do` antwortet mit 4xx
**Seit `000-000-0073` (2026-09-21), ausgeliefert mit `v2.2.0`.**

**Ohne Debug** antwortet ein unvorhergesehener Serverfehler — jede Ausnahme, die weder eine
`ContentflyException` noch eine HTTP-Ausnahme ist — jetzt mit festem Inhalt:

```json
{"code": null, "detail": "contentfly_general_internal_error", "type": "InternalServerError", "context": null}
```

Vorher standen dort die Meldung und die Klasse der Ausnahme — gemessen: bei einem DBAL-Fehler die
MySQL-Meldung samt Ausschnitt der Abfrage, bei einem `TypeError` Methode, Signatur und Server-Pfad.
Der volle Text steht jetzt im Server-Log (`Contentfly: unexpected …`). **Mit `APP_DEBUG` bleibt alles
wie bisher.**

`/system/do` warf für Fehler des Aufrufers eine nackte `\Exception` und antwortete mit **500**. Die
Sätze wären mit der Regel oben verschwunden; stattdessen sind es jetzt Fehler mit Schlüssel:

| Fall | vorher | jetzt |
|---|---|---|
| unbekannte oder nicht erlaubte Methode | 500 „Method … is not available." | **400** `contentfly_general_invalid_params`, Methode in `context.value` |
| `addToken` ohne `referrer`/`user` | 500 | **400** `contentfly_general_missing_params` |
| `addToken` mit unbekanntem Benutzer | 500 | **404** `contentfly_general_not_found` |
| `addToken` mit schon vergebenem Token | 500 | **409** `contentfly_general_ressource_already_exists` |
| `addToken` mit zu schwachem Token | 400 mit Satz in `detail` | **400** `contentfly_general_token_too_weak`, die Regel in `context.value` |
| `deleteToken` mit unbekannter Id | 500 „Invalid token" | **404** `contentfly_general_not_found` |

*Was zu tun ist:* Ein Client, der den Text in `detail` angezeigt oder ausgewertet hat, verzweigt auf
`code` bzw. zeigt bei `contentfly_general_internal_error` eine allgemeine Meldung. Wer Fehler
analysiert, liest das Server-Log statt der Antwort.

### `/api/insert` und `/api/update`: Datenbankfehler ohne ihren Text
**Seit `000-000-0079` (2026-09-22), ausgeliefert mit `v2.2.0`.**

Die Regel aus `0073` griff hier nicht: `doInsert()` und `doUpdate()` verpackten jeden Fehler beim
Speichern in eine `ContentflyException` mit dem Ausnahmetext als Meldung, und die gilt als erwartet.
**Ohne Debug** gilt jetzt auch hier:

| Fall | vorher | jetzt |
|---|---|---|
| Datenbankfehler beim Speichern (z. B. Wert zu lang, Pflichtspalte leer) | 500, Text von Doctrine/MySQL in `code` **und** `detail` | 500, `code` = `null`, `detail` = `contentfly_general_internal_error`, `type` = `InternalServerError` |
| Unique-Verletzung beim Anlegen | `context.value` endete mit der MySQL-Meldung (`SQLSTATE[23000] … Duplicate entry …`) | ohne diesen Anhang |

Der volle Text steht im Server-Log (`Contentfly: unexpected …` bzw. `Contentfly: unique violation on
<Entity>: …`). **Mit `APP_DEBUG` bleibt alles wie bisher.**

*Was zu tun ist:* Ein Client, der auf den Text in `code` oder `detail` verzweigt hat, verzweigt auf
`contentfly_general_internal_error` bzw. zeigt eine allgemeine Meldung.

### `/api/insert`: jede Unique-Verletzung antwortet mit 409
**Seit `000-000-0080` (2026-09-22), ausgeliefert mit `v2.2.0`.**

Nur Felder mit `unique` im Schema werden vor dem Speichern geprüft. Was erst die Datenbank ablehnte,
lief in einen Zweig mit zwei Fehlern:

| Fall | vorher | jetzt |
|---|---|---|
| Schlüssel, den nur die Datenbank kennt (`UniqueConstraint` auf der Tabelle) | **500** `contentfly_general_unknown_perror`, `context.value` nannte ein falsches Feld | **409** `contentfly_general_ressource_already_exists`, `context.value` = die Entity |
| Kollision auf einem Schema-Feld, die erst die Datenbank bemerkt (zwei gleichzeitige Anfragen) | **200 mit dem bereits vorhandenen Datensatz**, als sei er neu angelegt | **409** wie oben |
| dasselbe bei `PIM\User` | 500 `contentfly_general_user_already_exists` | **409**, wie in `/api/update` |

Welcher Schlüssel kollidierte, steht nur im Text von MySQL und damit seit `0079` im Server-Log.

*Was zu tun ist:* Ein Client, der auf 500 geprüft hat, prüft auf 409. Wer nach einem 200 die Id des
angeblich neuen Datensatzes weiterverwendet hat, bekommt im Kollisionsfall jetzt einen Fehler statt
eines fremden Datensatzes — das ist die Absicht.

### `/api/single`: `loadJoinedLang` entfällt und wird mit 400 abgelehnt
**Seit `000-000-0081` (2026-09-22), ausgeliefert mit `v2.2.0`.**

`loadJoinedLang` sollte die Joins auf übersetzbare Datensätze in einer anderen Sprache lesen. Ein
solcher Verweis hat aber zwei Schlüsselspalten, und `<feld>_lang` legt die Sprache schon fest — in
jeder anderen Sprache kam der Join als **`null`**, obwohl das Ziel existierte. Der Modus
„neu übersetzen" von `compareToLang`, der darauf aufbaute, meldete deshalb **jedes Mal** fehlende
Übersetzungen (`contentfly_i18n_missing_translations`).

| Aufruf | vorher | jetzt |
|---|---|---|
| `/api/single` mit `loadJoinedLang` | 200, Joins in anderer Sprache `null` | **400** `contentfly_general_invalid_params`, `context.value` = `loadJoinedLang` |
| `/api/single` mit `compareToLang` **und** `loadJoinedLang` | Fehler „fehlende Übersetzung" | **400** wie oben |
| `/api/single` mit `compareToLang` allein | unverändert | unverändert |

Ein leerer Wert gilt als nicht gesendet. Einziger bekannter Nutzer war die mit Epic `012` gelöschte
PIM-Oberfläche.

*Was zu tun ist:* Den Parameter weglassen. Joins kommen in der Sprache des Requests (`lang`); wer ein
Ziel in einer anderen Sprache braucht, liest es mit `/api/single` in dieser Sprache nach.

### Ein nicht lesbares `lastModified` antwortet mit 400
**Seit `000-000-0083` (2026-09-22), ausgeliefert mit `v2.2.0`.**

| Endpunkt | vorher | jetzt |
|---|---|---|
| `/api/list` | **500** (MySQL lehnte den Wert ab) | **400** `contentfly_general_invalid_date` |
| `/api/count`, `/api/deleted` — auch je Entity | **500** | **400** |
| `/api/all` | **200 mit allem**, als wäre kein Zeitpunkt angegeben | **400** |

`context.value` nennt `lastModified`. Eine Zahl statt einer Zeichenkette gilt ebenfalls als nicht
lesbar; bei `/api/count` war sie bisher wirkungslos (MySQL verglich sie mit der Ziffernfolge des
Datums). Ein leerer Wert heisst weiterhin: kein Zeitpunkt.

*Was zu tun ist:* Nichts für einen Client, der den Wert aus `meta.lastModified` zurückschickt. Wer
bei `/api/all` einen falschen Wert geschickt und sich auf den vollen Bestand verlassen hat, lässt
`lastModified` weg.

## Paketgrenze (Epic `007`)

Epic `007` macht das Framework zu einem Composer-Paket. Was hier steht, trifft jedes Projekt —
auch eines, das die Vorlage unverändert übernommen hat, denn `index.php` gehört ihm.

### `ROOT_DIR` gibt es nicht mehr — der Einstiegspunkt setzt `CONTENTFLY_PROJECT_DIR`
**Seit `007-001-0002` (2026-09-11).**

`lib/contentfly/bootstrap.php` definierte die Konstante `ROOT_DIR` und rechnete sie aus der
eigenen Lage: `__DIR__ . '/../..'`. Das ist der Grund, warum das Framework bisher nicht in
`vendor/` liegen konnte — dort zeigt derselbe Ausdruck nach `vendor/areanet/`, nicht ins
Projekt.

**Und es ging leise schief.** Der gerechnete Pfad existiert dann nicht, aber es *gibt* ihn.
Nachgemessen mit dem alten Stand, das Framework unter `vendor/areanet/contentfly/` abgelegt:

```
Failed opening required '…/vendor/areanet/contentfly/lib/contentfly/../../custom/config.php'
```

Eine Meldung über eine fehlende Datei. Dass die *Wurzel* falsch ist, steht dort nicht.

*Was zu tun ist:* Im Einstiegspunkt das Projektverzeichnis benennen, **bevor** der Bootstrap
eingebunden wird:

```php
// index.php
define('CONTENTFLY_PROJECT_DIR', __DIR__);
require_once __DIR__.'/lib/contentfly/bootstrap-web.php';
```

Dasselbe in `bin/console.php` und `bin/cli-config.php` mit `dirname(__DIR__)`. Fehlt die
Konstante, endet der Start mit einer Meldung, die genau das sagt — statt mit einer über eine
Datei.

*Wenn Projektcode `ROOT_DIR` benutzt:* Es gibt zwei Nachfolger, und der Unterschied ist neu.

| Zweck | vorher | nachher |
|---|---|---|
| Verzeichnis des **Projekts** | `ROOT_DIR` | `Areanet\PIM\Classes\Kernel\Paths::project()` |
| `custom/`, `data/`, `plugins/` darunter | `ROOT_DIR.'/data'` | `Paths::data()`, `Paths::custom()`, `Paths::plugins()` |
| Verzeichnis des **Frameworks** | `ROOT_DIR` — dasselbe | `Paths::package()` |

**Die letzte Zeile ist die eigentliche Änderung.** Projekt und Framework waren dieselbe
Konstante, weil sie dasselbe Verzeichnis waren. Sobald das Framework als Paket kommt, sind es
zwei — und ein Aufruf, der bisher beides meinte, muss sich entscheiden.

In `custom/config.php` steht die Konstante ebenfalls, für den Fundort der `.env`. Die
ausgelieferte Vorlage ist nachgezogen; wer sie angepasst hat, zieht die eine Zeile mit.

### Der Einstiegspunkt lädt den Autoloader und ruft `Start`
**Seit `007-001-0003` (2026-09-11).**

`index.php` band bisher direkt `lib/contentfly/bootstrap-web.php` ein, und der Bootstrap lud
daraufhin selbst `vendor/autoload.php`. **Ein Paket wird vom Autoloader geladen — es lädt ihn
nicht.** Solange der Bootstrap die erste eingebundene Datei ist, kann der Frameworkcode nicht in
`vendor/` liegen: Um ihn zu finden, bräuchte man den Autoloader, den er selbst erst lädt.

*Was zu tun ist:* Die drei Einstiegspunkte auf dieselbe Form bringen.

```php
// index.php
require_once __DIR__ . '/vendor/autoload.php';
\Areanet\PIM\Classes\Kernel\Start::web(__DIR__);
```

```php
// bin/console.php und bin/cli-config.php
require_once dirname(__DIR__) . '/vendor/autoload.php';
$app = \Areanet\PIM\Classes\Kernel\Start::console(dirname(__DIR__));
```

**Damit steht in keinem Einstiegspunkt mehr ein Pfad in den Frameworkcode.** Das ist der Zweck:
Ob Contentfly unter `lib/` im Projekt liegt oder unter `vendor/areanet/contentfly/`, sieht die
Datei nicht mehr. `APPCMS_CONSOLE` setzt `Start::console()` selbst.

`lib/contentfly/bootstrap.php` direkt einzubinden bricht ab, mit einer Meldung, die auf `Start`
zeigt.

### `custom/vendor/` wird nicht mehr geladen — und ein Rest davon bricht den Start ab
**Seit `007-001-0003` (2026-09-11).**

Ein Projekt hatte zwei Composer-Bäume: den Root und `custom/vendor/`, geladen in dieser
Reihenfolge, mit der Zusicherung aus `006-004-0001`, dass bei einem gemeinsamen PSR-4-Präfix der
Root gewinnt. Mit dem Bibliothekspaket fällt die Grundlage weg — das Framework ist dann eine
Abhängigkeit **im** Baum des Projekts.

**Der Ersatz ist stärker als die Zusicherung, die er ablöst.** Die alte Regel hielt eine
Überschneidung *fern*, solange ein Test die Bedingung prüfte. Composer *verweigert* unvereinbare
Constraints beim Auflösen. Der Fall, an dem das jahrelang scheiterte — `psr/log` in 1.1.3 und
3.0.2 gleichzeitig im Prozess —, kann nicht mehr entstehen.

*Was zu tun ist:* Was `custom/composer.json` noch braucht, in das Manifest des Projekts
übernehmen, dann `custom/vendor/` und `custom/composer.json` entfernen.

**Liegen bleiben geht nicht.** Findet der Start ein `custom/vendor/autoload.php`, bricht er ab
und sagt warum. Das ist Absicht: Ein Baum, der daliegt und nicht mehr geladen wird, sieht aus
wie einer, der benutzt wird — die Folgemeldung handelte dann von einer fehlenden Klasse und
nicht von einem Baum, der nicht mehr gilt.

**Nicht betroffen sind Plugins.** Ein Plugin bringt weiterhin seinen eigenen `vendor/`-Baum mit,
und `Classes/Plugin::initComposer()` lädt ihn — entschieden mit `007-001-0004`, mit dem Preis
ausgesprochen in `an_project/docs/architecture.md`.

### Zwei Manifeste statt einem — `custom/composer.json` entfällt
**Seit `007-001-0004` (2026-09-11).**

Ein Manifest trug bisher drei Namensräume: `Areanet\PIM\`, `Custom\` und `Plugins\`.
**Genau diese Vermischung machte das Update unmöglich** — wer eine neue Frameworkversion wollte,
bekam sie nur, indem er den Baum überschrieb, in dem auch sein eigener Code lag.

| | vorher | nachher |
|---|---|---|
| Framework | `areanet/contentfly-framework`, `type: project` | `areanet/contentfly`, `type: library`, Manifest in `lib/contentfly/` |
| Projekt | dasselbe Manifest | eigenes Manifest, `require: areanet/contentfly` |
| Projektpakete | `custom/composer.json` | Manifest des Projekts |

*Was zu tun ist:*

1. Das Framework als Abhängigkeit aufnehmen: `areanet/contentfly` in das `require` des Projekts.
2. Was in `custom/composer.json` stand, in dasselbe `require` übernehmen. `custom/composer.json`
   und `custom/composer.lock` entfallen, ebenso die `.gitignore`-Ausnahme dafür.
3. Im Autoload des Projekts bleiben `Custom\` und `Plugins\`. **`Areanet\PIM\` gehört dort
   nicht mehr hinein** — sonst gäbe es zwei Wege zu denselben Klassen, und welcher gewinnt,
   entschiede die Ladereihenfolge.

#### Der Ablauf, Schritt für Schritt — von der Kopie auf das Paket

**Einmal durchgespielt mit `007-001-0005`**, in einem leeren Verzeichnis, gegen ein Projekt, das
keinen Frameworkcode enthält. Was hier steht, ist der gemessene Weg, nicht der geplante.

1. **`lib/` aus dem Projekt entfernen.** Der Frameworkcode kommt ab jetzt aus `vendor/`.
2. **Manifest anlegen** mit `areanet/contentfly` im `require`, den beiden Autoload-Präfixen
   `Custom\` und `Plugins\`, und dem, was vorher in `custom/composer.json` stand.
3. **Einstiegspunkte** auf die Form aus `007-001-0003` bringen: `index.php`, `bin/console.php`,
   `bin/cli-config.php`.
4. **`composer install`** — das Framework landet in `vendor/areanet/contentfly`.
5. **`php bin/console.php appcms:install`** wie bisher.

**Was mitgeht und was nicht.** Mit: `custom/`, `plugins/`, `data/`, die Einstiegspunkte, die
`.htaccess`. Nicht mit: `lib/`, `custom/vendor/`, `custom/composer.json`.

**Die Entities sind ein eigener Schritt und stehen woanders.** Der Rector-Lauf, der
`Entity/` von Annotationen auf Attribute bringt und die gestrichenen `@PIM`-Angaben entfernt,
ist in `an_project/docs/pim-annotationen-migration.md`, Abschnitt 7 beschrieben — mit dem
Aufruf, den Grenzen und dem, was von Hand bleibt. **Er steht dort und nicht hier**, weil die
Liste, aus der die Regel gebaut ist, dieselbe Datei ist; zwei Beschreibungen desselben Laufs
liefen auseinander.

**Das Verzeichnis `data/` muss beschreibbar sein und dem Projekt gehören** — nicht dem Paket.
Es trägt Cache, Dateien, Import und Temp; ein Update des Frameworks darf es nicht anfassen.

**Ein Paket wechselt dabei die Seite:** `vlucas/phpdotenv` stand im Framework-Manifest und steht
jetzt im Projekt. Es wird nur von `custom/config.php` benutzt — nachgezählt: 0 Treffer in `lib/`,
1 in `custom/`. Die Einordnungsregel in `tools/dependency-assignment.json` ist entsprechend
nachgezogen: Schritt 2 („benutzt die ausgelieferte Vorlage es?") führt jetzt zum Projekt und
nicht mehr zum Framework.

### `doctrine/persistence` darf 4 sein
**Seit `000-000-0031` (2026-09-15).**

Das Paketmanifest erlaubt jetzt `^3.4 || ^4`; der Lock steht auf **4.2.0**. Der Deckel auf `^3.4`
aus `007-001-0004` bestand nur, weil `Classes/Events/LoadMetadata` die in 4.2 deprecated Methode
`AbstractClassMetadataFactory::setMetadataFor()` rief. Der Aufruf war wirkungslos und ist
entfernt: An einer frischen Installation tragen ohne ihn dieselben 15 Tabellen den
`modified_index`, und der Schema-Dump ist byte-gleich.

**Ein Projekt merkt davon nichts am Schema und nichts an der API.** Es merkt es nur, wenn es
selbst etwas aus `doctrine/persistence` benutzt:

- Wer `setMetadataFor()` selbst ruft, bekommt unter 4.2 eine Deprecation.
- Composer kann beim nächsten `composer update` auf 4.x heben. Wer das nicht will, deckelt im
  eigenen Manifest.

*Was zu tun ist:* Nichts, solange das Projekt `doctrine/persistence` nicht direkt benutzt.
Sonst die eigenen Aufrufe gegen die Deprecation-Liste von Persistence 4 prüfen.

---

## Kernel (Epic `009`)

Der Kernel ist mit Epic `009` von Silex 2 auf Symfony 7.4 gewechselt. **Der Schnitt war so
angelegt, dass die Oberfläche, die ein Projekt benutzt, stehen bleibt** — was hier steht, ist
der Rest, der es nicht konnte.

### Was sich für ein Projekt **nicht** ändert

Diese Liste zuerst, weil eine Liste nur der Brüche sich liest, als bräche alles. Alles Folgende
ist unverändert und durch die Suite aus Epic `008` abgedeckt:

| Weiterhin | Anmerkung |
|---|---|
| `$app['schlüssel']` — lesen und setzen | Faule Factory mit `$app` als Argument, wie bei Pimple. Zugesichertes API — **seit `007-003` mit fester Schlüsselliste**, siehe `an_project/docs/dev-guide.md`. |
| `$app['routeManager']->mount(…)->post(…)->get(…)` | Der Weg, auf dem ein Projekt Routen registriert. Auch das `isSecure`-Flag. |
| `$app->before()`, `->after()`, `->error()` samt Priorität | Höhere Priorität zuerst, Vorgabe −8, bei gleicher Priorität die frühere Registrierung. |
| `$app->mount($prefix, $collection)` | |
| `$app->extend($id, $callable)` | Wirft weiterhin, wenn der Dienst schon ausgelesen wurde — siehe unten zum Ausnahmetyp. |
| `$app['dbs']`, `$app['db']`, `$app['orm.em']`, `$app['mailer']` | |
| `CustomCommand` und der `custom:`-Präfix | |
| `custom/app.php`, `custom/config.php`, `index.php`, `.htaccess` | Unverändert. |
| Alle 27 Routen, 18 davon abgesichert | Dieselben Pfade, dieselbe Absicherung. |

### `getSilexApplication()` gibt es nicht mehr
**Seit `009-002-0005` (2026-09-09).**

Die Methode war an `Knp\Command\Command` geerbt und ist mit
`knplabs/console-service-provider` aus dem Baum gefallen. Ein Projekt-Command, der sie ruft,
bekommt einen `Error`.

*Was zu tun ist:* `application()` rufen. Sie liefert dasselbe Objekt und heisst so, weil der alte
Name beim neuen Kernel das Falsche beschreibt.

### Ein Projekt-Command muss die Signaturen von Console 7 treffen
**Seit `009-002-0005` (2026-09-09).**

`symfony/console` 7 deklariert `setName(string $name): static`, `configure(): void` und
`execute(InputInterface $input, OutputInterface $output): int`. Unter Console 4 war alles drei
untypisiert. PHP lehnt eine aufweichende Implementierung **beim Laden** ab, nicht beim
Ausführen — die Konsole startet dann gar nicht.

Im Framework selbst hatte `SetupCommand::execute()` gar kein `return`; unter Console 4 ergab
das still `null`, was Symfony als 0 las.

*Was zu tun ist:* Die drei Signaturen an jedem eigenen Command angleichen und sicherstellen,
dass `execute()` einen `int` zurückgibt.

### `$app` ist kein Action-Argument mehr
**Seit `009-001-0002` (2026-09-09).**

Silex lieferte einer Controller-Action ein Argument, das mit `Silex\Application` typisiert war
— über `Silex\AppArgumentValueResolver`, der auf die **konkrete** Klasse prüft. Diesen Resolver
gibt es nicht mehr; im Framework sind dafür sieben Actions im `ApiController` um den Parameter
gekürzt worden.

*Was zu tun ist:* Den Parameter aus der Action streichen und `$this->app` benutzen. Das ist
dasselbe Objekt aus derselben Container-Factory.

### `$app['request']` ist entfallen
**Seit `009-002-0004` (2026-09-09).**

Der Schlüssel war eine Falle: Der Container merkt sich das Ergebnis einer Factory, also hätte er
ab dem ersten Zugriff **denselben** Request geliefert — auch im nächsten. Genau daran ist der
Fehlerhandler in `000-000-0006` gestorben.

*Was zu tun ist:* Den Request als Action-Argument entgegennehmen, oder
`$app['request_stack']->getCurrentRequest()`.

### `$app['controllers_factory']` ist entfallen
**Seit `009-002-0003` (2026-09-09).**

Silex' `ControllerCollection` gibt es nicht. Ersatz ist
`Areanet\PIM\Classes\Kernel\Routing\RouteCollector` mit genau `get()`, `post()` und
`match()` — mehr hat kein Aufrufer im Baum benutzt.

*Was zu tun ist:* `new RouteCollector()` statt `$app['controllers_factory']`. Wer mehr als die
drei Methoden braucht, baut die `RouteCollection` selbst; `mount()` nimmt beides.

### `$app['twig']` gibt es nicht mehr
**Seit `012-002-0003` (2026-09-04).**

Twig war die Template-Engine der PIM-Oberfläche und ist mit ihr gegangen — mit `twig/twig` aus dem
Manifest und der Registrierung aus dem Bootstrap. Ein Projekt, das Twig **selbst** benutzt, merkt es
erst am ersten Aufruf: Beim Bestandsprojekt UFP (`007-005-0004`) rendern fünf Mail-Vorlagen über
`$app['twig']`, und der Container antwortet `The container does not know "twig"`.

*Was zu tun ist:* Twig als eigene Abhängigkeit aufnehmen und in `custom/app.php` registrieren — mit dem
Pfad, den das Framework früher setzte:

```php
// composer require twig/twig:^3
$app['twig'] = function ($app) {
    return new \Twig\Environment(
        new \Twig\Loader\FilesystemLoader(__DIR__ . '/Views/'),
        array('strict_variables' => false)
    );
};
```

Die Vorlagen von UFP rendern unter Twig 3 bis auf ein Leerzeichen gleich wie unter Twig 2.

### Ein eigener Controller-Provider: andere Schnittstelle, Rückgabetyp, und `connect()` wird gerufen
**Seit `009-001-0003` und `009-002-0003` (2026-09-09).**

Drei Änderungen an einer Stelle:

1. `Silex\Api\ControllerProviderInterface` ist ersetzt durch
   `Areanet\PIM\Classes\Kernel\ControllerProviderInterface`. Der Grund ist zwingend: Silex'
   Fassung schreibt `connect(Silex\Application $app)` vor, und PHP erlaubt einer
   Implementierung, den Parametertyp zu **erweitern**, nicht ihn zu ersetzen — solange ein
   Provider Silex' Schnittstelle implementiert, muss er Silex nennen.
2. `connect()` hat jetzt einen Rückgabetyp: `Symfony\Component\Routing\RouteCollection`.
   Bewusst nicht `RouteCollector`, damit ein Projekt seine Routen auch anders bauen kann.
3. **`mount()` ruft `connect()` nicht mehr selbst.** Silex erkannte einen Provider an seiner
   Schnittstelle; hier übergibt der Aufrufer die fertige Sammlung.

*Was zu tun ist:* Die Schnittstelle tauschen, den Rückgabetyp setzen und an der Aufrufstelle
`$app->mount($prefix, $provider->connect($app))` schreiben.

### Der Container ist nicht mehr Pimple
**Seit `009-002-0002` (2026-09-09).**

`Areanet\PIM\Classes\Kernel\Container` bildet Pimples Vertrag nach, aber nicht seinen vollen
Umfang. **`share()`, `protect()`, `raw()`, `factory()` und `register()` gibt es nicht** — sie
kamen im Baum nicht vor.

Zwei davon fallen einem Projekt eher auf die Füsse als die anderen:

- **`protect()`** — ohne es wird eine Closure, die als *Wert* abgelegt werden soll, beim ersten
  Zugriff aufgerufen. Der Fehler ist laut: Der Aufrufer bekommt den Rückgabewert statt der
  Closure.
- **`register()`** — Service-Provider im Pimple-Sinn gibt es nicht mehr. Ein Projekt registriert
  seine Dienste direkt: `$app['x'] = function ($app) { … };`

Ausserdem sind die **Ausnahmetypen** andere: Ein unbekannter Schlüssel ergibt
`\InvalidArgumentException`, ein `extend()` auf einen bereits ausgelesenen Dienst
`\RuntimeException` statt Pimples `FrozenServiceException`.

*Was zu tun ist:* Auf die fünf Methoden verzichten; `catch (FrozenServiceException)` auf
`\RuntimeException` umstellen.

### `$app->stream()` ist entfallen — `$app->redirect()` und `$app->json()` sind geblieben
**Seit `009-001-0004` (2026-09-09), berichtigt mit `007-005-0005`.**

Silex' Rumpf lautete wörtlich `return new StreamedResponse(...)`. **Dieser Eintrag nannte bis
`007-005-0005` auch `redirect()` entfallen** — die Methode ist aber mit `009-002-0002` in die eigene
`Application` zurückgekommen, zusammen mit `json()`. Das Bestandsprojekt UFP ruft `$app->redirect()`
13-mal auf, und es läuft.

*Was zu tun ist:* Statt `$app->stream()` eine `StreamedResponse` direkt zurückgeben. `redirect()` und
`json()` bleiben, wie sie sind.

### `Classes\Event` erbt von den Event-Contracts
**Seit `009-002-0004` (2026-09-09).**

`Symfony\Component\EventDispatcher\Event` gibt es in Symfony 7 nicht mehr; die Basisklasse ist
nach `Symfony\Contracts\EventDispatcher\Event` gewandert. Ein Projekt, das ein eigenes
Ereignis von der alten Klasse ableitet oder sie type-hinted, bricht.

*Was zu tun ist:* Von `Areanet\PIM\Classes\Event` oder direkt von der Contracts-Klasse
ableiten.

### `dispatch()` hat die umgekehrte Argumentreihenfolge
**Seit `009-002-0004` (2026-09-09).**

Seit Symfony 4.3 heisst es `dispatch($event, $eventName)` statt `dispatch($eventName, $event)`.
Im Framework waren 21 Stellen betroffen.

*Was zu tun ist:* Die Argumente jedes eigenen `dispatch()`-Aufrufs drehen. **Der Fehler ist
still, wenn beide Argumente durchgehen** — deshalb hier und nicht nur im Upgrade-Log von
Symfony.

### `Application::add()` heisst `addCommand()`
**Seit `009-003-0002` (2026-09-09).**

`Symfony\Component\Console\Application::add()` ist deprecated.

*Was zu tun ist:* `addCommand()` rufen. Für Projekt-Commands ändert sich nichts — die laufen
über `$app['consoleManager']->addCommand(…)`, und der Name stimmte dort schon.

### Ein `before()`-Hook verhindert keine spätere Command-Registrierung mehr
**Seit `009-004-0004` (2026-09-09).**

Zwischen `009-002` und `009-004` war das anders, und zwar kaputt: `before()` las den Dispatcher
sofort aus und fror den Container-Eintrag ein; jede danach in `custom/app.php` registrierte
Console-Anmeldung starb mit `RuntimeException: Der Dienst "dispatcher" ist bereits ausgelesen`.
Silex hatte die Registrierung bis zum Boot verschoben, der Nachbau zunächst nicht.

*Was zu tun ist:* Nichts. Der Eintrag steht hier, weil ein Projekt, das eine Zwischenfassung
erwischt hat, den Fehler sonst bei sich sucht.

### `symfony/validator` und `symfony/translation` sind aus dem Baum
**Seit `009-002-0001` (2026-09-09).**

Beide waren angefordert und wurden nicht benutzt — gezählt: 0 Fundstellen im Namensraum, 0
gelesene Container-Schlüssel, 0 `@Assert`, 0 `trans()`. Der `ValidatorServiceProvider` wurde
registriert und nie abgeholt.

*Was zu tun ist:* Ein Projekt, das `$app['validator']` oder `trans()` benutzt, fordert das
Paket in seinem eigenen Manifest an.

## Entity-Layer (Story `010-001`)

### Dateien aus Contentfly 1.6 liegen unter `data/files/JJJJ/MM/<id>/` — `appcms:files:relocate` zieht sie um
**Seit `000-000-0041` (2026-09-15).**

Contentfly 1.6 legte eine Datei unter `data/files/<path><id>/` ab; `pim_file.path` hielt ein
Datumspräfix wie `2026/06/`. Contentfly 2 liest `data/files/<id>/` und kennt `path` nicht — das
Schema-Update **löscht die Spalte**. Danach findet die API keine Bestandsdatei mehr, und die Angabe, wo
sie lag, ist mit der Spalte weg. Beim Bestandsprojekt UFP (`007-005-0003`) traf das alle 41 Dateien.

Entschieden: **ein Layout, einmal erreicht** — kein Altlast-Zweig im Datei-Backend. Der neue Command
verschiebt jeden Ordner samt Thumbnails und Varianten nach `data/files/<id>/`; die Datenbank liest er nur.

*Was zu tun ist:* **Vor dem Schema-Update** in Phase 4, weil der Command die Spalte braucht:

```sh
php bin/console.php appcms:files:relocate --dry-run   # zeigt, was er täte
php bin/console.php appcms:files:relocate             # verschiebt
```

Gemessen an UFP: 27 verschoben, 14 fehlten schon vorher (gemeldet, nicht angefasst); ein zweiter Lauf
findet alle 27 am Platz; eine Bestandsdatei wird danach vom migrierten Backend byte-gleich ausgeliefert.
Geleerte Datumsordner entfernt der Command. **Er meldet und lässt stehen:** Ordner, die schon fehlen;
Konflikte, bei denen Quelle und Ziel beide existieren; einen `path`, der kein schlichtes relatives Präfix
ist. Konflikte und abgelehnte Pfade setzen den Exit-Code auf 1. Ordner unter einem Datumspräfix, zu denen
`pim_file` keine Zeile hat (bei UFP 12), bleiben liegen — sie gehören zu keiner Datei der Anwendung.

Ohne Spalte `path` meldet der Command, dass nichts zu tun ist — auch wenn er versehentlich nach dem
Schema-Update läuft. Dann liegen die Dateien noch im alten Layout, und die Zuordnung steht nur noch in der
Sicherung aus Phase 1.

### Entities tragen PHP-Attribute statt Annotationen
**Seit `010-001-0003` (2026-09-10).**

`@ORM\*` und `@PIM\*` im Docblock sind durch `#[ORM\...]` und `#[PIM\...]` ersetzt, und der
Metadaten-Treiber ist ein `AttributeDriver`. Das Schema, das die API ausliefert, ist dabei
**Feld für Feld unverändert** geblieben, ebenso die erzeugte Datenbank — 211 Spalten und 67
Indexzeilen, gemessen vorher gegen nachher.

**Für ein Bestandsprojekt heisst das: Die eigenen Entities müssen mit.** Ein Mischbetrieb ist
nicht möglich, und der Grund liegt tiefer als der Treiber je Namensraum: Bei einer
`MappedSuperclass` setzt Doctrine an den geerbten Feldern kein `inherited`, also liest der
Treiber der **Unterklasse** sie noch einmal selbst. Eine Projekt-Entity, die von
`Areanet\PIM\Entity\Base` erbt und Annotationen trägt, findet an der umgestellten `Base` keinen
Identifier mehr:

```
No identifier/primary key specified for Entity "…" sub class of "Areanet\PIM\Entity\Base".
```

**Zwei Stolperstellen, beide im Framework selbst aufgetreten:**

1. **Ein Attribut wird gegen die `use`-Zeilen seiner eigenen Datei aufgelöst.** Wer Felder in
   einen Trait auslagert, braucht `use Doctrine\ORM\Mapping as ORM;` **dort**. Unter
   Annotationen ging es ohne, weil `ReflectionProperty::getDeclaringClass()` für eine
   Trait-Eigenschaft die benutzende Klasse liefert und der `AnnotationReader` gegen deren
   Imports auflöste. Fehlt der Import, fällt das Feld **still** aus dem Schema.
2. **`Index` und `UniqueConstraint` stehen neben `Table`, nicht darin.** Als Annotation mussten
   sie mangels Wiederholbarkeit in ein Array unter `Table`; ein Attribut darf sich wiederholen.

*Was zu tun ist:* Die eigenen Entities auf Attribute umstellen, die Imports in Traits ergänzen,
`Index`/`UniqueConstraint` herausziehen. Ein Migrationsweg gehört in Epic `007`.

### Die Ziele der `@PIM`-Attribute sind enger als vorher
**Seit `010-001-0001` (2026-09-10).**

Ohne `@Target` galt für eine Annotation `TARGET_ALL` — sie durfte überall stehen. Als Attribut
benennt jede Klasse ihre Ziele: `Config` an Klasse **und** Eigenschaft, die übrigen sieben nur
an Eigenschaften. Das deckt sich mit jeder Verwendung im Framework, aber ein Projekt, das etwa
`@PIM\Select` an einer Klasse gesetzt hat, bekommt jetzt einen Fehler.

*Was zu tun ist:* Solche Stellen entfernen — sie hatten ohnehin keine Wirkung.

### `Type::getAnnotationFile()` ist entfallen
**Seit `010-001-0005` (2026-09-10).**

Die abstrakte Methode nannte dem `DocParser` den Dateipfad einer Annotationsklasse, damit
`AnnotationRegistry::registerFile()` sie laden konnte — nötig, weil der Parser eine Annotation
nur auflöst, wenn ihre Klasse bereits bekannt ist. Ein Attribut nennt eine echte Klasse, die der
Autoloader holt. Die Methode hat damit keinen Gegenstand mehr, und `doctrine/annotations` ist
aus dem Manifest.

**Das bricht nicht beim Laden:** Eine abstrakte Methode zu **entfernen** ist für Ableitungen
harmlos — ein eigener `CustomType`, der sie implementiert, behält sie als zusätzliche, nie
gerufene Methode. Wer sich auf die **Wirkung** verlassen hat, verliert sie: Eine eigene
Attributklasse muss autoladbar sein. Unter `Custom\` (auf `custom/`) und `Plugins\` (auf
`plugins/`) ist sie das.

*Was zu tun ist:* Die eigene `getAnnotationFile()` kann weg. Sicherstellen, dass eigene
Attributklassen über PSR-4 gefunden werden.

### `BaseI18nTree`: Der Elternknoten trägt seine Sprache mit — neue Spalte `parent_lang`
**Seit `000-000-0025` (2026-09-15).**

`BaseI18nTree` hat einen zusammengesetzten Schlüssel (`id`, `lang`), die Beziehung `treeParent`
hatte aber nur die Join-Spalte `parent_id`. `orm:validate-schema` meldete das als Mapping-Fehler.
Jetzt zeigt ein Knoten über `parent_id` **und** `parent_lang` auf seinen Elternknoten, und zwar
auf den **in derselben Sprache**. So hat das Framework den Baum schon immer gelesen
(`Api::getTree()` verlangt `parent.lang = :lang`). Begründung: `an_project/docs/architecture.md`,
*Key decisions*, 2026-09-15.

**Was sich ändert:**

- `pim_i18n_tree` bekommt die Spalte `parent_lang`; Index und Fremdschlüssel gehen über
  `(parent_id, parent_lang)`.
- Beim Anlegen einer Übersetzung übernimmt die API einen universellen Join auf eine
  i18n-Entity jetzt in der **geschriebenen** Sprache, nicht in der Hauptsprache.
- **Eine Übersetzung kann erst unter einen Elternknoten, wenn es dessen Übersetzung gibt.** Der
  Fremdschlüssel verlangt die Zeile `(parent_id, lang)`. Vorher liess sich ein solcher Knoten
  anlegen, erschien in `getTree()` aber ohnehin nicht, weil die Abfrage den Elternknoten in
  derselben Sprache sucht.

*Was zu tun ist* — nur, wenn das Projekt von `BaseI18nTree` erbt. Im Framework und in der Vorlage
tut das niemand. Durchgespielt an einer Datenbank im alten Schema:

1. **Waisen suchen**, also Knoten, deren Elternknoten in ihrer Sprache fehlt:
   ```sql
   SELECT c.id, c.lang, c.parent_id
   FROM pim_i18n_tree c
   LEFT JOIN pim_i18n_tree p ON p.id = c.parent_id AND p.lang = c.lang
   WHERE c.parent_id IS NOT NULL AND p.id IS NULL;
   ```
   Für jede Zeile entscheiden: die Übersetzung des Elternknotens anlegen, oder `parent_id` auf
   `NULL` setzen. Schritt 3 scheitert sonst genau an diesen Zeilen am Fremdschlüssel.
2. **Schema nachziehen:** `php bin/console.php orm:schema-tool:update --dump-sql` zeigt fünf
   Statements, alle an `pim_i18n_tree` (alten Fremdschlüssel und Index entfernen, Spalte anlegen,
   neuen Fremdschlüssel und Index anlegen). Danach mit `--force` ausführen.
3. **Die Sprache eintragen:**
   ```sql
   UPDATE pim_i18n_tree SET parent_lang = lang WHERE parent_id IS NOT NULL;
   ```
4. `php bin/console.php orm:validate-schema` meldet beide Hälften grün.

### `APP_CACHE_DRIVER = 'apc'` gibt es nicht mehr
**Seit `010-002-0002` (2026-09-10).**

Der Zweig benutzte `Doctrine\Common\Cache\ApcCache`, und die ruft `apc_fetch()`. **Die
APC-Erweiterung gibt es für PHP 7 und 8 nicht mehr** — gemessen ist `function_exists('apc_fetch')`
schlicht `false`. Der Zweig konnte auf keiner unterstützten PHP-Version laufen; er war eine
Falle, keine Einstellung.

Eine Instanz mit `apc` **startet jetzt nicht mehr**, statt stillschweigend auf `filesystem`
zurückzufallen. Das ist Absicht: Ein stiller Rückfall hätte den Betreiber weiter glauben lassen,
sein Cache liege im geteilten Speicher.

*Was zu tun ist:* Auf `apcu` umstellen — den Nachfolger, den der Bootstrag seit jeher behandelt,
der aber nie in der Dokumentation von `APP_CACHE_DRIVER` stand.

### `APP_CACHE_DRIVER = 'memcached'` braucht jetzt einen Server
**Seit `010-002-0002` (2026-09-10).**

Vorher baute der Bootstrap ein blankes `new Memcached()` — einen Client **ohne einen einzigen
Server**. Ein solcher Client speichert nichts; der Zweig war also selbst dort wirkungslos, wo
die Erweiterung vorhanden war, und zwar lautlos.

Der Server steht jetzt in `APP_CACHE_MEMCACHED_DSN`, Vorgabe `memcached://localhost:11211`.
Ausserdem trennen Abfrage- und Metadaten-Cache jetzt über Namensräume; vorher teilten sie sich
**eine** Instanz, während alle anderen Zweige trennten.

Fehlt die Erweiterung, meldet sich die Anwendung beim Start, statt beim ersten Zugriff mit einem
Fatal zu sterben.

*Was zu tun ist:* Den DSN setzen, falls der Server nicht lokal auf dem Standardport läuft. Wer
sich auf den bisherigen Zustand verlassen hat, hat in Wahrheit ohne Cache gearbeitet.

### Der Metadaten-Cache greift jetzt wirklich
**Seit `010-002-0005` (2026-09-10).**

Er war konfiguriert und wirkungslos: `bootstrap.php` setzte ihn auf der Konfiguration,
**nachdem** der EntityManager gebaut war, und `EntityManager::__construct()` liest ihn genau
einmal. Gemessen — Cache vor dem EntityManager gesetzt: 2 Cache-Dateien nach einer
Metadaten-Abfrage; danach gesetzt: **0**.

**Für ein Bestandsprojekt ist das eine Verhaltensänderung, auch wenn nichts an der
Konfiguration zu ändern ist.** Wer bisher eine Entity änderte, sah die Änderung sofort, weil die
Metadaten bei jedem Request neu gelesen wurden. Ab jetzt liegen sie im Cache, und ein
Deployment muss ihn räumen.

Im Debug-Modus und auf der Konsole bleibt der Cache aus — das war schon so und ändert sich
nicht.

*Was zu tun ist:* Das Deployment um einen Schritt ergänzen, der `data/cache/metadata` leert —
oder `POST /system/do` mit `method=flushSchemaCache` aufruft. Siehe
`an_project/docs/deployment.md`.

### Query- und Metadaten-Cache liegen in einem anderen Format
**Seit `010-002-0001` (2026-09-10).**

Die Adapter kommen aus `symfony/cache` statt aus `doctrine/cache`. Die Verzeichnisse bleiben
(`data/cache/query`, `data/cache/metadata`), der Inhalt hat ein anderes Layout.

*Was zu tun ist:* Beim Deployment einmal leeren. Alte Dateien werden nicht gelesen und nicht
aufgeräumt.

## Authentifizierung (Story `013-001`)

### `APP_MASTER_PASSWORD` gibt es nicht mehr
**Seit `013-001-0002` (2026-09-10).**

Ein hier gesetzter Wert akzeptierte den Login **für jeden Benutzer** — eine Konfigurationszeile
mit Vollzugriff auf jedes Konto.

**Ersatzlos entfernt, nicht abschaltbar gemacht.** Ein Schalter, der Vollzugriff gewährt, ist
auch ausgeschaltet eine Hintertür: Er kann versehentlich gesetzt werden, er steht in
Konfigurationsbeispielen, und er lädt dazu ein, ihn „nur kurz" zu benutzen.

Dass das Problem bekannt war, ist aktenkundig: Das Kundenprojekt, aus dem dieses Framework
herausgeschnitten wurde, setzte den Wert beim Bootstrap ausdrücklich auf `null`.

*Was zu tun ist:* Die Zeile aus `custom/config.php` streichen — sie hat keine Wirkung mehr, und
PHP meldet ein Schreiben auf eine nicht deklarierte Eigenschaft. Wer sich auf den Zugang
verlassen hat, braucht das Passwort des jeweiligen Benutzers oder setzt es zurück.

### Passwörter werden mit Argon2id gehasht
**Seit `013-001-0001` (2026-09-10).**

Vorher `hash('sha256', $pass.$salt)` — ein Verfahren **ohne Arbeitsfaktor**. Eine GPU prüft
Milliarden Kandidaten pro Sekunde.

**Bestandsdaten wandern beim Login mit.** Ein alter Hash wird weiterhin akzeptiert und dabei
ersetzt; nach dem ersten Login jedes Benutzers ist er weg. Kein Zwangs-Reset, keine Migration
im Voraus.

**Die Spalte `pim_user.pass` fasst jetzt 255 statt 100 Zeichen.** Ein Argon2id-Hash ist rund 96
— es hätte knapp gepasst und war trotzdem zu eng, weil PHP Algorithmus und Parameter wechseln
darf. Ein abgeschnittener Hash fällt nicht beim Speichern auf, sondern beim nächsten Login, als
„Passwort falsch".

*Was zu tun ist:* Ein Schema-Abgleich meldet die Spalte. Wer eigene Stellen hat, die
`pim_user.pass` direkt schreiben, muss sie auf `password_hash()` umstellen — der alte
SHA-256-Weg wird nur noch **gelesen**.

### Der Login antwortet jetzt mit `429`
**Seit `013-001-0003` (2026-09-10).**

`POST /auth/login` kannte bisher genau zwei Ausgänge: `200` mit Token oder `401`. Dazu kommt
**`429 Too Many Requests`**, mit einem `Retry-After`-Kopf. Ein Client, der jede Antwort ungleich
`200` als „Passwort falsch" auslegt, zeigt seinem Benutzer ab jetzt die falsche Meldung.

Gebremst wird **pro Kennung und pro IP**, gestaffelt: fünf Fehlversuche je Kennung und Minute,
zwanzig je Adresse und Minute, darüber je ein Fenster über eine Viertelstunde und über eine
Stunde. Die Wartezeit wächst dadurch mit der Hartnäckigkeit — gemessen 60 s, 900 s, 3600 s.
Nur Fehlversuche zählen; eine gelungene Anmeldung löscht den Zähler der Kennung.

**`AuthController::CHECK_LOGIN_INTERVAL` und `MIN_LOGIN_INTERVAL` sind entfallen.** Beide waren
`public` und damit theoretisch von aussen lesbar. Wirkung hatten sie keine: Die erste war fest
`false`, der Zweig dahinter lief nie.

*Was zu tun ist:* Clients, die auf den Statuscode reagieren, um `429` ergänzen. Wer den Login in
einem Skript aufruft, das absichtlich falsche Anmeldungen erzeugt, läuft jetzt in die Bremse.

### Hinter einem Proxy gehört `APP_TRUSTED_PROXIES` gesetzt
**Seit `013-001-0003` (2026-09-10).**

`setTrustedProxies()` wurde vorher im ganzen Baum **nirgends** gerufen. Das war folgenlos,
solange niemand die Adresse des Aufrufers auswertete. Mit der Bremse pro IP ist es das nicht
mehr: Steht ein Reverse Proxy oder Loadbalancer davor, hält die Anwendung ohne diese Angabe
**dessen** Adresse für die des Aufrufers — die Bremse träfe ihn und damit alle Benutzer
dahinter, während der Angreifer ungebremst weiterrät.

**Ohne Eintrag ändert sich nichts.** Der Vorgabewert ist leer; dann wird `setTrustedProxies()`
gar nicht erst gerufen und die Anwendung verhält sich wie bisher. Wer keine Proxies
konfiguriert, betreibt sie direkt — dann stimmt die Adresse ohnehin.

*Was zu tun ist:* Wer hinter einem Proxy läuft, trägt ihn in `custom/config.php` ein; die
Vorlage führt den Block auskommentiert mit. **Nur eintragen, wem man traut** — ein vertrauter
Absender darf sagen, wer der Aufrufer ist. `APP_TRUSTED_HEADERS` wählt zwischen den
X-Forwarded-*-Headern (Vorgabe) und `Forwarded` nach RFC 7239; ein unbekannter Wert wird
abgewiesen statt stillschweigend auf die Vorgabe zurückgeführt.

### Alle Sitzungen enden mit dem Update
**Seit `013-001-0004` (2026-09-10).**

`pim_token.token` trug 128 Hex im Klartext. Ein Lesezugriff auf die Datenbank — ein Backup, eine
SQL-Injection, ein Dump im Ticketsystem — übergab damit **sämtliche laufenden Sitzungen**,
sofort verwendbar. Gespeichert wird jetzt ein SHA-256; beim Prüfen wird der vorgezeigte Token
gehasht und der Hash nachgeschlagen.

**Bestehende Zeilen werden dadurch unbrauchbar.** Ein gespeicherter Klartext-Token trifft nie
auf den Hash eines vorgezeigten. Wer angemeldet ist, meldet sich einmal neu an; ein
**Referrer-Token muss neu hinterlegt werden**, sonst schliesst sich die Schnittstelle, die ihn
benutzt.

Sie beim Update mitzuhashen war die verworfene Alternative: Das hiesse, sie noch einmal im
Klartext zu lesen — und ein Backup von gestern enthält sie ohnehin.

*Was zu tun ist:* Nach dem Update `DELETE FROM pim_token;`. Die Zeilen sind wertlos, und eine
leere Tabelle sagt deutlicher, was passiert ist, als eine voller Einträge, die niemanden mehr
einlassen. Referrer-Tokens danach über `POST /system/do` mit `addToken` neu anlegen — mit
**neuen** Werten, denn die alten standen im Klartext in Datenbank und Protokoll.

**Projektcode, der Tokens selbst nachschlägt, bricht mit.** Wer `pim_token.token` liest oder mit
`findOneBy(array('token' => $token))` sucht, findet einen Hash statt des Tokens — oder nichts. Beim
Bestandsprojekt UFP (`007-005-0004`) leitete der OAuth-Controller nach dem Login mit dem gespeicherten
Wert weiter; die App hätte einen Hash als Token vorgezeigt und `401` bekommen. Das Token im Klartext
steht nur einmal zur Verfügung: im Feld `token` der Login-Antwort. Nachschlagen geht über
`Areanet\PIM\Entity\Token::hash($token)`.

### `listTokens` liefert den Hash, nicht den Token
**Seit `013-001-0004` (2026-09-10).**

Das Feld `token` in der Antwort von `listTokens` — und in der von `addToken` bei einem späteren
Aufruf — ist der gespeicherte Hash. **Der Token selbst lässt sich nicht mehr nachschlagen, auch
nicht vom Betreiber.** Er wird genau einmal zurückgegeben: in der Antwort auf `addToken`, die
ihn anlegt, beziehungsweise auf `/auth/login`.

Das Feld bleibt stehen, weil es die Zeile eindeutig benennt und weil, wer einen Token in der
Hand hält, ihn selbst hashen und so seinen Eintrag finden kann. Ein Client, der den Wert
versehentlich als Token vorzeigt, bekommt `401` — er fällt zu, nicht auf.

### `addToken` weist einen schwachen Token ab und erzeugt einen, wenn keiner kommt
**Seit `000-000-0030` (2026-09-15).**

`addToken` nahm `token` aus dem Request, wie es kam; nur ein leerer Wert wurde abgewiesen.
`token=test` wurde angelegt. In `pim_token` steht ein ungesalzener SHA-256, und der ist für `test`
in Sekunden zurückgerechnet (Befund A-5).

**Was sich ändert:**

- **`token` ist optional.** Fehlt es, erzeugt das Framework den Wert (64 Zufallsbytes als Hex,
  wie `generateToken`). Die Antwort trägt ihn genau einmal, wie bisher.
- **Ein mitgeschickter Token braucht mindestens 32 Zeichen, davon mindestens 10 verschiedene.**
  Sonst antwortet `addToken` mit **`400`** und legt nichts an. Vorher wurde er angenommen (`200`).
- Fehlen `referrer` oder `user`, bleibt es bei `500`; die Meldung heisst jetzt
  `Invalid referrer and/or user`.
- **Bestehende API-Tokens bleiben gültig,** auch schwache: Die Tabelle kennt nur Hashes, ihre
  Stärke lässt sich im Nachhinein nicht prüfen.

**Bewusst unverändert, mit Begründung im Code:** API-Tokens verfallen weiter nicht
(`TokenHandler::timeoutApplies()`), und das Vorzeigen eines Tokens wird nicht gebremst
(`TokenHandler::fromDatabase()`).

*Was zu tun ist:* Wer beim Anlegen einen eigenen Wert mitschickt, prüft ihn gegen die Grenze, oder
lässt `token` weg und übernimmt den erzeugten Wert aus der Antwort. **Bestehende API-Tokens, die
von Hand gewählt wurden, neu anlegen** (erst anlegen, im Fremdsystem eintragen, dann den alten
mit `deleteToken` entfernen). Wer sie behält, behält ein Risiko, das kein Update beseitigen kann.

### `pim_log.model_label` trägt bei Token-Vorgängen den Hash
**Seit `013-001-0004` (2026-09-10).**

`addToken` und `deleteToken` schrieben den Token im Klartext ins Protokoll. `pim_log` lebt
länger als die Sitzung, die es beschreibt — ein Dump des Protokolls übergab dieselben
Sitzungen wie ein Dump der Tokentabelle. Als Kennzeichen taugt der Hash genauso.

**Der Altbestand bleibt, wie er ist.** `pim_log` ist ein Protokoll; alte Zeilen nachträglich
umzuschreiben hiesse, die Aufzeichnung zu ändern — dieselbe Linie wie bei den deutschen
`mode`-Werten aus `000-000-0015`. Wer alte Protokollzeilen aufbewahrt, sollte wissen, dass darin
verwendbare Token stehen, und sie entsprechend behandeln.

### `POST /api/login` und `POST /api/logout` sind entfallen
**Seit `013-001-0005` (2026-09-10).**

Sie zeigten auf `api.controller:loginAction` und `:logoutAction` — Methoden, die es im
`ApiController` nicht gibt und in diesem Baum nie gab. **Erreicht haben sie den Router
ohnehin nie:** `RouteCollector` zählt ihre Routen je Provider durch, `/api/login` hiess
`login_0` und wurde beim Mounten von `/auth/login` gleichen Namens verdrängt. Gemessen: 30
registrierte Routen, 29 in der Sammlung.

Entfernt statt auf `auth.controller` umgebogen — ein zweiter Name für dieselbe Sache wäre eine
zweite Oberfläche, die man absichern muss.

*Was zu tun ist:* Nichts. Die Antwort auf `/api/login` ist dieselbe wie vorher — `405`, wie bei
jedem unbekannten Pfad. Die funktionierenden Routen sind `/auth/login` und `/auth/logout`.

### Routennamen tragen jetzt den Mountpunkt
**Seit `013-001-0005` (2026-09-10).**

`Application::mount()` stellt den Routennamen den normalisierten Mountpunkt voran, aus
`login_0` wird `auth_login_0`. Nötig, weil `RouteCollection::addCollection()` beim Namen
überschreibt und `RouteCollector` je Provider bei null zu zählen beginnt — zwei Provider,
deren erste Route denselben Pfad trägt, frassen einander auf.

**Das betrifft auch eigene Provider.** Ein Projekt, das über `custom/app.php` mountet, verlor
bisher stillschweigend jede Route, deren Name mit einer schon gemounteten kollidierte.

*Was zu tun ist:* In aller Regel nichts — die Namen benutzt niemand, es gibt keinen
`url_generator`. Wer eine Route doch beim Namen nennt, zieht den Präfix nach.

### LoginManager aus `Plugins\…` funktionieren
**Seit `013-001-0005` (2026-09-10).**

`substr($name, 7) == 'Plugins'` schnitt **ab** Position 7, statt die ersten sieben Zeichen zu
prüfen. Für `Plugins\Auth\Ldap` ergab das `\Auth\Ldap`; die Bedingung griff nie, und der Name
wurde fälschlich zu `Custom\Classes\Plugins\Auth\Ldap`.

*Was zu tun ist:* Wer eine Klasse tatsächlich unter `Custom\Classes\Plugins\…` abgelegt und
sich auf den Fehler verlassen hat, muss sie verschieben oder unter einem Namen ohne
`Plugins`-Präfix ansprechen. Wer einen echten Plugin-LoginManager hatte, bekommt ihn zum ersten
Mal zum Laufen.

## Authentifizierung, Teil 2 (Story `013-002`)

### `BaseControllerProvider::checkToken()` gibt es nicht mehr
**Seit `013-002-0004` (2026-09-10).**

An seiner Stelle steht `authenticate()`, mit derselben Signatur und derselben Wirkung: ein `bool`,
und im Erfolgsfall stehen `$app['auth.user']` und `$app['auth.token']` wie bisher. Darunter
arbeitet Symfonys `access_token`-Authenticator statt eines eigenen Rumpfes.

*Was zu tun ist:* Ein Projekt, das einen eigenen Controller-Provider von
`BaseControllerProvider` ableitet und in dessen `$checkAuth`-Closure `$this->checkToken(...)`
ruft, ersetzt den Aufruf durch `$this->authenticate(...)`. Mehr nicht — Argumente und Rückgabe sind
gleich geblieben.

### `LOGIN_PATH` und `isAuthRequiredForPath()` sind entfallen
**Seit `000-000-0026` (2026-09-11).**

Beide sassen auf `BaseControllerProvider`. Die Methode gab zurück, ob ein Pfad eine Anmeldung
braucht — und **niemand rief sie**, auch `checkToken()` nicht, solange es das noch gab.

Entfernt statt stehengelassen, weil sie beim Lesen aussah wie die Stelle, an der man steuert,
welche Pfade offen sind. Das tut sie nicht: Diese Entscheidung fällt je Route über `isSecure` im
`RouteManager` beziehungsweise über den `$checkAuth`-Hook des Providers. Eine Methode, die einen
Schalter vortäuscht, den es woanders gibt, ist gefährlicher als gar keine.

*Was zu tun ist:* In aller Regel nichts. Wer einen eigenen Controller-Provider von
`BaseControllerProvider` ableitet und die Methode **überschrieben** hat, bekommt keinen Fehler —
sie wird nur nie gerufen, und das war schon vorher so. Wer sie **aufruft**, bekommt einen
`Error`; der Aufruf kann ersatzlos weg, denn sein Rückgabewert steuerte nichts.

### Die drei Token-Konstanten sind entfallen
**Seit `013-002-0004` (2026-09-10).**

`BaseControllerProvider::TOKEN_HEADER_KEY`, `TOKEN_HEADER_KEY_ALT` und `TOKEN_REQUEST_KEY`
standen dort, weil `checkToken()` sie las. Die Werte stehen jetzt in
`Areanet\PIM\Classes\Security\TokenSources`, zusammen mit dem Code, der sie benutzt.

Sie stehen zu lassen wäre schlimmer gewesen als sie zu entfernen: Drei öffentliche Konstanten,
die nichts mehr steuern, sehen beim nächsten Lesen aus wie die Stelle, an der man die
Quellen eines Tokens ändert.

*Was zu tun ist:* Wer sie gelesen hat, liest sie aus `TokenSources` — `HEADER_ALT`,
`HEADER_ALT_XSRF`, `PARAMETER_ALT`.

### `Authorization: Bearer` wird jetzt angenommen
**Seit `013-002-0002` (2026-09-10).**

Eine fünfte Tokenquelle, und die einzige, die RFC 6750 kennt. Die vier bisherigen —
`appcms-token`, `X-XSRF-TOKEN`, `_token` im Query-String, `_token` im Rumpf — bleiben
unverändert, in derselben Reihenfolge; **Bestandsclients merken nichts.**

Der Bearer-Header wird nur ausgewertet, wenn er mit `Bearer ` beginnt. Ein `Authorization: Basic`
aus `APP_HTTP_AUTH_USER` bleibt unberührt.

### `$app['auth.token']` kann `null` sein
**Seit `013-002-0004` (2026-09-10).**

Der Schlüssel trägt die Zeile aus `pim_token`. Im JWT-Zweig gibt es keine — das ist der Gewinn
jenes Zweigs. Ausgestellt werden JWT erst mit `013-003`; die Möglichkeit entsteht aber hier.

*Was zu tun ist:* Projektcode, der `$app['auth.token']` liest, prüft auf `null`. Der Logout tut
es bereits.

### `SECURITY_JWT_SECRET` ist neu, und es braucht mindestens 32 Byte
**Seit `013-002-0003` (2026-09-10).**

**Kein Standardwert**, dieselbe Linie wie `SECURITY_CIPHER_KEY`: Ein im Repository hinterlegtes
Geheimnis ist keines. Ohne Wert weist der JWT-Zweig jeden Token ab, statt sich stillschweigend
abzuschalten — eine Prüfung, die das täte, wäre keine.

`firebase/php-jwt` ab 7.0 verlangt für HS256 mindestens 32 Byte und wirft sonst schon beim
Signieren. Ein kurzes Geheimnis wäre ohnehin ratbar.

*Was zu tun ist:* Solange keine JWT ausgestellt werden (bis `013-003`), nichts — der opaque
Token-Weg ist unberührt. Wer vorgreifen will, setzt den Wert in der Umgebung:
`php -r "echo bin2hex(random_bytes(32));"`.

### Drei neue Pakete im Root-Manifest
**Seit `013-002` (2026-09-10).**

`symfony/security-http` (mit `security-core`, `password-hasher`, `property-access`,
`property-info`, `type-info` im Schlepptau) und `firebase/php-jwt` auf **`^7.0`**. Die 6er-Reihe
trägt CVE-2025-45769 (*weak encryption*); statt einer Ausnahme im Audit-Gate steht ein
Constraint.

*Was zu tun ist:* `composer install`. Ein Projekt, das `firebase/php-jwt` selbst in
`custom/composer.json` führt — das Kundenprojekt tat das —, muss den Eintrag mit dem Root-Stand
in Einklang bringen.

## Authentifizierung, Teil 3 (Story `013-003`)

### `pim_token` bekommt eine Spalte, und es gibt eine neue Tabelle
**Seit `013-003` (2026-09-10).**

`pim_token.purpose` (nullable) unterscheidet ein Refresh-Token von einem gewöhnlichen
Login-Token. `pim_revoked_token` nimmt die `jti` widerrufener Access-JWT auf, bis diese ohnehin
ablaufen.

**Warum die Spalte sein muss:** Ein Refresh-Token ist eine `pim_token`-Zeile, und der opaque
Zweig nahm bis dahin jede Zeile als Login-Token an. Ein Refresh-Token gilt länger als ein
Access-JWT — das ist sein Zweck —, und ohne die Trennung wäre es ein langlebiger
Generalschlüssel für die ganze API gewesen.

*Was zu tun ist:* Schema abgleichen (`POST /system/do` mit `updateDatabase`, oder ein
Doctrine-Schema-Update). Bestehende Zeilen bekommen `purpose = NULL` und verhalten sich
unverändert.

### Der Login kennt `tokenType`
**Seit `013-003-0001` (2026-09-10).**

`POST /auth/login` gibt auf `tokenType: "jwt"` ein Access-JWT im Feld `token` aus, dazu
`refreshToken` und `expiresIn`. **Ohne die Angabe ändert sich nichts** — ein Bestandsclient
bekommt das opaque Token wie bisher.

Bewusst kein Konfigurationsschalter: Ein solcher kippte die Antwort für jeden Client auf einmal.

*Was zu tun ist:* Nichts, solange der Client nichts anfordert. Wer umsteigt, ändert einen
Feldwert und keinen Feldnamen — `token` bleibt das, was vorgezeigt wird.

### `POST /auth/refresh` ist neu
**Seit `013-003-0002` (2026-09-10).**

Tauscht ein Refresh-Token gegen ein frisches Access-JWT und **ersetzt dabei das Refresh-Token**.
Ein zweiter Gebrauch desselben Refresh-Tokens wird abgewiesen.

Der Endpunkt hängt nicht hinter der Anmeldung — er wird ja gerade dann gebraucht, wenn das
Access-JWT abgelaufen ist — prüft dafür selbst und unterliegt dem `LoginThrottle` (pro Adresse).

*Was zu tun ist:* Ein Client, der JWT benutzt, muss den Rückgabewert `refreshToken` bei jedem
Refresh **ersetzen**. Wer den alten weiterverwendet, fliegt beim zweiten Mal heraus.

### `GET /auth/logout` nimmt `refreshToken` entgegen
**Seit `013-003-0003` (2026-09-10).**

Der Parameter ist optional. Mit ihm wird beim Abmelden zusätzlich das Refresh-Token entzogen;
ohne ihn wird nur das vorgezeigte Access-JWT gesperrt, und die Refresh-Zeile verfällt über ihr
eigenes Zeitlimit.

Ein mitgeschicktes Refresh-Token **eines anderen Benutzers** wird nicht angerührt.

*Was zu tun ist:* Ein Client, der JWT benutzt, hängt sein Refresh-Token an den Logout-Aufruf.

### `appcms:token:cleanup` räumt zusätzlich die Sperrliste
**Seit `013-003-0003` (2026-09-10).**

Derselbe Befehl, ein zweiter Satz in der Ausgabe. Wer ihn im Cron hat, muss nichts ändern; wer
ihn noch nicht hat, braucht ihn jetzt für zwei Tabellen statt einer.

### Ein JWT ohne `kid` wird abgewiesen
**Seit `013-003-0004` (2026-09-10).**

Jedes ausgestellte Token trägt die Kennung seines Signaturschlüssels im Header. Ohne Kennung
müsste die Anwendung raten, welcher Schlüssel gemeint ist — und alle der Reihe nach zu probieren
hebt den Sinn eines Schlüsselwechsels auf.

**Folgenlos für Bestandsprojekte:** Ausgestellt wurde ein Token ohne `kid` nie. Die Ausstellung
entstand mit `013-003-0001`, die Kennung mit `013-003-0004`, und dazwischen lag kein Release.

*Was zu tun ist:* Nichts. Wer selbst Tokens für diese Anwendung signiert, setzt `kid` auf den
Wert von `SECURITY_JWT_KEY_ID`.

## Authentifizierung, Teil 4 — der LoginManager (Story `013-004`)

**Betrifft nur Projekte, die einen `LoginManager` haben.** Wer sich ausschliesslich mit
Benutzername und Passwort anmeldet, ist von diesem Abschnitt nicht berührt.

### `Areanet\PIM\Classes\Manager\LoginManager` gibt es nicht mehr
**Seit `013-004-0002` (2026-09-10).**

An seine Stelle tritt das Interface `Areanet\PIM\Classes\Security\LoginProvider` mit **einer**
Pflichtmethode. Der Unterschied ist nicht nur der Name:

| alt | neu |
|---|---|
| `auth()` liefert eine fertige `User`-Entity | `authenticate(Request): ?ExternalIdentity` liefert, was das Fremdsystem sagt |
| Der Provider ruft `createManagedUser()` und schreibt in die Datenbank | Der Provider fasst die Datenbank **nicht** an |
| Klasse wird über den Request-Parameter ausgewählt | Der Parameter benennt einen Eintrag aus `custom/app.php` |
| Gruppe und Adminflag als Argumente je Aufruf | `SECURITY_PROVIDER_GROUPS`, an einer Stelle |

*Der Weg vom alten Manager zum neuen Vertrag, Schritt für Schritt:*

1. **Die Klasse umhängen.** `extends LoginManager` wird zu
   `implements Areanet\PIM\Classes\Security\LoginProvider`. Der Konstruktor mit `$app` und
   `$request` entfällt; ein Provider bekommt den Request als Argument von `authenticate()`.
2. **`auth()` zu `authenticate()` machen.** Was bisher am Ende `createManagedUser(...)` rief, gibt
   jetzt `new ExternalIdentity($identifierInExternalSystem, $groupsFromExternalSystem)` zurück. Wer
   niemanden erkannt hat, gibt `null` zurück — eine Ausnahme führt zum selben Ergebnis, ihre
   Meldung erreicht den Aufrufer aber nicht mehr.
3. **Die Kennung nicht mehr verfremden.** In die `ExternalIdentity` gehört die Kennung, wie das
   Fremdsystem sie führt. Alias, Präfix und Eindeutigkeit macht das Framework.
4. **Gruppen und Adminflag aus dem Code nehmen** und in `SECURITY_PROVIDER_GROUPS` eintragen —
   je Providername `groups`, `admin` und `default`.
5. **Was der Manager darüber hinaus tat, in einen Listener auf `pim.auth.after.login`.** Felder am
   Benutzer setzen, `tempData` für den Client — das gehört nicht in den Provider, sondern dorthin.
   Werte, die nur der Provider kennt (etwa ein Token des Fremdsystems), gibt er in
   `ExternalIdentity::$attributes` mit. Eintrag weiter unten.
6. **Den Provider registrieren:** `$app['loginProviders']->register('<name>', fn () => new …)`
   in `custom/app.php`. Dieser `<name>` ist ab jetzt der Wert, den ein Client als `loginManager`
   schickt.
7. **Die Clients umstellen:** Sie schicken den Namen statt des Klassennamens.

`custom/Classes/Authentication/ExampleProvider.php` führt alles davon an einem lauffähigen Beispiel
vor.

### Der Request-Parameter `loginManager` wählt keine Klasse mehr aus
**Seit `013-004-0001` (2026-09-10).**

Er benennt einen Eintrag in der `LoginProviderRegistry`. Ein Klassenname steht dort nicht und wird
abgewiesen — wie jeder andere unbekannte Name, und **ohne** auf die Passwortprüfung
zurückzufallen.

Der Parametername bleibt `loginManager`: Bestandsclients schicken ihn so, und ihn umzubenennen
wäre ein Bruch am Draht ohne Gewinn. Wenn er umbenannt wird, dann mit dem Rest der Migration.

*Was zu tun ist:* Schritt 6 und 7 oben. Solange nichts registriert ist, ist jeder
`loginManager`-Wert unbekannt und die Anmeldung darüber scheitert.

### Nach einem erfolgreichen Login löst das Framework `pim.auth.after.login` aus
**Seit `000-000-0045` (2026-09-15).**

**Kein Bruch, sondern der Ort für das, was der Provider-Vertrag nicht abdeckt.** Ein alter
`LoginManager` prüfte nicht nur, er setzte oft auch Felder am Benutzer und gab dem Client über
`tempData` zusätzliche Daten mit. Beim Bestandsprojekt UFP (`007-005-0004`) liest die App bei jedem
Login `data.role` — ohne diesen Haken bräche die Anmeldung im Client.

Das Event kommt auf dem Passwort-Weg und auf jedem Provider-Weg, **nach** Prüfung, Anlage,
Gruppenzuordnung und Aktiv-Prüfung und **vor** dem Ausstellen des Tokens. Ein abgelehnter Login löst
es nicht aus. Parameter: `user`, `request`, `provider` (Name oder `null`), `identity`
(`ExternalIdentity` oder `null`), `app`.

Was ein Listener als `tempData` setzt, steht als `data` in der Antwort — auch mit `tokenType=jwt`.
Änderungen am Benutzer werden mit dem Token gespeichert; ein eigenes `flush()` ist nicht nötig.

*Was zu tun ist:* Nur wer einen Manager mit solchen Zusätzen umstellt:

```php
$app->on('pim.auth.after.login', function (\Areanet\PIM\Classes\Event $event) {
    $user = $event->getParam('user');
    $user->setTempData(array('group' => $user->getGroup()?->getName()));
});
```

**`$app->on()`, nicht `$app['dispatcher']->addListener()`** — wer den Dispatcher in `custom/app.php`
liest, friert ihn ein, und jedes spätere `extend()` wirft (`ConsoleManager`). Die Vorlage
`custom/app.php`, Abschnitt 6, zeigt ein lauffähiges Beispiel.

### Ein über ein Fremdsystem angelegter Benutzer hat kein Passwort mehr
**Seit `013-004-0002` (2026-09-10).**

`createManagedUser()` setzte `setPass($alias)` — das Passwort war der Benutzername (Befund A-6).
Entschärft war das allein durch den Riegel „nur über LoginManager authorisierbar"; jeder Pfad,
der ihn umging, war eine triviale Kontoübernahme.

Neu angelegte Konten bekommen `pim_user.pass = '*'` — kein gültiger Hash, gegen den keine
Eingabe passt. Der Riegel bleibt; er ist jetzt die **zweite** Sicherung.

*Was zu tun ist:* **Den Altbestand prüfen.** Konten, die `createManagedUser()` angelegt hat,
tragen weiterhin `hash('sha256', <alias>.<salt>)` — also ein Passwort, das der Benutzername ist.
Sie sind nur durch den Riegel geschützt. Diese Zeilen gehören gesperrt:

```sql
UPDATE pim_user SET pass = '*' WHERE loginManager IS NOT NULL AND loginManager <> '';
```

Das ist verlustfrei: Diese Konten sollen sich ohnehin nur über ihr Fremdsystem anmelden.

### `pim_user` bekommt eine Spalte und eine Bedingung
**Seit `013-004-0002` (2026-09-10).**

`externalId` (nullable) nimmt die Kennung des Fremdsystems auf; eine Unique-Bedingung
`uniq_user_external_identity` steht über `loginManager` und `externalId`. Dieselbe Eindeutigkeit, die
vorher aus dem MD5-Präfix im Alias kam — nur lesbar.

*Was zu tun ist:* Schema abgleichen. Bestehende Zeilen bekommen `externalId = NULL` und
verhalten sich unverändert.

### Über die Altbestände mit MD5-Präfix ist entschieden: sie bleiben stehen
**Seit `013-004` (2026-09-10).**

Konten, die `createManagedUser()` angelegt hat, tragen einen Alias der Form
`<md5-des-klassennamens>-<identifier>`, `loginManager` mit dem **Klassennamen** und `externalId`
leer. Das Framework schreibt sie **nicht** um, und zwar aus drei Gründen:

1. **Die Zuordnung ist nicht rückrechenbar.** Aus `3f2a…-jdoe` lässt sich die alte Klasse nur
   erraten, indem man alle Klassennamen durchprobiert, die ein Projekt je hatte — und die kennt
   das Framework nicht.
2. **Ein automatischer Umschrieb änderte den Alias**, und der Alias ist die Kennung, unter der
   ein JWT den Benutzer führt (`sub`) und unter der Projektcode ihn womöglich nachschlägt.
3. **Ein Migrationsschritt, den niemand prüfen kann, ist schlimmer als einer, den jemand
   bewusst geht.**

*Was zu tun ist:* Für jeden alten Provider einmal, mit bekanntem Klassennamen und bekanntem
neuem Providernamen:

```sql
UPDATE pim_user
   SET externalId   = SUBSTRING(alias, 34),
       alias        = CONCAT('<neuername>', ':', SUBSTRING(alias, 34)),
       loginManager = '<neuername>',
       pass         = '*'
 WHERE loginManager = '<AlterKlassenname>';
```

Die 34 ist kein Zufall: 32 Zeichen MD5 plus der Bindestrich. **Vorher eine Sicherung anlegen**
und danach nachsehen, dass jeder Benutzer sich noch anmelden kann — wer den Schritt auslässt,
bekommt beim nächsten Login schlicht ein zweites Konto, was ärgerlich, aber nicht gefährlich ist.

**Am Bestandsprojekt erprobt (`007-005-0004`), mit drei Ergänzungen:**

- **Lokale Passwortkonten, die ein Manager markiert hat, haben kein MD5-Präfix.** Ein Manager, der das
  Passwort gegen `pim_user` prüfte und dabei `loginManager` setzte, sperrt diese Konten für den
  Passwort-Weg. Das SQL oben schneidet ihnen 33 Zeichen ab. Für sie gilt entweder `loginManager = NULL`
  (und der Client schickt keinen `loginManager` mehr) oder ein Provider, der das Passwort selbst prüft —
  dann `externalId = alias`, Alias und Passwort bleiben, und der Rehash auf Argon2id entfällt auf diesem Weg.
- **Wer den Provider unter dem bisherigen Kurznamen registriert** (`StandardLoginManager`, ohne
  Namensraum), braucht Schritt 7 nicht: Die Registry vergleicht ohne Gross- und Kleinschreibung, und
  Bestandsclients schickten den Namen ohnehin so.
- **Ein Provider darf zum Prüfen Projektdaten lesen** — eine Umfrage, einen Share-Link, ein
  Passwort. Schreiben tut er nicht; das gehört in den Listener auf `pim.auth.after.login`.
  `SECURITY_PROVIDER_GROUPS` ordnet Gruppen über ihren **Namen** zu; wer Gruppen im Projekt über eine
  Rolle findet, setzt die Gruppe im Listener.

Die Umstellung von UFP steht als Beispiel in dessen Projekt-Branch `migration/contentfly-2`
(`migration/contentfly-2/migration_login_providers.sql`, `custom/Classes/*LoginManager.php`).

## Authentifizierung, Teil 5 — LDAP und OIDC (Story `013-005`)

**Betrifft nur Projekte, die einen der beiden Provider eintragen.** Wer bei Benutzername und
Passwort bleibt, ist von diesem Abschnitt nicht berührt — die Provider sind **nicht**
vorregistriert.

### `symfony/http-client` ist neu im Root-Manifest
**Seit `013-005-0003` (2026-09-11).**

Mit `http-client-contracts` und einem Polyfill. Es wird nur benutzt, wenn ein Projekt den
OIDC-Provider einträgt — im Manifest steht es trotzdem, weil die Klasse mitgeliefert wird und
reines PHP ist.

*Was zu tun ist:* `composer install`.

### `symfony/ldap` kommt **nicht** mit
**Seit `013-005-0004` (2026-09-11).**

Der `LdapProvider` wird mitgeliefert, das Paket dahinter nicht. **Es verlangt die
Systemerweiterung `ext-ldap`**, und `composer install` prüft die Plattformanforderungen aller
Pakete — stünde es im `require`, bräuchte jede Contentfly-Installation die Erweiterung, auch die,
die nie ein Verzeichnis anfasst.

Es steht deshalb in `suggest` (und in `require-dev`, weil die Suite des Frameworks den Provider
prüft). Ein Produktivlauf mit `composer install --no-dev` kommt ohne `ext-ldap` aus.

**Gefunden beim Gate-Lauf auf PHP 8.4:** `composer install` scheiterte im CI-Image mit
„requires ext-ldap", und der Job starb still mit Exit 2 — die Meldung ging in einer Umleitung
unter. `tools/ci/install-php-extensions.sh` baut `ldap` jetzt mit, weil die Suite es braucht.

*Was zu tun ist:* Nur wer den Provider einträgt:

```sh
composer require symfony/ldap
# und ext-ldap ins PHP-Image
```

Ohne das Paket wirft `LdapProvider::fromConfig()` mit genau diesem Hinweis.

### `appcms:provider:sync` ist neu
**Seit `013-005-0002` (2026-09-11).**

Sperrt Benutzer, die ihr Fremdsystem nicht mehr kennt (`isActive = false`). Ohne ihn behält ein
aus dem Verzeichnis entfernter Benutzer seinen Zugang, bis sein Refresh-Token abläuft.

Er **sperrt und löscht nicht**: umkehrbar, und `pim_log` behält seinen Bezug. Ein Provider, der
keine Auskunft geben kann, führt dazu, dass der Benutzer übersprungen wird — sichtbar in der
Ausgabe. **Ein Ausfall sperrt niemanden.**

*Was zu tun ist:* Wer einen Provider einträgt, hängt den Befehl in denselben Cron wie
`appcms:token:cleanup`. Vorher einmal mit `--dry-run` ansehen.

## Feldverschlüsselung (Story `010-004`)

### Verschlüsselt wird mit XChaCha20-Poly1305 statt AES-CBC
**Seit `010-004-0002` (2026-09-10).**

Das alte Verfahren war **unauthentifiziert**. Gemessen an einem Beispielsatz: Ein gekipptes Byte
im Chiffretext ging durch und lieferte einen anderen Klartext — ein Block Müll, der Rest intakt.
Die Anwendung merkte nichts und lieferte ihn aus.

**Bestandsdaten bleiben lesbar.** Das Format steht am Chiffretext, nicht in der Konfiguration:
Ein neuer Wert beginnt mit `PIM1:`, alles ohne dieses Präfix ist das alte Format. Eine frisch
aktualisierte Instanz liest ihre Daten also weiter, ohne dass jemand etwas umstellt.

**Geschrieben wird ausschliesslich neu.** Es gibt keinen Schalter zurück — er wäre ein Weg, auf
das schwächere Verfahren zurückzudrängen, und die Migration wäre nie abgeschlossen.

*Was zu tun ist:* Nichts Zwingendes. Jeder Wert stellt sich beim nächsten Schreiben um. Wer es
auf einen Schlag will, nimmt `appcms:security:reencrypt` — siehe `an_project/docs/deployment.md`.

### Ein manipulierter Wert wirft jetzt, statt Unsinn zu liefern
**Seit `010-004-0002` (2026-09-10).**

Das ist der Zweck der Umstellung und zugleich eine Verhaltensänderung: Wo die Anwendung bisher
irgendetwas zurückgab, verweigert sie jetzt. Ein Projekt, dessen Datenbank beschädigte
Chiffretexte enthält, bemerkt das ab sofort — vorher nicht.

*Was zu tun ist:* Nichts. Wer die Meldung sieht, hat ein Problem, das vorher unsichtbar war.

### `SECURITY_CIPHER_KEY` wird abgeleitet statt durchgereicht
**Seit `010-004-0002` (2026-09-10).**

Der konfigurierte Wert ging bisher **roh** an OpenSSL. Eine Passphrase beliebiger Länge ist kein
Schlüssel; OpenSSL füllte oder kürzte stillschweigend. Jetzt entsteht daraus mit BLAKE2b ein
Schlüssel von genau 32 Byte.

*Was zu tun ist:* Nichts. Die Ableitung ist deterministisch — derselbe konfigurierte Wert ergibt
denselben Schlüssel, und Bestandsdaten im alten Format werden weiterhin mit dem rohen Wert
gelesen.

### `ext-sodium` und `ext-openssl` sind jetzt angefordert
**Seit `010-004-0002` (2026-09-10).**

Beide wurden immer gebraucht, aber `composer.json` hatte **keine einzige** `ext-*`-Angabe. Wer
sie nicht hat, erfährt es jetzt beim `composer install` statt beim ersten verschlüsselten Feld.

*Was zu tun ist:* Nichts auf üblichen Installationen — beide sind in PHP 8 Standard. Ein
minimal gebautes PHP braucht sie nachinstalliert.

## Doctrine ORM 3 (Story `010-003`)

### `pim_navItem` heisst so — wer die Tabelle umbenannt hat, benennt sie vor dem Schema-Update zurück
**Seit `000-000-0043` (2026-09-15).**

> **Überholt seit `000-000-0077`.** `Entity\NavItem` gibt es nicht mehr; das Schema-Update löscht die
> Tabelle unter jedem Namen. Wer die Daten behält, übernimmt die Entity nach `custom/` (Eintrag unter
> *API*) — dann gilt das Folgende für die eigene Klasse weiter.

**Kein Bruch, aber eine Falle, die Daten kostet.** `Entity\NavItem` liegt in `pim_navItem`, als einzige
Tabelle des Frameworks in CamelCase. Das bleibt so (entschieden mit `000-000-0043`): Ein Umbenennen
zwänge jedes Projekt mit Navigationsdaten zu einem Schritt, damit wenige es nicht mehr müssen.

Getroffen sind Projekte, deren Tabelle **anders heisst**:

- **selbst umbenannt**, wie das Bestandsprojekt UFP (`pim_nav_item`, `007-005-0003`);
- **über einen Dump gewandert.** MySQL mit `lower_case_table_names=1` (Windows) oder `2` (macOS) speichert
  `pim_navitem`. Ein Dump von dort auf einem Linux-Server (`0`) trifft `pim_navItem` nicht mehr.

Dann plant das Schema-Update, gemessen an einer Installation mit einer Zeile Navigation:

```
CREATE TABLE `pim_navItem` (…);
…
DROP TABLE pim_nav_item;
```

Die Zeilen sind danach weg. Auf den Servern selbst ist der Name kein Problem: Mit
`lower_case_table_names=0` und `=1` meldet `orm:validate-schema` nach einer frischen Installation `[OK]`
(gemessen, MySQL 8.0).

*Was zu tun ist:* **Vor** dem Schema-Update in Phase 4 nachsehen (`SHOW TABLES LIKE 'pim_nav%'`) und eine
anders benannte Tabelle zurückbenennen:

```sql
RENAME TABLE pim_nav_item TO pim_navItem;   -- bzw. pim_navitem
```

Danach meldet `--dump-sql` für die Navigation nichts mehr, und die Zeilen sind erhalten (gemessen).

### Die Proxies liegen unter `data/cache/proxies/<Kennung>`, nicht mehr in `data/cache/doctrine`
**Seit `000-000-0049` (2026-09-16).**

Doctrine schreibt seine Proxy-Klassen jetzt in ein Verzeichnis **je Version** von `areanet/contentfly`
und `doctrine/orm`. Der alte Ort `data/cache/doctrine` wird nicht mehr gelesen und darf gelöscht werden.

**Warum das kein Aufräumen ist, sondern eine Reparatur:** Seit `000-000-0047` erzeugt Doctrine eine Proxy
nur neu, wenn sie fehlt oder **älter** ist als ihre Entity-Datei. Eine Proxy aus dem Betrieb vor dem
Update ist regelmässig jünger — Dateien eines Composer-Pakets tragen das Datum ihres Archivs. Gemessen an
der Datenkopie des Bestandsprojekts UFP: Die Proxy von ORM 2 wurde geladen, und jeder Aufruf endete mit

```
500 Interface "Doctrine\ORM\Proxy\Proxy" not found
```

Mit einem Verzeichnis je Version entscheidet kein Datum mehr darüber, ob eine Proxy passt.

*Was zu tun ist:* Nichts — das neue Verzeichnis entsteht beim ersten Lauf. `data/cache/doctrine` kann
weg; ein Deployment, das `data/` behält, sollte es löschen. Alte Versionsverzeichnisse unter
`data/cache/proxies/` bleiben absichtlich liegen (eine laufende alte Installation bedient sich noch
daraus) und können jederzeit entfernt werden.

### Der Metadaten-Cache muss vor dem Upgrade geleert werden
**Seit `010-003-0002` (2026-09-10).**

**Das ist der wichtigste Punkt dieses Abschnitts, weil er sich als etwas ganz anderes tarnt.**
ORM 3 speichert Metadaten in einer anderen Form als ORM 2. Ein Cache aus ORM 2 wird gelesen und
liefert Unsinn — nicht als Fehlermeldung, sondern als:

```
TypeError: Doctrine\DBAL\Types\TypeRegistry::get(): Argument #1 ($name)
must be of type string, null given
```

Beim Umstieg im Framework selbst standen damit **191 von 268 Tests** rot; nach dem Leeren des
Caches waren es 3. Wer diesen Fehler sieht, sucht ihn beim Mapping und findet ihn dort nie.

*Was zu tun ist:* `data/cache/metadata` und `data/cache/query` leeren, bevor die neue Version
zum ersten Mal läuft — und `data/cache/doctrine` gleich mit, den Proxy-Ort bis `000-000-0049`. Siehe
`an_project/docs/deployment.md`.

### Eine Entity darf ein geerbtes Feld nicht wortgleich wiederholen
**Seit `010-003-0002` (2026-09-10).**

ORM 2 hat es stillschweigend überschrieben, ORM 3 lehnt es ab:

```
Duplicate definition of column 'id' on entity '…' in a field or discriminator column mapping.
```

Im Framework traf das `Entity\Log` (die Felder `id`, `users`, `created`, `userCreated`) und
`Entity\BaseI18n` (die Spaltendefinition von `id`). Eine Projekt-Entity, die von
`Areanet\PIM\Entity\Base` erbt und eines seiner Felder erneut deklariert, bricht genauso.

*Was zu tun ist:* Die Wiederholung streichen. Soll ein geerbtes Feld **abweichen**, bleibt nur
die Abweichung stehen — `BaseI18n` behält so seinen `#[ORM\Id]` mit
`#[ORM\GeneratedValue('NONE')]`, ohne die Spalte noch einmal zu beschreiben.

**Achtung bei geerbten Beziehungen, die absichtlich anders deklariert sind.** Beim Bestandsprojekt UFP
(`007-005-0003`) wiederholten 16 Entities `userCreated` mit `onDelete: 'CASCADE'`. Die Deklaration zu
streichen hätte das Löschverhalten **still** auf das geerbte zurückgesetzt. Der Weg ist
`#[ORM\AssociationOverrides]` an der Klasse:

```php
#[ORM\AssociationOverrides([
    new ORM\AssociationOverride(name: 'userCreated',
        joinColumns: [new ORM\JoinColumn(name: 'usercreated_id', onDelete: 'CASCADE')]),
])]
```

### `pim_log.created` bekommt einen Vorgabewert
**Seit `010-003-0002` (2026-09-10).**

Die einzige Schemaänderung des ganzen Sprungs, gemessen über zwei Installationen: **211 Spalten
und 67 Indizes, ein Unterschied.**

`Entity\Log` wiederholte `created` aus `Base`, dabei aber **ohne**
`options: ['default' => 'CURRENT_TIMESTAMP']`. Die Abweichung war nirgends begründet und sieht
nach einer unvollständigen Kopie aus. Mit dem Wegfall der Wiederholung gilt jetzt der Wert aus
`Base`, wie bei jeder anderen Tabelle.

*Was zu tun ist:* Nichts, oder ein `ALTER TABLE`. Ein Schema-Abgleich meldet die Spalte;
vorhandene Zeilen bleiben unberührt.

### Eigene DQL-Funktionen müssen `getSql(): string` deklarieren
**Seit `010-003-0002` (2026-09-10).**

`FunctionNode::getSql()` ist in ORM 3 typisiert. Eine Ableitung ohne Rückgabetyp wird **beim
Laden** abgelehnt — und das passiert erst, wenn eine Abfrage die Funktion benutzt. Im Framework
sind daran drei Berechtigungstests gescheitert, nachdem alles andere schon grün war.

*Was zu tun ist:* `: string` ergänzen, und `Lexer::T_*` durch `TokenType::T_*` ersetzen.

### Fünf Doctrine-Console-Commands sind entfallen
**Seit `010-003-0002` (2026-09-10).**

`ConvertDoctrine1Schema`, `ConvertMapping`, `EnsureProductionSettings`, `GenerateEntities` und
`GenerateRepositories`. Von sechzehn registrierten Commands laufen elf weiter.

*Was zu tun ist:* Nichts, ausser ein Projekt hat sie in einem Skript benutzt. `ConvertMapping`
und `GenerateEntities` erzeugten Mapping-Dateien und Entity-Klassen aus einer Datenbank — dieser
Weg ist in ORM 3 aufgegeben.

### `ConsoleRunner::createHelperSet()` ist entfallen
**Seit `010-003-0002` (2026-09-10).**

Betrifft `bin/cli-config.php`, den Einstiegspunkt für `vendor/bin/doctrine`. ORM 3 erwartet dort
einen `EntityManagerProvider` — dieselbe Umstellung, die `009-005-0003` für die eigene Konsole
schon gemacht hat.

*Was zu tun ist:* `return new SingleManagerProvider($app['orm.em']);`

## Doctrine (Story `009-005`)

### DBAL 2 → 3
**Seit `009-005-0001` (2026-09-09).**

`doctrine/dbal` steht auf **3.10.6**. Der Sprung war keine Wahl: `symfony/http-foundation` 7.4
kollidiert mit DBAL < 3.6, der Kernel-Schnitt hing daran. `doctrine/orm` bleibt bei **2.20.13**.

Was ein Projekt trifft, das die Verbindung direkt benutzt: `fetchAll()` ist entfernt,
`Statement::execute()` umgebaut. **Nicht** entfernt, entgegen einer verbreiteten Annahme:
`Connection::exec()`, `QueryBuilder::execute()` und `executeQuery()->rowCount()` gibt es
weiterhin, nur deprecated.

**Das häufigste Muster eines Projekts ist genau das entfernte.** `prepare()`, `bindValue()`,
`execute()`, dann `fetchAll()` oder `fetch()` **auf dem Statement** — beim Bestandsprojekt UFP
(`007-005-0004`) 362 Stellen in 39 Dateien, der grösste Block der Codemigration. In DBAL 3 gibt
`execute()` ein `Result` zurück, und nur das holt Zeilen. Rectors DBAL-Satz hilft hier nicht: Er benennt
`Statement::fetchAll()` in `fetchAllAssociative()` um, das ein Statement auch in DBAL 3 nicht hat.

*Was zu tun ist:* Das Muster mit `tools/migration/dbal3-statements.php` umbauen — erst ohne,
dann mit `--write`. Das Werkzeug macht aus `execute()` + Fetch ein `executeQuery()` mit
`fetchAllAssociative()`/`fetchAssociative()` auf dem Result (DBAL 2 holte assoziativ, die Zeilen behalten
ihre Form), aus einem `execute()` ohne Fetch ein `executeStatement()`, und lässt stehen und meldet, was
es nicht eindeutig entscheiden kann. Am UFP-Code erzeugt es genau den geprüften Stand. Übrige eigene
DBAL-Aufrufe gegen die Upgrade-Notizen von DBAL 3 lesen; die Deprecations fallen im Gate auf, bevor sie in
DBAL 4 zu Fehlern werden.

### Zahlen aus Roh-SQL bleiben Strings — auch unter PHP 8.1+
**Seit `000-000-0044` (2026-09-15).**

**Kein Bruch, sondern einer, der abgefangen ist** — erwähnenswert, weil er ohne das Framework jedes
Bestandsprojekt still träfe. Seit PHP 8.1 gibt `pdo_mysql` bei emulierten Prepared Statements, die
DBAL benutzt, Ganz- und Fliesskommazahlen als `int`/`float` zurück. Auf PHP 7.4 kam `"0"`, auf 8.3
käme `0`. Gemessen am Bestandsprojekt UFP (`007-005-0004`): Dessen Frontend vergleicht an rund 50
Stellen strikt mit `'1'`/`'0'` — keiner dieser Vergleiche wirft, alle würden nur falsch.

Beide Verbindungen, `$app['db']` und `$app['database']`, setzen deshalb
`PDO::ATTR_STRINGIFY_FETCHES` (`Classes\Database\ConnectionDefaults`). Eine Antwort aus Roh-SQL hat
dieselben JSON-Typen wie unter Contentfly 1.x — auch `/api/tree2`, das die Rohwerte der Spalten
durchreicht (`"isActive":"1"`). **Entities sind nicht betroffen:** Doctrine wandelt
über den gemappten Typ, ein `integer`-Feld bleibt `int`, ein `boolean`-Feld `bool`.

*Was zu tun ist:* Nichts. Wer eine **eigene** Verbindung mit `DriverManager` öffnet, setzt
`'driverOptions' => ConnectionDefaults::driverOptions()`, sonst gilt dort das Verhalten von PHP 8.1.
Wer native Typen will, wandelt im eigenen Code.

### Neue Ids sind UUID v4 statt v1
**Seit `009-005-0002` (2026-09-09).**

`Entity\Base` trug `@ORM\GeneratedValue(strategy="UUID")`. Doctrines Generator fragte die
Datenbank über `AbstractPlatform::getGuidExpression()` — die es in DBAL 3 nicht mehr gibt; **173
von 249 Tests** fielen daran. Die Id-Erzeugung läuft jetzt in PHP: eigener
`Areanet\PIM\Classes\ORM\Id\UuidGenerator`, `APPCMS_ID_STRATEGY` auf `CUSTOM`,
`@ORM\CustomIdGenerator` an `Base` und `Log`.

**Version 4, nicht 1.** Es gab nie eine einheitliche Herkunft — `Api.php` erzeugt für
i18n-Objekte seit jeher selbst `uuid4()`. Und eine v1-UUID trägt die MAC-Adresse des Servers und
den Erzeugungszeitpunkt, die damit in jeder API-Antwort stehen.

**Für vorhandene Daten ändert das nichts.** Beide Formen sind 36 Zeichen und stehen in derselben
Spalte.

*Was zu tun ist:* Eine eigene Entity mit `@ORM\GeneratedValue(strategy="UUID")`, die **nicht**
von `Base` erbt, bricht — sie braucht denselben `@ORM\CustomIdGenerator`. Wer sich auf die
Sortierbarkeit von v1-Ids verlassen hat, kann das nicht mehr.

### Die Konsole nimmt Provider statt eines `HelperSet`
**Seit `009-005-0003` (2026-09-09).**

Bis DBAL 2 kamen die Verbindungen über ein `HelperSet` mit `ConnectionHelper`. Die Klasse gibt es
nicht mehr; `bin/console.php` starb daran in Zeile 14 und mit ihr die **ganze** Konsole,
`appcms:install` eingeschlossen. Jetzt `ConnectionProvider` und `EntityManagerProvider`.

Von achtzehn Doctrine-Commands laufen fünfzehn unverändert, zwei brauchen einen Provider, und
**`ImportCommand` ist entfallen** — entfernt, nicht auskommentiert.

*Was zu tun ist:* Ein eigener Command, der sich die Verbindung aus dem `HelperSet` holt, holt sie
jetzt aus dem Provider.

### Abfrage- und Metadaten-Cache teilen sich nicht mehr einen Namensraum
**Seit `009-003-0002` (2026-09-09).**

`new ApcCache('query')` — die Klasse hat gar keinen Konstruktor, das Argument wurde verworfen.
Vier Cache-Instanzen liefen deshalb in **einem** Namensraum; die Trennung war seit Jahren
beabsichtigt und griff nicht. Jetzt über `setNamespace()`.

*Was zu tun ist:* Nichts, ausser den Cache beim Deployment einmal leeren. Vorhandene Einträge
liegen unter den alten Schlüsseln.

### `Entity\Serializable::getId()` ist abstrakt
**Seit `009-003-0002` (2026-09-09).**

Die Klasse benutzte die Methode, ohne sie zu deklarieren. Jetzt steht die Bedingung im Code, und
eine Ableitung ohne `getId()` fällt **beim Laden** auf statt beim ersten Aufruf.

*Was zu tun ist:* Eine eigene Klasse, die von `Areanet\PIM\Entity\Serializable` erbt, ohne
über `Entity\Base` zu gehen, braucht ein eigenes `getId()`.

### `Classes\ApnsPHP\Log\NoLogger` ist gelöscht
**Seit `009-003-0002` (2026-09-09).**

Die Klasse implementierte eine Schnittstelle, die es im Baum nicht gibt — sie hätte bei der
ersten Instanziierung einen Fatal geworfen und war nirgends referenziert.

*Was zu tun ist:* Nichts, ausser ein Projekt hat sie selbst benutzt; dann war sie dort schon
kaputt.


## Annotationen

Die `@PIM`-Annotationen sind mit Epic `012` stark reduziert worden. Die vollständige Liste
dessen, was entfallen ist und wodurch es ersetzt wird, steht in
`an_project/docs/pim-annotationen-migration.md` — sie ist die Grundlage für die Rector-Regel
aus Epic `007`.
