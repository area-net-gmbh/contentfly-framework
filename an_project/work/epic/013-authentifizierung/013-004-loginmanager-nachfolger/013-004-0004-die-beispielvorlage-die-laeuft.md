---
id: 013-004-0004
title: Die Beispiel-Vorlage, die wirklich läuft
status: todo
depends_on: [013-004-0002, 013-004-0003]
---

# Die Beispiel-Vorlage, die wirklich läuft

## Context
`custom/Classes/` enthaelt heute zwei Service-Klassen und keinen einzigen Provider. Die Story
verlangt eine Vorlage, die **wirklich laeuft** — und das ist mehr als ein Codeschnipsel im
Kommentar: Eine Vorlage, die nie ausgefuehrt wird, ist eine Behauptung.

Sie ist zugleich der einzige Weg, den ganzen Pfad end-to-end zu pruefen. Bis hierhin sind
Vertrag, Provisionierung und Abbildung je fuer sich gemessen; erst ein echter Provider zeigt,
dass sie zusammenpassen.

**Was die Vorlage nicht sein darf:** ein Zugang, den eine Installation versehentlich offen
laesst. Sie prueft gegen etwas, das ohne ausdrueckliche Konfiguration nichts durchlaesst, und sie
sagt in ihrem eigenen Text, dass sie eine Vorlage ist.

## Acceptance criteria
- [ ] In `custom/` steht ein Provider, der den neuen Vertrag erfuellt und ueber HTTP eine Anmeldung durchfuehrt.
- [ ] Ein Integrationstest meldet sich ueber ihn an und bekommt einen Token — derselbe Weg, den ein Projekt gehen wuerde.
- [ ] Ohne ausdrueckliche Konfiguration laesst die Vorlage **niemanden** herein. Ein Test belegt es.
- [ ] Der Vorlagentext sagt, was ein Projekt daran aendern muss, und was es nicht anfassen sollte.
- [ ] `tools/check-template-config.sh` bleibt gruen.

## Verification
Ein Integrationstest ueber den vollen Weg: registrieren, anmelden, Token vorzeigen, geschuetzte
Route oeffnen. Ein zweiter ohne Konfiguration, der eine Abweisung erwartet. Volle Suite.
