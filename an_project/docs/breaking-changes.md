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

## Annotationen

Die `@PIM`-Annotationen sind mit Epic `012` stark reduziert worden. Die vollständige Liste
dessen, was entfallen ist und wodurch es ersetzt wird, steht in
`an_project/docs/pim-annotationen-migration.md` — sie ist die Grundlage für die Rector-Regel
aus Epic `007`.
