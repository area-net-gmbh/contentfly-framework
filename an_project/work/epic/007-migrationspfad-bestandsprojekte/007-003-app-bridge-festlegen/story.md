---
id: 007-003-0000
title: Die $app[...]-Bridge festlegen
status: todo
depends_on: []
---

# Die `$app[...]`-Bridge festlegen

## Goal
Es steht fest — und ist durchgesetzt, nicht nur aufgeschrieben —, ob der `ArrayAccess`-Zugriff
`$app['orm.em']` dauerhaft zur öffentlichen Framework-API gehört oder eine befristete
Migrationshilfe mit Frist ist.

**Ohne diese Festlegung weiss kein Bestandsprojekt, ob es seine Controller anfassen muss.** Genau
deshalb ist es eine eigene Story und keine Fussnote im Leitfaden: Solange die Frage offen ist,
kann ein Projekt den Aufwand seiner Migration nicht abschätzen.

## Ausgangslage

Die Bridge stammt aus `009-002` und ist bewusst gebaut worden, um den Silex-Zugriff zu erhalten:
`Classes/Kernel/ApplicationInterface` erweitert `\ArrayAccess`, `Classes/Kernel/Container`
implementiert es. Bestandscode greift genau so auf Dienste zu.

## Die beiden Wege

1. **Dauerhaft.** Der Zugriff ist Teil der öffentlichen API und bleibt. Dann ist er zu
   dokumentieren wie jede andere Zusicherung, samt der Liste der Schlüssel, auf die man sich
   verlassen darf — heute ist das nirgends festgeschrieben.
2. **Befristet.** Der Zugriff ist eine Migrationshilfe mit Deprecation-Frist. Dann braucht es
   einen benannten Nachfolger, eine Frist, und einen Weg, auf dem ein Projekt merkt, dass es
   betroffen ist — eine Deprecation, die niemand sieht, ist keine.

**Beides ist vertretbar, aber nur eines ist entschieden.** Zu dieser Story gehört die
Entscheidung *und* ihre Durchsetzung, nicht die Aufzählung der Möglichkeiten.

## Abnahme

Die Festlegung steht in `an_project/docs/architecture.md` unter *Key decisions*, mit Begründung
und den verworfenen Alternativen. Fällt sie auf „befristet", macht ein Lauf sichtbar, welcher
Aufruf betroffen ist; fällt sie auf „dauerhaft", steht die Liste der zugesicherten Schlüssel im
`dev-guide.md` und ein Test hält sie fest.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
