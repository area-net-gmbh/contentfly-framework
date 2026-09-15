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
Benennung ist ein Erbe, kein Hinweis auf ihren Zweck.

*Was zu tun ist:* Die acht Zeilen aus `custom/config.php` entfernen; sie werden nicht mehr
gelesen.

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

## API

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
Daten) und `languages` (aus `APP_LANGUAGES`, bestimmt die Hauptsprache).

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

### Der Statuscode steht im Fehlerrumpf unter `status`
**Seit `000-000-0006` (2026-09-09).**

Für alles, was weder `ContentflyException` noch `ContentflyI18NException` ist — also für jeden
PHP-Fehler — stand der Code als **schlüsselloser** Eintrag im Rumpf und kam deshalb als `"0"`
beim Client an. In den beiden anderen Zweigen hiess das Feld schon immer `status`.

*Was zu tun ist:* `status` lesen statt `0`.

### Ein Fehler beim Start antwortet mit JSON statt mit leerem Rumpf
**Seit `000-000-0024` (2026-09-15).**

Eine Ausnahme, die fällt, bevor der Kernel steht — ein unerfüllbarer `APP_CACHE_DRIVER`, eine
fehlende `custom/config.php` —, erreichte keinen Fehlerhandler. Die Antwort war **HTTP 500 mit
0 Byte**; die Meldung stand nur im Serverlog.

Jetzt fängt `Kernel\Start::web()` sie ab und antwortet im Format der übrigen Fehlerantworten:
`message`, `type`, `status`, bei `APP_DEBUG` zusätzlich `debug` mit Datei, Zeile und Trace. Ohne
`APP_DEBUG` ersetzt die Antwort Projekt- und Paketverzeichnis durch `<project>` bzw. `<package>`.
Die Meldung steht weiterhin im Serverlog, dort mit vollen Pfaden. Die Konsole ist unverändert:
Dort endet ein Startfehler wie bisher mit der Ausnahme auf `stderr`.

*Was zu tun ist:* Nichts. Wer die leere 500 als Zeichen für eine Fehlkonfiguration ausgewertet
hat, liest jetzt `message`.

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

### Die übrigen Envelopes ändern sich erst mit dem Release
**Angekündigt mit `000-000-0014` — noch keine Änderung.**

Die sieben Antwortformen der API werden auf `data`, `errors`, `meta` gebracht. Der Umbau gehört
in Epic `011` und nicht in `009`: Die Charakterisierungstests aus Epic `008` sind die
Abnahmegrundlage des Kernel-Wechsels, und wären beide Seiten des Vergleichs gleichzeitig neu,
liesse sich eine Abweichung nicht mehr dem Umbau oder der Absicht zuordnen.

Die vollständige Tabelle *vorher → nachher* je Endpunkt steht in
`an_project/docs/api-envelope.md`. Sie ist die Vorlage für den Migrationsleitfaden aus Epic
`007` — wer heute einen Client baut, kann sich darauf einstellen.

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

### `$app->redirect()` und `$app->stream()` sind entfallen
**Seit `009-001-0004` (2026-09-09).**

Silex' Rümpfe lauteten wörtlich `return new RedirectResponse(...)` beziehungsweise
`return new StreamedResponse(...)`.

*Was zu tun ist:* Genau diese beiden Klassen direkt zurückgeben.

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

### `listTokens` liefert den Hash, nicht den Token
**Seit `013-001-0004` (2026-09-10).**

Das Feld `token` in der Antwort von `listTokens` — und in der von `addToken` bei einem späteren
Aufruf — ist der gespeicherte Hash. **Der Token selbst lässt sich nicht mehr nachschlagen, auch
nicht vom Betreiber.** Er wird genau einmal zurückgegeben: in der Antwort auf `addToken`, die
ihn anlegt, beziehungsweise auf `/auth/login`.

Das Feld bleibt stehen, weil es die Zeile eindeutig benennt und weil, wer einen Token in der
Hand hält, ihn selbst hashen und so seinen Eintrag finden kann. Ein Client, der den Wert
versehentlich als Token vorzeigt, bekommt `401` — er fällt zu, nicht auf.

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
5. **Den Provider registrieren:** `$app['loginProviders']->register('<name>', fn () => new …)`
   in `custom/app.php`. Dieser `<name>` ist ab jetzt der Wert, den ein Client als `loginManager`
   schickt.
6. **Die Clients umstellen:** Sie schicken den Namen statt des Klassennamens.

`custom/Classes/Authentication/ExampleProvider.php` führt alles davon an einem lauffähigen Beispiel
vor.

### Der Request-Parameter `loginManager` wählt keine Klasse mehr aus
**Seit `013-004-0001` (2026-09-10).**

Er benennt einen Eintrag in der `LoginProviderRegistry`. Ein Klassenname steht dort nicht und wird
abgewiesen — wie jeder andere unbekannte Name, und **ohne** auf die Passwortprüfung
zurückzufallen.

Der Parametername bleibt `loginManager`: Bestandsclients schicken ihn so, und ihn umzubenennen
wäre ein Bruch am Draht ohne Gewinn. Wenn er umbenannt wird, dann mit dem Rest der Migration.

*Was zu tun ist:* Schritt 5 und 6 oben. Solange nichts registriert ist, ist jeder
`loginManager`-Wert unbekannt und die Anmeldung darüber scheitert.

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

Wie ein Projekt diesen Schritt gebündelt bekommt, entscheidet Epic `007`.

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
zum ersten Mal läuft. Siehe `an_project/docs/deployment.md`.

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

*Was zu tun ist:* Eigene DBAL-Aufrufe gegen die Upgrade-Notizen von DBAL 3 lesen. Die
Deprecations fallen im Gate auf, bevor sie in DBAL 4 zu Fehlern werden.

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
