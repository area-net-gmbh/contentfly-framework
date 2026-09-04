---
id: 012-005-0001
title: Widget-Annotationen löschen
status: todo
depends_on: []
---

# Widget-Annotationen löschen

## Context
Zwölf der fünfzehn Klassen in `Classes/Annotations/` beschreiben Formularelemente — `Rte` trägt eine TinyMCE-Toolbar-Zeile. Ohne Oberfläche haben sie keinen Zweck.

## Acceptance criteria
- [ ] `Rte`, `Checkbox`, `Radio`, `Select`, `Textarea`, `MatrixChooser`, `Datetime`, `Time`, `Password`, `EntitySelector`, `Virtualjoin` sind je einzeln geprüft und gelöscht, sofern nur UI daran hängt.
- [ ] `Permissions` und `I18nPermissions` bleiben — sie tragen Berechtigungen, also Datenverhalten.
- [ ] Wo eine der gelöschten Annotationen doch Datenverhalten trug, ist das benannt und der Anteil erhalten geblieben.
- [ ] Die Entities des Frameworks und der Vorlage sind entsprechend bereinigt.

## Verification
`grep -rn "@PIM\\\\Rte\|@PIM\\\\Checkbox" lib custom` liefert keine Treffer. Anwendung bootet, Schema wird erzeugt.
