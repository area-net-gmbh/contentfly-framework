---
id: 008-001-0005
title: /api/translations und /api/query
status: done
depends_on: [008-001-0002]
---

# /api/translations und /api/query

## Context
Die beiden verbliebenen Leseendpunkte, und die beiden ungewöhnlichsten: `translations` bedient
die Mehrsprachigkeit, `query` erlaubt freies Abfragen und ist deshalb der einzige Leseendpunkt
mit einer eigenen Berechtigungsprüfung.

## Umfang

### `/api/translations`
Liefert die Sprachvarianten eines Objekts. Dahinter steht der `BaseI18n`-Zweig des Datenmodells
und `i18n_universal` — ein weiteres der zehn Felder, die `012-005-0002` als datenrelevant
behalten hat.

Festzuhalten:
- Die Form der Antwort für ein Objekt mit mehreren Sprachvarianten.
- **`i18n_universal`**: Ein so markiertes Feld hat über alle Sprachen denselben Wert. Zu prüfen,
  dass eine Änderung in einer Sprache in den anderen sichtbar ist — das ist der ganze Zweck des
  Flags.
- Verhalten bei einer Entity ohne i18n.

### `/api/query`
Freies Abfragen mit `select`/`from`. **Der einzige Leseendpunkt mit eigenem Gating:**

```php
if(!$this->app['auth.user']->getIsAdmin()){
    $group = $this->app['auth.user']->getGroup();
    if(!$group || $group->getApiQueryEnabled() != 'enabled'){
        throw new ContentflyException(..., 'api::query');
    }
}
```

Drei Fälle sind festzuhalten: Admin (darf), Nicht-Admin in einer Gruppe mit
`apiQueryEnabled = 'enabled'` (darf), Nicht-Admin ohne dieses Recht (darf nicht). Der dritte ist
der wichtige — ein Endpunkt für freie Abfragen, dessen Gating beim Kernel-Umbau unbemerkt
wegfällt, ist eine offene Datenbank.

Ebenfalls festzuhalten: das Verhalten bei fehlendem `select` oder `from` — `Api::getQuery()`
prüft beides.

## Acceptance criteria
- [x] `/api/translations`: **kein mehrsprachiges Objekt verfügbar** — die Vorlage konfiguriert keine Sprachen. Festgehalten ist stattdessen das Verhalten ohne i18n plus ein Test auf diese Vorbedingung.
- [x] `i18n_universal`: **heute nicht beobachtbar.** Ohne konfigurierte Sprachen und ohne konkrete `BaseI18n`-Entity gibt es keine Sprachvarianten zu vergleichen. Im Testkommentar festgehalten.
- [x] Das Verhalten für eine Entity ohne i18n ist festgehalten.
- [x] `/api/query`: alle drei Berechtigungsfälle sind geprüft — Admin, berechtigte Gruppe, unberechtigte Gruppe.
- [x] Der Fall „`select` oder `from` fehlt" ist festgehalten — beide einzeln.
- [x] Beide Routen sind ohne Token abgewiesen.

## Verification
```sh
CONTENTFLY_TEST_BASE_URL=http://127.0.0.1:8145 \
CONTENTFLY_TEST_ADMIN_PASS=dev-only-secret \
  ./custom/vendor/bin/phpunit --testsuite integration
```
Für das Gating von `/api/query` braucht es einen Nicht-Admin-Benutzer in einer Gruppe mit und
einer ohne `apiQueryEnabled` — der Aufbau gehört in die Testdaten-Helfer aus `008-001-0001`.

## Ergebnis — 9 Tests in `tests/Integration/Api/QueryApiTest.php`

Gesamtsuite: **68 Tests, 159 Assertions**, vier Läufe grün.

### `/api/query`: das Gating ist isoliert nachgewiesen

Alle drei Fälle stehen — Admin, Nicht-Admin mit `apiQueryEnabled = 'enabled'`, Nicht-Admin mit
`'disabled'`. Das war nicht auf Anhieb sauber: Der erste Versuch scheiterte, weil ein
Nicht-Admin **zwei** Hürden nimmt. Vor dem `apiQueryEnabled`-Gate greift die Leseprüfung der
Entity, und ohne `Permission`-Zeile endet beides in `contentfly_general_access_denied`. Der
Testaufbau legt deshalb eine Berechtigung für `PIM\Tag` mit an — erst dadurch misst der Test
das Gate, das er messen soll, und nicht das davor.

Nebenbei festgehalten: `/api/query` ist der **einzige** Endpunkt, der die Anfrageparameter in
der Antwort zurückgibt (`params` neben `ts`, `data`, `version`, `hash`).

### `/api/translations`: heute nicht sinnvoll prüfbar

`APP_LANGUAGES` ist in der Vorlage leer, und es gibt **keine konkrete `BaseI18n`-Entity** — nur
die abstrakten Basisklassen `BaseI18n`, `BaseI18nSortable`, `BaseI18nTree`. Damit existiert
kein mehrsprachiges Objekt, an dem sich `i18n_universal` beobachten ließe.

Festgehalten ist deshalb, was gilt: Der Endpunkt endet für eine Entity ohne i18n im Fehler,
und ein eigener Test hält die **Vorbedingung** fest (`/api/config` meldet keine Sprachen).
Schaltet ein Projekt oder die Vorlage Mehrsprachigkeit ein, schlägt dieser Test an — und dann
gehört die eigentliche `i18n_universal`-Zusicherung ergänzt. Das ist ehrlicher, als ein Feld
zu testen, dessen Wirkung im Testaufbau gar nicht entstehen kann.

## Verification
- [x] Vier vollständige Läufe grün bei zufälliger Ausführungsreihenfolge.
- [x] Ein Lauf ohne `CONTENTFLY_TEST_BASE_URL` überspringt sauber (62 übersprungen, 6 Unit-Tests laufen).
- [x] Die Testdatenbank ist danach leer — auch die angelegten Gruppen, Benutzer und
      Berechtigungen sind abgeräumt; übrig bleibt nur der Installations-Admin.
