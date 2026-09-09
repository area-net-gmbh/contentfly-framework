<!-- PURPOSE: Vollständige Liste der in Story 012-005 entfernten @PIM-Annotationen und
     Config-Felder. Grundlage der Rector-Regel und des Migrationsleitfadens aus Epic 007. -->

# `@PIM`-Annotationen — was entfallen ist

Mit der PIM-Oberfläche (Epic `012`) ist der Teil der `@PIM`-Annotationen weggefallen, der
Eingabemasken beschrieb. **Hart, ohne Duldungsphase** — entschieden am 2026-09-04. Ein
Bestandsprojekt, dessen Entities die unten gelisteten Annotationen oder Felder tragen, muss
sie entfernen; ein stehengebliebenes Feld ist kein toleriertes Relikt, sondern ein Fehler:
`Doctrine\Common\Annotations\Annotation::__get()` wirft eine `BadMethodCallException`, und
der `AnnotationReader` bricht schon beim Einlesen ab, wenn eine Annotation ein Feld trägt,
das ihre Klasse nicht kennt.

Diese Datei ist die Grundlage der Rector-Regel und des Migrationsleitfadens aus Epic
`007-000-0000`.

## 1. Entfallene Annotationen (7)

Ersatzlos zu löschen — sie beschrieben ausschließlich Formularelemente.

| Annotation | Ersatz / Auswirkung |
|---|---|
| `@PIM\Rte(toolbar=…, extend=…)` | keiner. Eine `text`-Spalte wird weiterhin als `textarea` geführt, `encoded` funktioniert unverändert. |
| `@PIM\Textarea(lines=…)` | keiner. `TextareaType` greift schon über `@ORM\Column(type="text")`. |
| `@PIM\Datetime(format=…)` | keiner. Die API liefert Datumswerte unverändert als `LOCAL`, `LOCAL_TIME`, `ISO8601`, `TIMESTAMP`. |
| `@PIM\Time(format=…)` | keiner. Zeitwerte kommen fest als `H:i` (`TimeType::DEFAULT_FORMAT`). **Ein Projekt, das hier ein abweichendes Format gesetzt hatte, ändert damit seine API-Ausgabe** — der einzige Punkt der Liste mit sichtbarer Wirkung. |
| `@PIM\Password()` | keiner. Das Feld wird als `string` geführt; es hing kein Hashing und kein Ausgabe-Schutz daran. |
| `@PIM\MatrixChooser(…)` | keiner. Hatte im Framework nie eine Type-Klasse. |
| `@PIM\EntitySelector()` | keiner. Das Feld wird als `string` geführt. |

Mitentfernt: `RteType`, `PasswordType`, `EntitySelectorType`. Ein Projekt, das eine davon in
`APP_SYSTEM_TYPES` oder `APP_CUSTOM_TYPES` aufführt, streicht den Eintrag — sonst bricht der
Start mit `contentfly_type_class_not_found`.

## 2. Gebliebene Annotationen (5)

Sie tragen Datenverhalten und bleiben unverändert gültig: `@PIM\Config`, `@PIM\Select`,
`@PIM\Virtualjoin`, `@PIM\Permissions`, `@PIM\I18nPermissions` (dazu `@PIM\ManyToMany`).

`@PIM\Checkbox` und `@PIM\Radio` bleiben ebenfalls — **aber reduziert**:

| Annotation | entfallenes Feld |
|---|---|
| `@PIM\Checkbox` | `horizontalAlignment`, `columns` |
| `@PIM\Radio` | `horizontalAlignment`, `columns`, `select` |

`group` bleibt in beiden: es benennt die `OptionGroup`, die beim Aufbau des Schemas angelegt
wird.

## 3. Entfallene Felder von `@PIM\Config` (14)

Von 24 Feldern bleiben 10. Diese 14 sind zu streichen — auf Klassen- wie auf Eigenschaftsebene:

`viewMode` · `showInList` · `listShorten` · `hide` · `label` · `tab` · `tabs` · `sort` ·
`isDatalist` · `isSidebar` · `lines` · `accept` · `readonly` · `filter`

Zu den beiden Feldern, die die Story einzeln geprüft haben wollte:

- **`readonly`** — **UI-Hinweis, kein Schreibschutz.** Der Wert wurde in das Schema
  geschrieben (`Type::processSchema()`, `Api::getSchema()`) und dort von niemandem gelesen;
  die API hat auf ein `readonly`-Feld nie anders reagiert. Wer einen echten Schreibschutz
  braucht, baut ihn im Controller oder über `Permission`.
- **`filter`** — **schon vorher wirkungslos.** Weder `Api`, `ApiController` noch eine
  Type-Klasse hat das Feld je ausgelesen. Der gleichnamige Schema-Key wurde nur mit dem
  Leerstring vorbelegt.

## 4. Gebliebene Felder von `@PIM\Config` (10)

