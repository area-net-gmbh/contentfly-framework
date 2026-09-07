---
id: 008-004-0005
title: Die Vorlage custom/ läuft mit
status: todo
depends_on: []
---

# Die Vorlage custom/ läuft mit

## Context
Epic `008` verlangt, dass „ein Projekt auf Basis dieses Frameworks funktioniert" ebenfalls
abgesichert ist. `custom/` **ist** dieses Projekt: die Vorlage, an der sich jedes neue und
jedes migrierende Projekt orientiert (Epic `007`).

Bricht die Vorlage beim Kernel-Umbau, bricht sie für alle.

## Umfang

### A — Die Beispiel-Entity über die generischen Endpunkte
`custom/Entity/Core/Example.php` ist nach `012-005-0004` die Referenz für den Zielzustand der
Annotationen. Zu prüfen:
- Sie erscheint im API-Schema unter `Core\Example`.
- Sie ist über `/api/insert`, `/api/single`, `/api/list` und `/api/delete` benutzbar — also
  über **dieselben** Endpunkte wie die Framework-Entities, ohne Sonderbehandlung.
- Ihre verbliebenen Annotationen wirken: `@PIM\Select(options=…)` auf dem Statusfeld.

Das ist zugleich der Nachweis, dass die Unterverzeichnis-Struktur (`custom/Entity/Core/`)
funktioniert — der Punkt, an dem `Api::getAll()` bis `000-000-0007` gescheitert ist.

### B — Der Beispiel-Endpunkt
`POST api/v1/example/bootstrap` aus `custom/app.php`. In `008-003-0004` bereits abgedeckt: Er
antwortet ohne Token und im **eigenen** Envelope der Vorlage (`success`, `status`, `i18n`,
`data`, `errors`, `meta`, `timestamp`). Hier kommt der inhaltliche Teil dazu — was er liefert
und dass der `ApiResponseService` der Vorlage dabei die Form bestimmt.

### C — Die Middleware
`custom/app.php` registriert einen `before`- und einen `after`-Hook. Der `Referrer-Policy`-Header
ist in `008-003-0004` geprüft; hier ergänzt: dass der `before`-Hook läuft und in welcher
Reihenfolge — die Reihenfolge ist laut Kommentar in der Vorlage „kein Detail", weil
Sicherheits-Hooks aufeinander aufbauen.

### D — Was die Vorlage nicht kann
`custom/Command/ExampleCommand.php` erbt von `Symfony\…\Command` statt von `CustomCommand` und
ist deshalb **nicht** über den `ConsoleManager` registrierbar — die Vorlage selbst vermerkt das
in `custom/app.php`. Festzuhalten, damit beim Kernel-Umbau nicht versehentlich „repariert" wird,
was bewusst so steht.

## Acceptance criteria
- [ ] `Core\Example` erscheint im API-Schema und ist über die generischen Endpunkte anlegbar,
      lesbar und löschbar.
- [ ] Die `@PIM\Select`-Annotation der Vorlage wirkt — die Optionen stehen im Schema.
- [ ] Der Beispiel-Endpunkt liefert den Envelope der Vorlage; sein Inhalt ist festgehalten.
- [ ] Der `before`-Hook ist nachweisbar aktiv.
- [ ] Der Sonderfall `ExampleCommand` ist festgehalten, mit Verweis auf den Kommentar in
      `custom/app.php`.
- [ ] Die Testdaten der Beispiel-Entity werden abgeräumt.

## Verification
Mehrere vollständige Läufe; die Tabelle der Beispiel-Entity ist danach auf dem Ausgangsstand.
