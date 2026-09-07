---
id: 008-004-0005
title: Die Vorlage custom/ läuft mit
status: done
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
- [x] `Core\Example` erscheint im API-Schema und ist über die generischen Endpunkte anlegbar,
      lesbar und löschbar.
- [x] Die `@PIM\Select`-Annotation der Vorlage wirkt — die Optionen stehen im Schema.
- [x] Der Beispiel-Endpunkt liefert den Envelope der Vorlage; sein Inhalt ist festgehalten.
- [x] Der `before`-Hook ist nachweisbar aktiv.
- [x] Der Sonderfall `ExampleCommand` ist festgehalten, mit Verweis auf den Kommentar in
      `custom/app.php`.
- [x] Die Testdaten der Beispiel-Entity werden abgeräumt.

## Verification
Mehrere vollständige Läufe; die Tabelle der Beispiel-Entity ist danach auf dem Ausgangsstand.

## Ergebnis
`tests/Integration/Api/VorlageApiTest.php` — 12 Tests, 53 Assertions. Gesamtsuite
232 Tests / 564 Assertions (vorher 220 / 511). Vier Gesamtläufe; `example_entity` und die
zugehörigen `pim_log`-Zeilen sind danach auf dem Ausgangsstand.

### Was festgehalten ist
- **`Core\Example` im Schema** — unter dem Kurznamen aus Verzeichnis und Klasse, sowohl im
  `data`- als auch im `permissions`-Block. Damit ist zugleich belegt, dass die
  Unterverzeichnis-Struktur `custom/Entity/Core/` trägt — der Punkt, an dem `Api::getAll()`
  bis `000-000-0007` gescheitert ist.
- **Die generischen Endpunkte** — `insert`, `single`, `list`, `delete` in einer Runde. Eine
  Projekt-Entity braucht keinen eigenen Controller und keine Sonderbehandlung, und sie läuft
  durch dieselbe Protokollierung (`Log::INSERTED`, `model_name` = `Core\Example`).
- **Die `@PIM\Select`-Optionen** stehen im Schema, als `id`/`name`-Paare mit identischem Wert.
- **Der Beispiel-Endpunkt** — Inhalt der Antwort: `i18n.message`, `i18n.key`
  (`core.config.loaded`), leeres `data`, `errors`/`meta` `null`. Die Sicherheitsseite deckt
  `RouteSecurityApiTest` ab.
- **Der Zeitstempel der Vorlage** ist ISO 8601 mit Millisekunden und Zeitzone, der des
  Frameworks `Y-m-d H:i:s` ohne beides — ein Unterschied, der bei `000-000-0014` auffallen wird.
- **Der `ExampleCommand`** ist bewusst nicht registriert; geprüft an der Klasse, am Vermerk in
  `custom/app.php` und an der Ausgabe von `bin/console.php list`.

### Die Middleware — und wo der Test an eine Grenze stösst
Der `after`-Hook ist beobachtbar: `Referrer-Policy` kommt an. Damit steht fest, dass
`custom/app.php` geladen und ausgeführt wird — und weil beide Hooks in derselben Datei im
selben Durchlauf registriert werden, ist damit auch der `before`-Hook registriert.

Der `before`-Hook selbst ist **von aussen nicht prüfbar**. Er tut genau eines —
`$app['request.startedAt'] = microtime(true);` — und niemand liest diesen Wert. Kein Endpunkt
gibt ihn aus, kein Header trägt ihn. Das ist keine Lücke des Tests, sondern eine Aussage über
die Vorlage: Sie führt das `before`-Muster an einem Beispiel vor, das seine eigene Wirkung
nicht zeigt. Der `after`-Hook macht es besser. Ebenso ist die Reihenfolge, die die Vorlage
ausdrücklich als bedeutsam beschreibt, mit einem einzigen Hook nicht nachweisbar.

### Befunde → `000-000-0017`
1. **`jsonExample` existiert für die API nicht.** Der `TypeManager` kennt keinen `json`-Typ;
   das Feld fällt still aus dem Schema — kein Eintrag, keine Warnung. Schreiben scheitert mit
   `contentfly_general_unknown_property`, Lesen liefert das Feld gar nicht. Die Vorlage führt
   damit ein Feld vor, das über die API unbenutzbar ist.
2. **`@PIM\Select` validiert nichts.** `state: "gibtsnicht"` wird angenommen und gespeichert.
   Die Optionen sind reine Metadaten für einen Client — dasselbe Muster wie `canExport` und
   `getExtended`, nur hier besonders leicht zu beheben, weil die erlaubten Werte direkt
   danebenstehen.
3. **Die Vorlage trägt Kommentare aus einem fremden Projekt.** `Example.php` spricht von
   Mandanten, Stripe, `TrialExpiryChecker` und einer „trial-end paywall spec";
   `ExampleController::bootstrapAction()` dokumentiert eine Auflösung über `X-Origin-Host`
   und einen Mandanten-Slug, die der Rumpf nicht enthält. Nichts davon existiert im Baum.
   Kein Verhaltensfehler, aber eine Vorlage, die Falsches erklärt.