| Feld | Warum es bleibt |
|---|---|
| `excludeFromSync` | nimmt die Entity aus der Sync-API — `Classes/Api.php`; seit `000-000-0013` der **einzige** Mechanismus dafür, sieben PIM-Entities setzen ihn |
| `encoded` | steuert die Verschlüsselung — `StringType`, `TextareaType` |
| `isFilterable` | gibt die Eigenschaft für API-Filter frei — `Classes/Type.php` |
| `unique` | Datenmodell |
| `type` | aktuell nur `tree`; steuert die Baumabfragen in `Classes/Api.php` |
| `i18n_universal` | Datenmodell (sprachübergreifende Felder) |
| `sortRestrictTo` | Sortierung der API-Antworten — `JoinBidirectionalType` wertet es aus |
| `sortBy`, `sortOrder` | **Angaben für den Client, keine Sortierung der API-Antworten.** Die Zeile darüber stand bis `000-000-0013` auch für diese beiden und war falsch: `Api::getList()` wertet sie nicht aus, sortiert wird allein nach dem `order`-Parameter des Requests, und fehlt der, bleibt es bei `ORDER BY id DESC`. Sie bleiben, weil sie die vom Projekt gemeinte Reihenfolge seiner eigenen Daten benennen und ein Client daraus sein `order` bauen kann. Begründung gegen das Anwenden: siehe `000-000-0013` |
| `labelProperty` | **Abweichung von der ursprünglichen Streichliste.** Der Wert landet als `modelLabel` im `Log` — er wird also *persistiert* — und bestimmt, welches Feld verjointer Objekte die API im `partial`-Select mitliefert. Ohne ihn verlieren Log-Einträge ihr Label und verjointe Objekte im Payload alles außer der `id`. Entschieden am 2026-09-07. |

## 5. Entfallene Plugin-Schnittstelle

Aus `Areanet\PIM\Classes\Plugin` sind die Frontend-Anteile entfallen — Plugins konnten der
Oberfläche einen eigenen Asset-Ordner beisteuern:

| Entfallen | Ersatz |
|---|---|
| `Plugin::getFrontendPath()` | keiner. Gab den über Symlink freigegebenen Pfad `/plugins/<KEY>/Frontend` zurück. |
| `Plugin::useFrontend()` | keiner. Legte den Symlink im Webroot an; war bereits vor diesem Umbau ein leerer Rumpf. |

**Was bleibt, bleibt unverändert:** `useORM()` samt Annotation-Driver für die Entities des
Plugins, `getEntities()`, `registerPluginType()`, `init()`, die Composer-Einbindung des Plugins
und die Registrierung von Console-Commands über `$app['consoleManager']`. Ein Plugin, das
Entities, Types, Services und Commands beisteuert, läuft ohne Änderung weiter; nur ein Plugin
mit eigenem `Frontend/`-Ordner verliert dessen Anbindung.

Ebenfalls entfallen, ohne Ersatz und ohne bekannten Verbraucher:
`TypeManager::getCustomTypes()`, `getSystemTypes()`, `getPluginTypes()`, der `$mode`-Parameter
von `TypeManager::getTypes()` samt der Konstanten `CUSTOM`/`PLUGINS`/`SYSTEM`, und der leere
Haken `Type::renderJSON()`.

## 6. Entfallene Konfiguration

`FRONTEND_SHOW_ID_IN_LIST` und `FRONTEND_SHOW_OWNER_IN_LIST` samt der daraus abgeleiteten
Konstanten `APP_CMS_SHOW_ID_IN_LIST` und `APP_CMS_SHOW_OWNER_IN_LIST`. Sie kamen
ausschließlich in `showInList` vor. Ein Projekt, das sie in `custom/config.php` setzt,
entfernt die Zeilen.

### Nachtrag aus `000-000-0010`: acht weitere `FRONTEND_*`-Felder

Story `012-005-0004` hat nur die beiden oben genannten mitgezogen — jene, die sie selbst
verwaist hatte — und den Rest ausdrücklich als eigenen Task vermerkt. Der ist eingelöst:

| Feld | warum es entfällt |
|---|---|
| `FRONTEND_UI` | wurde nirgends gelesen |
| `FRONTEND_URL` | wurde nirgends gelesen |
| `FRONTEND_CUSTOM_LOGIN_BG` | wurde nirgends gelesen |
| `FRONTEND_TITLE` | nur im `frontend`-Block des Schemas |
| `FRONTEND_WELCOME` | nur im `frontend`-Block des Schemas |
| `FRONTEND_LOGIN_REDIRECT` | nur im `frontend`-Block des Schemas |
| `FRONTEND_FORM_IMAGE_SQUARE_PREVIEW` | nur im `frontend`-Block des Schemas |
| `FRONTEND_CUSTOM_LOGO` | `frontend`-Block **und** `/api/config` |

**Zwei bleiben, obwohl sie `FRONTEND_` heissen.** Die Benennung ist ein Erbe, kein Hinweis auf
den Zweck:

- **`FRONTEND_ITEMS_PER_PAGE`** — Standard-Seitengrösse der Pagination von `/api/list`. Reines
  API-Verhalten; wer es entfernt, ändert, wie viele Objekte ein Client ohne eigene Angabe
  bekommt.
- **`FRONTEND_CUSTOM_NAVIGATION`** — schaltet einen datengetriebenen Zweig frei, der die
  Entities `PIM\Nav` und `PIM\NavItem` ausliest. Beide gibt es, sie gehören zum Datenmodell.

*Was zu tun ist:* Die acht Zeilen aus `custom/config.php` entfernen. Sie werden nicht mehr
gelesen und stehen sonst als wirkungslose Schalter herum.
