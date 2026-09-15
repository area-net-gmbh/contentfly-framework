---
id: 000-000-0042
title: Upload — das Framework kennt kein Größenlimit
status: todo
depends_on: []
---

# Upload — das Framework kennt kein Größenlimit

## Context
**Gefunden bei `007-005-0003`** (Befund F-3). UFP hatte `FILE_MAX_UPLOAD_SIZE` (20 MB) als Patch im
alten Framework; mit dem Paket ist er weg, und Contentfly 2 liest keinen solchen Schlüssel. Es gilt
nur PHPs `upload_max_filesize`/`post_max_size` — eine Einstellung des Servers, nicht der Anwendung.

## Acceptance criteria
- [ ] Entschieden: `FILE_MAX_UPLOAD_SIZE` im Framework (optional, `UploadValidator`, `413`) oder begründet verworfen.
- [ ] Bei Umsetzung: Test, der eine zu grosse Datei abweist und keine Zeile hinterlässt.
- [ ] `breaking-changes.md` nennt, was ein Projekt mit dem alten Patch-Schlüssel tut.

## Verification
Upload über der Grenze gegen eine Testinstallation.
