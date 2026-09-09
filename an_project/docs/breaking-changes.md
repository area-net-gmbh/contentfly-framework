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
