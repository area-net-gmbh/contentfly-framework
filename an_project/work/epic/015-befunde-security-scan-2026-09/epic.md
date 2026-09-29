---
id: 015-000-0000
title: Befunde Security-Scan 2026-09
status: todo
depends_on: []
---

# Befunde Security-Scan 2026-09

## Goal
Die Befunde des Claude-Security-Scans vom 2026-09-25 sind behoben. Gescannt wurde Commit
`4459c160` auf `master`, Scope `lib/contentfly`, `custom`, `bin`, `index.php` und `.htaccess`
(179 Dateien), Aufwand `medium`. **37 Findings** haben die Prüfung durch ein Panel aus drei
Verifiern überstanden: 7 HIGH, 20 MEDIUM, 10 LOW. Sie gehen auf **22 Ursachen** zurück. Jede
Ursache ist ein Task. Der Task nennt alle Finding-IDs, die er schliesst.

Erfolg heisst: Alle 22 Tasks sind `done`, jede Lücke ist mit einem Test belegt, der vor dem Fix
rot und danach grün ist, und ein erneuter Scan desselben Scopes meldet keine der Ursachen mehr.

Bericht, maschinenlesbare Fassung und Patch-Vorschläge liegen lokal unter
`CLAUDE-SECURITY-20260925-124927/`. Der Ordner ist per eigener `.gitignore` von Git
ausgenommen, existiert also nur in dieser Arbeitskopie. Die Tasks beschreiben deshalb jeden
Befund vollständig und hängen nicht vom Bericht ab.

Reihenfolge: zuerst die vier HIGH-Ursachen (`015-000-0001` bis `0004`), dann MEDIUM, dann LOW.

## Stories
Keine. Die Befunde stehen direkt als Tasks unter dem Epic.

## Tasks
- [ ] 015-000-0001 — Leeres Passwort überschreibt fremde Passwörter und öffnet den Admin-Login (HIGH · F2, F3, F5, F6)
- [ ] 015-000-0002 — Installer setzt admin/admin, appcms:setup setzt das Admin-Passwort zurück (HIGH · F1)
- [ ] 015-000-0003 — File.name mit ../ liest beliebige Dateien über /api/all filedata (HIGH · F4)
- [ ] 015-000-0004 — bin/console.php läuft über HTTP ohne CLI-Prüfung (HIGH · F7)
- [ ] 015-000-0005 — Datei-ID aus dem Request wird zum Pfad — Upload und Löschen ausserhalb von data/files (MEDIUM · F8, F21)
- [ ] 015-000-0006 — /file/upload überschreibt fremde Dateien ohne Eigentümerprüfung (MEDIUM · F9)
- [ ] 015-000-0007 — HTTP-Basic-Sperre lässt mit halbem Zugang durch (MEDIUM · F10, F12, F16)
- [ ] 015-000-0008 — Login-Fehlertexte verraten, ob ein Konto existiert (MEDIUM · F11)
- [ ] 015-000-0009 — hasUserId wertet id == User-ID als Eigentum (MEDIUM · F13)
- [ ] 015-000-0010 — Login-Drossel pro Kennung lässt sich mit Akzent-Varianten des Alias umgehen (MEDIUM · F14, F32)
- [ ] 015-000-0011 — lang in anderer Schreibweise umgeht die Sprachrechte (MEDIUM · F15)
- [ ] 015-000-0012 — fulltext-Filter in /api/list sucht in pass und salt (MEDIUM · F17, F20, F27)
- [ ] 015-000-0013 — Nicht-Admins ändern languages und tokenTimeout ihrer Gruppe (MEDIUM · F18, F19, F31)
- [ ] 015-000-0014 — JWT-Identität hängt am änderbaren Alias (MEDIUM · F22, F26)
- [x] 015-000-0015 — OIDC-Login prüft nicht, für welchen Client das Token ausgestellt ist (MEDIUM · F23, F24)
- [ ] 015-000-0016 — Löschen einer Sprachvariante löscht alle anderen ohne Rechteprüfung (MEDIUM · F25)
- [ ] 015-000-0017 — Host-Header wählt den Config-Block, unbekannte Hosts fallen auf default (LOW · F28, F30, F36)
- [ ] 015-000-0018 — Login-Laufzeit verrät existierende Konten (LOW · F29)
- [ ] 015-000-0019 — Notfallpfad erlaubt anonymes updateDatabase bei kaputtem Schema (LOW · F33)
- [ ] 015-000-0020 — Hochgeladenes HTML/SVG wird inline aus dem Web-Root ausgeliefert (LOW · F34)
- [ ] 015-000-0021 — /file/overwrite löscht die Quelldatei ohne Löschrecht (LOW · F35)
- [ ] 015-000-0022 — /api/replace verrät die Existenz von Datensätzen ohne Leserecht (LOW · F37)
