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

## Annotationen

Die `@PIM`-Annotationen sind mit Epic `012` stark reduziert worden. Die vollständige Liste
dessen, was entfallen ist und wodurch es ersetzt wird, steht in
`an_project/docs/pim-annotationen-migration.md` — sie ist die Grundlage für die Rector-Regel
aus Epic `007`.
