---
id: 000-000-0069
title: Die ungetesteten Zweige in Api.php einordnen
status: todo
depends_on: []
---

# Die ungetesteten Zweige in Api.php einordnen

## Context
**Gefunden bei der ersten Coverage-Messung (`000-000-0056`, 2026-09-18).** `Api.php` ist mit
**59 %** nicht die schwächste Datei, aber die mit der **grössten absoluten Lücke: 514 von 1.258
Zeilen offen.** Sie ist das Herz der Datenschnittstelle — jeder Lese- und Schreibzugriff läuft
hindurch.

Eine Prozentzahl sagt hier wenig. Die offenen Zeilen verteilen sich auf viele Zweige; manche sind
vermutlich tot, manche Sonderfälle ohne Test, manche Rechte-Verengungen.

## Acceptance criteria
- [ ] Die offenen Zeilen sind **nach Methode** aufgeschlüsselt (Coverage-Bericht zeilengenau).
- [ ] Jeder offene Block ist eingeordnet: **Test nachziehen** (eigenes Ticket, gebündelt nach Methode), **toter Code** (entfernen, eigenes Ticket) oder **begründet nicht abdecken**.
- [ ] Besonders geprüft: jede Stelle mit `Permission::` oder `I18nPermission::`, die kein Test erreicht.

## Verification
Die Einordnung als Tabelle im Task. Ein Leser kann für jeden offenen Block sagen, was mit ihm
geschieht.
