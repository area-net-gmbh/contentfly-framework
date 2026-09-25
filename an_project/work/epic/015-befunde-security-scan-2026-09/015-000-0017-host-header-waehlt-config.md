---
id: 015-000-0017
title: Host-Header wählt den Config-Block, unbekannte Hosts fallen auf default
status: todo
depends_on: []
---

# Host-Header wählt den Config-Block, unbekannte Hosts fallen auf default

## Context
**Security-Scan 2026-09, LOW. Findings F28, F30, F36.** Das Panel hat F28 von MEDIUM auf LOW
gesenkt. F30 wurde mit 2 von 3 Stimmen bestätigt.

`bootstrap.php:62` setzt `HOST` aus `$_SERVER['SERVER_NAME']`, `bootstrap.php:75` reicht es an
`Adapter::setHostname`. Unter Apaches Vorgabe `UseCanonicalName Off` und unter PHPs eingebautem
Server ist `SERVER_NAME` der `Host`-Header des Clients. `Factory::getConfig`
(`Classes/Config/Factory.php:37`) wählt damit den ganzen Config-Block des Requests
(`APP_DEBUG`, `APP_HTTP_AUTH_*`, `APP_FORCE_SSL`, `DB_*`, `SECURITY_*`), und ein unbekannter Host
bekommt still den Block `default`. Dieser ist laut Projekt-Doku der für die lokale Entwicklung.

Folge, wenn ein Projekt dem dokumentierten Muster folgt (lockerer `default`, strengerer
Host-Block): Mit `Host: x` bekommt ein Angreifer für seine Requests die Entwicklungs-Config. Das
heisst Debug-Ausgabe (Stack-Traces, Pfade, Fehlertexte in `meta.debug`), keine HTTP-Basic-Sperre
aus `015-000-0007`, kein erzwungenes SSL und gegebenenfalls andere Secrets. Er kann ausserdem einen
anderen *bekannten* Block per Namen wählen. Die mitgelieferte Vorlage hat nur `default` und ist
deshalb nicht betroffen.

## Acceptance criteria
- [ ] Der Config-Block wird aus einem Wert gewählt, den das Deployment setzt, etwa einer beim Start gelesenen Umgebungsvariable, nicht aus Request-Daten.
- [ ] Passt dieser Wert auf keinen Block, während Host-Blöcke definiert sind, bricht der Start ab (fail closed) statt auf `default` zu fallen.
- [ ] Doku (`deployment.md`, Migrationsleitfaden, `breaking-changes.md`) beschreibt die Umstellung und, solange die Hostwahl übergangsweise bleibt, `UseCanonicalName On` mit festem `ServerName`.

## Verification
Test mit zwei Blöcken (`default` mit `APP_DEBUG = true`, Host-Block mit `APP_DEBUG = false`): Ein
Request mit fremdem `Host` erhält vor dem Fix Debug-Ausgabe, nach dem Fix den konfigurierten Block
bzw. einen Abbruch.
