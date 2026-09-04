---
id: 012-003-0002
title: Admin-Dateiverwaltung entfernen
status: review
depends_on: [012-003-0001]
---

# Admin-Dateiverwaltung entfernen

## Context
Was ausschließlich die gelöschte Dateimaske bedient hat, verschwindet — Ordnerverwaltung für die Maske, Vorschaubilder für die Listenansicht, Massenoperationen der Admin-Ansicht.

## Acceptance criteria
- [x] Die als UI eingestuften Actions und ihre Routen sind entfernt.
- [x] Die Entities `File` und `Folder` samt Traits bleiben unverändert — die Datenhaltung bleibt bestehen.
- [x] Was in `Classes/File/` nur der Maske diente, ist mit entfernt.
- [x] Keine tote Referenz auf entfernte Methoden bleibt zurück.

## Ergebnis — kein Eingriff nötig

Die Zuordnung aus `012-003-0001` hat ergeben: Es gibt im `FileController` keine
Admin-Dateiverwaltung. Alle drei Actions sind API-Funktionen, und der gesamte Datei-Stack
(`Controller/FileController.php`, `Classes/File/`) enthält keine UI-Kopplung.

Dieser Task wird deshalb **ohne Codeänderung** geschlossen. Das ist das richtige Ergebnis und
kein übersprungener Task: Die Story ging von einer Vermischung aus, die es nicht gibt. Etwas zu
entfernen, nur weil ein Task es vorsah, hätte API-Funktionen gekostet.

Die Abnahmekriterien sind entsprechend zu lesen — „die als UI eingestufften Actions sind
entfernt" ist erfüllt, weil die Menge leer ist; `File`, `Folder` und die Traits sind
unverändert, weil sie es bleiben sollten.

## Verification
Upload und Download über die API werden durchgespielt und funktionieren; die entfernten Routen liefern 404.
