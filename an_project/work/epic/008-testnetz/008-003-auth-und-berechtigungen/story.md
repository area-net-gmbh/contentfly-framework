---
id: 008-003-0000
title: Auth, Berechtigungen und Routen-Absicherung
status: done
depends_on: []
---

# Auth, Berechtigungen und Routen-Absicherung

## Goal
Anmeldung und Token sind seit `012-004-0003` mit zehn Tests abgedeckt. Was fehlt, ist die Schicht
darunter: **wer was sehen und ändern darf**. Das `Permission`-System entscheidet pro Entity und
Benutzergruppe, und es entscheidet über Daten — beim Kernel-Tausch ist es die Stelle, an der ein
Fehler nicht auffällt, sondern still zu viel herausgibt.

## Umfang

### Was schon steht — und nicht wiederholt wird
`tests/Integration/Api/AuthApiTest.php` deckt bereits ab: Anmeldung, falsches Passwort,
unbekannter Benutzer, Zugriff ohne / mit falschem / mit gültigem Token, Abmelden entwertet den
Token, Tokens werden pro Anmeldung neu erzeugt, plus ein Regressionsschutz gegen eine
zurückkehrende PHP-Session. Diese Story **ergänzt**, sie schreibt nichts davon neu.

### A — Das Permission-Gating
`Areanet\PIM\Classes\Permission` kennt vier Stufen, die pro Entity und Gruppe gesetzt werden:

| Stufe | Bedeutung |
|---|---|
| `ALL` | alles sichtbar |
| `GROUP` | nur Objekte der eigenen Gruppe |
| `OWN` | nur eigene Objekte (`userCreated`) oder solche, in denen der Benutzer eingetragen ist |
| — | kein Zugriff |

Zu charakterisieren, je Stufe und je Operation (`isReadable`, `isWritable`, `isDeletable`,
`canExport`, `getExtended`):
- Was eine Liste zurückgibt, wenn die Stufe greift — **gefiltert oder Fehler?**
- Wie ein verjointes Objekt aussieht, das nicht gelesen werden darf: Die Typ-Klassen liefern
  dafür `array('id' => …, 'pim_blocked' => true)` statt des Objekts. Dieses Verhalten ist über
  `JoinType`, `RadioType`, `OnejoinType`, `CheckboxType` und `MultijoinType` verteilt und
  nirgends festgehalten.
- Ob ein Schreibversuch ohne Recht mit 403 endet oder anders.

### B — `I18nPermissions`
Gruppen tragen Sprachrechte (`Group::getLanguages()`). Festzuhalten, welche Sprachvarianten ein
Benutzer sieht und was passiert, wenn er in eine Sprache schreibt, für die er kein Recht hat.

### C — Die Absicherung der Routen
Der Provider bindet jede Route entweder mit oder ohne den `$checkAuth`-Hook. Der Schalter heißt
**`Route::$isSecure`** und liegt in
`Classes/Controller/Provider/Base/CustomControllerProvider.php` — nicht, wie der Epic-Text
vermutet, als `_secured` im `RouteManager`; die Bezeichnung existiert im Code nicht (festgestellt
in `012-006-0003`).

Zu prüfen ist die Semantik, auf die sich Epic `009` verlässt:
- Eine mit `isSecure = true` gebundene Route weist ohne gültigen Token mit 401 ab.
- Eine mit `isSecure = false` gebundene Route ist ohne Token erreichbar — das ist der Weg, über
  den ein Projekt öffentliche Endpunkte baut (`custom/app.php` zeigt es an
  `api/v1/example/bootstrap`).
- Die Reihenfolge der `before`-Hooks aus `custom/app.php` bleibt gewahrt.

### D — Der Master-Passwort-Befund
`APP_MASTER_PASSWORD` erlaubt heute den Login als **jeder** Benutzer
(`AuthController`). Story `013-001` entfernt das ersatzlos. Solange es existiert, gehört es
charakterisiert — mit einem Testkommentar, der auf `013-001` verweist, damit der Test dort
bewusst umgedreht statt versehentlich gelöscht wird.

## Fertig, wenn
- Für jede Permission-Stufe und jede der fünf Operationen ist das heutige Verhalten durch einen
  Test festgehalten.
- Das `pim_blocked`-Verhalten verjointer Objekte ist für mindestens zwei Typ-Klassen geprüft.
- Die Sprachrechte aus `I18nPermissions` sind lesend und schreibend abgedeckt.
- Die `isSecure`-Semantik ist in beiden Richtungen geprüft — gesicherte Route ohne Token weist
  ab, ungesicherte Route antwortet.
- Das Master-Passwort-Verhalten ist festgehalten und im Test auf `013-001` verwiesen.
- Die vorhandenen zehn Auth-Tests bleiben unverändert grün.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [x] 008-003-0001 — Testfundament für Berechtigungen
- [x] 008-003-0002 — Lesen: isReadable in allen vier Stufen
- [x] 008-003-0003 — Schreiben und Löschen: isWritable, isDeletable
- [x] 008-003-0004 — Sprachrechte und die Routen-Absicherung
- [x] 008-003-0005 — Master-Passwort und die nicht durchgesetzten Rechte
