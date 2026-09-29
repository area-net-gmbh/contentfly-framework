---
id: 015-000-0002
title: Installer setzt admin/admin, appcms:setup setzt das Admin-Passwort zurück
status: done
depends_on: []
---

# Installer setzt admin/admin, appcms:setup setzt das Admin-Passwort zurück

## Context
**Security-Scan 2026-09, HIGH. Finding F1.**

`Helper::install()` (`Classes/Helper.php:124`) findet oder erzeugt den lokalen Benutzer `admin`
und ruft immer `setPass("admin")` und `setIsAdmin(true)` auf. `appcms:setup`
(`Command/SetupCommand.php:27`) ruft das ohne Schutz auf. Ein Setup-Lauf auf einer laufenden
Instanz setzt ein bereits geändertes Admin-Passwort also still auf `admin` zurück.
`appcms:install` überschreibt das Passwort nur, wenn `--admin-password` oder
`APPCMS_ADMIN_PASSWORD` gesetzt ist. Ein Passwortwechsel beim ersten Login wird nicht erzwungen.

Auf einer Instanz mit Standard-Installation oder nach einem erneuten `appcms:setup` genügt
`POST /auth/login {"alias":"admin","pass":"admin"}` für ein Admin-Token, gleich beim ersten
Versuch, also weit unter der Login-Drossel.

## Acceptance criteria
- [x] Kein fest eingebautes Passwort mehr: `appcms:install` verlangt `--admin-password` bzw. `APPCMS_ADMIN_PASSWORD` oder erzeugt ein zufälliges Passwort und gibt es genau einmal aus.
- [x] `Helper::install()` ändert an einem bestehenden Admin-Konto weder Passwort noch `loginManager` noch `isAdmin`.
- [x] `appcms:setup` auf einer installierten Instanz lässt das Admin-Passwort unverändert.
- [x] Doku (`runbook.md`, `deployment.md`, Migrationsleitfaden) nennt kein Standardpasswort mehr, und der Bruch steht im Register `breaking-changes.md`.

## Verification
Test: Das Admin-Passwort auf einen Wert ≠ `admin` setzen und `appcms:setup` ausführen. Vor dem
Fix gelingt danach der Login mit `admin`/`admin`, nach dem Fix nicht. Ein zweiter Test prüft,
dass `appcms:install` ohne Passwortangabe kein Konto mit dem Passwort `admin` anlegt.
