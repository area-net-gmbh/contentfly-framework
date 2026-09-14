---
id: 014-002-0000
title: Security-Schicht auf Englisch
status: review
depends_on: [014-001-0000]
---

# Security-Schicht auf Englisch

## Goal
`lib/contentfly/Classes/Security/**`, `Controller/AuthController.php`, die Security-Teile von
`bootstrap.php` und die Security-Config-Keys in `Classes/Config.php` sind englisch. Dazu
gehören die Klassen, Methoden, Container-Schlüssel, Config-Keys und das Cache-Verzeichnis aus der
Tabelle im Epic, dazu die Meldungen und Kommentare.

Aufrufer in `custom/`, `tests/`, `tools/ci/` und `.gitlab-ci.yml` werden mitgezogen, damit die
Pipeline weiterläuft.

**Warum eine eigene Story:** Hier liegen die meisten deutschen Namen und genau die Klassen, die
die IT-Security zuerst liest.

## Tasks
- [ ] 014-002-0001 — Token-Prüfung auf Englisch
- [ ] 014-002-0002 — Anmeldeprovider auf Englisch
- [ ] 014-002-0003 — Anmeldebremse, Proxies und Feldverschlüsselung auf Englisch
- [ ] 014-002-0004 — AuthController vollständig englisch
- [ ] 014-002-0005 — Sprachwächter um die Security-Pfade erweitern
