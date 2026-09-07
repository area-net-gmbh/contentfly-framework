---
id: 008-001-0005
title: /api/translations und /api/query
status: todo
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
- [ ] `/api/translations`: Form der Antwort für ein mehrsprachiges Objekt ist festgehalten.
- [ ] `i18n_universal` ist geprüft — ein so markiertes Feld trägt über alle Sprachvarianten
      denselben Wert. Verweis auf `012-005-0002` im Kommentar.
- [ ] Das Verhalten für eine Entity ohne i18n ist festgehalten.
- [ ] `/api/query`: alle drei Berechtigungsfälle sind geprüft — Admin, berechtigte Gruppe,
      unberechtigte Gruppe.
- [ ] Der Fall „`select` oder `from` fehlt" ist festgehalten.
- [ ] Beide Routen sind ohne Token abgewiesen.

## Verification
```sh
CONTENTFLY_TEST_BASE_URL=http://127.0.0.1:8145 \
CONTENTFLY_TEST_ADMIN_PASS=dev-only-secret \
  ./custom/vendor/bin/phpunit --testsuite integration
```
Für das Gating von `/api/query` braucht es einen Nicht-Admin-Benutzer in einer Gruppe mit und
einer ohne `apiQueryEnabled` — der Aufbau gehört in die Testdaten-Helfer aus `008-001-0001`.
