---
id: 012-003-0002
title: Admin-Dateiverwaltung entfernen
status: todo
depends_on: [012-003-0001]
---

# Admin-Dateiverwaltung entfernen

## Context
Was ausschließlich die gelöschte Dateimaske bedient hat, verschwindet — Ordnerverwaltung für die Maske, Vorschaubilder für die Listenansicht, Massenoperationen der Admin-Ansicht.

## Acceptance criteria
- [ ] Die als UI eingestuften Actions und ihre Routen sind entfernt.
- [ ] Die Entities `File` und `Folder` samt Traits bleiben unverändert — die Datenhaltung bleibt bestehen.
- [ ] Was in `Classes/File/` nur der Maske diente, ist mit entfernt.
- [ ] Keine tote Referenz auf entfernte Methoden bleibt zurück.

## Verification
Upload und Download über die API werden durchgespielt und funktionieren; die entfernten Routen liefern 404.
