---
id: 000-000-0039
title: CORS — jede Herkunft wird mit Credentials zurückgespiegelt
status: review
depends_on: []
---

# CORS — jede Herkunft wird mit Credentials zurückgespiegelt

## Context
**Gefunden bei `007-005-0001`** am Bestandsprojekt UFP, das die Herkunft in seiner Kopie aus der
Konfiguration setzt. Im neuen Framework ist sie offen.

**Gemessen an einer frischen Installation dieses Repos (2026-09-15):**
`GET /api/config` mit `Origin: https://evil.example` antwortet mit

```
Access-Control-Allow-Origin: https://evil.example
Access-Control-Allow-Credentials: true
```

**Warum das zählt:** Der `after`-Hook in `bootstrap-web.php` setzt `Allow-Origin` auf den `Origin`
jeder Anfrage, und `APP_ALLOW_CREDENTIALS_SDK` steht auf `true`. Damit darf jede fremde Seite im
Browser eines angemeldeten Benutzers Anfragen mit dessen Credentials stellen und die Antwort lesen.
`Config::$APP_ALLOW_ORIGIN` ist deklariert und wird **nirgends gelesen**.

**Betrifft den Prüfstand der IT-Security** `v2.0.0-pre-security-2026-09-14`, nicht auf der Liste
der bekannten offenen Befunde.

**Entschieden am 2026-09-15:** `APP_ALLOW_ORIGIN` nimmt die erlaubten Herkünfte. Nur eine davon wird
zurückgegeben; ohne Eintrag gibt es **kein** `Allow-Origin`. Eine Ionic-App (etwa
`capacitor://localhost`) muss eingetragen werden — das ist eine Bruchstelle mit klarer Anweisung.

## Acceptance criteria
- [x] Eine Anfrage mit einer Herkunft, die nicht in `APP_ALLOW_ORIGIN` steht, bekommt **kein** `Access-Control-Allow-Origin` und **kein** `Access-Control-Allow-Credentials`.
- [x] Eine eingetragene Herkunft bekommt genau sich selbst zurück, dazu `Vary: Origin`.
- [x] Ohne Eintrag gilt dasselbe wie für eine fremde Herkunft.
- [x] Der OPTIONS-Preflight folgt derselben Regel.
- [x] Tests halten alle drei Fälle fest, mit den Headern, nicht nur dem Statuscode.
- [x] Die Vorlage `custom/config.php` zeigt, wie Herkünfte eingetragen werden; die Suite und die CI tragen, was sie brauchen.
- [x] `breaking-changes.md` und `technical.md` sind nachgezogen.

## Verification
Die Messung aus dem Context wiederholen: fremde Herkunft ohne Header, eingetragene mit genau
sich selbst. Volle Suite, PHPStan, Deprecation-Gate.

## Ergebnis

**`Classes/Security/CorsPolicy` entscheidet, welche Herkunft eine Antwort nennen darf;** der
`after`-Hook in `bootstrap-web.php` fragt sie.

- Eine **eingetragene** Herkunft bekommt genau sich selbst zurück, mit
  `Access-Control-Allow-Credentials` (Wert wie bisher aus `APP_ALLOW_CREDENTIALS_SDK`).
- Eine **fremde** Herkunft, eine Anfrage **ohne** `Origin` und jede Anfrage **ohne
  Konfiguration** bekommen weder `Allow-Origin` noch `Allow-Credentials`.
- **`*`** bleibt als ausdrückliche Wahl möglich, dann ohne Credentials.
- Jede Antwort trägt **`Vary: Origin`**.
- Verglichen wird exakt (Schema, Host, Port; ein abschliessender Schrägstrich in der
  Konfiguration zählt nicht). Kein Präfix-Vergleich: `https://app.example.com.evil.example` ist
  eine andere Herkunft.
- `Allow-Headers`, `Allow-Methods` und `Max-Age` bleiben unverändert — sie erlauben ohne
  `Allow-Origin` nichts.

**Konfiguration:** `Config::$APP_ALLOW_ORIGIN` ist dokumentiert (Array oder kommagetrennt, Standard
`null` = keine fremde Herkunft). Die Vorlage `custom/config.php` liest die Umgebungsvariable
`APP_ALLOW_ORIGIN` und erklärt die Werte für Ionic/Capacitor. `tools/check-template-config.sh`
bleibt grün.

**Testserver und CI:** `CONTENTFLY_TEST_ALLOWED_ORIGIN` (`https://allowed.example`) steht in
`.gitlab-ci.yml`, wird von `tools/ci/prepare-test-environment.sh` verlangt und als
`APP_ALLOW_ORIGIN` an den Server gegeben — nach demselben Muster wie das JWT-Secret.
`tests/README.md` ist nachgezogen.

**Die Messung, wiederholt:** `Origin: https://evil.example` → nur noch `Vary: Origin`;
`Origin: https://allowed.example` → `Access-Control-Allow-Origin: https://allowed.example`,
`Access-Control-Allow-Credentials: true`.

**Tests:** `CorsPolicyTest` (4, die Regel) und `CorsApiTest` (4, die Header einer Installation:
fremd, eingetragen, ohne `Origin`, Preflight). **Gegenprobe** mit `bootstrap-web.php` von master:
drei rot; der vierte (eingetragene Herkunft) ist dort grün, weil das Spiegeln jede Herkunft
zurückgab — genau der Befund.

**Ein eigener Fehltritt beim Testen, festgehalten:** Das lokale Hilfsskript, das die
Testinstallation neu aufsetzt, stellte `custom/config.php` per `git checkout HEAD` wieder her und
verwarf damit die noch nicht committete Änderung an der Vorlage — der erste Lauf von
`CorsApiTest` war deshalb rot. Das Skript liegt ausserhalb des Repos und ist korrigiert; im Repo
beschreiben `runbook.md` und `tests/README.md` denselben Befehl für die Zeit **nach** einem Commit,
wo er richtig ist.

**Doku:** `breaking-changes.md` (Abschnitt Konfiguration, mit den Werten für Capacitor),
`technical.md` ~~A-8~~, Kopf von `migration.md` auf 105 Einträge.

**Verifiziert:** volle Suite `Tests: 544, Assertions: 1768, Skipped: 3`, PHPStan
`[OK] No errors`, Deprecation-Gate 0; `MigrationGuideTest` und Sprachwächter nach den Doku-Änderungen
erneut grün.
