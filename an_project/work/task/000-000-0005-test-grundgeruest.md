---
id: 000-000-0005
title: Test-Grundgerüst herstellen
status: todo
depends_on: []
---

# Test-Grundgerüst herstellen

## Context
`phpunit.xml.dist` liegt im Repo, PHPUnit 10.5 ist installiert — aber `tests/` gibt es nicht.
Der Aufruf endet mit *„Cannot open bootstrap script … /tests/bootstrap.php"*. Die Konfiguration
stammt wie `app.php` und `config.php` aus dem Kundenprojekt: Sie zeigt auf `tests/Unit` und
misst Abdeckung über `custom/Classes/Service` — Verzeichnisse, deren Inhalt hier nie ankam.

Damit kann in diesem Repo **kein einziger Test laufen**. Das blockiert unmittelbar:

| Blockiert | Wofür |
|---|---|
| `012-003-0003` | API-Dateifunktionen (Upload/Download) mit Tests absichern |
| `012-004-0003` | Token-Authentifizierung mit Tests absichern |
| Epic `008` | Das gesamte Testnetz baut darauf auf |

Dies ist bewusst **nur das Gerüst**, nicht das Testnetz: eine lauffähige Harness, auf der die
Stories ihre Tests schreiben können. Was tatsächlich abgedeckt wird, entscheidet Epic `008`.

## Acceptance criteria
- [ ] `tests/bootstrap.php` existiert und macht die Framework- und Projektklassen ladbar
      (beide Autoloader, `ROOT_DIR` und die Konstanten, die Klassen beim Laden erwarten).
- [ ] `phpunit.xml.dist` zeigt auf Verzeichnisse, die es gibt, und misst Abdeckung über den
      Code dieses Repos — nicht über den des Kundenprojekts.
- [ ] Ein **echter** Test läuft grün und prüft etwas Sinnvolles. Ein `assertTrue(true)` beweist
      nur, dass PHPUnit startet.
- [ ] Der Aufruf ist im Runbook dokumentiert.
- [ ] Tests, die eine Datenbank brauchen, sind von denen getrennt, die ohne auskommen — sonst
      steht die ganze Suite still, sobald kein Container läuft.

## Verification
`./custom/vendor/bin/phpunit` läuft ohne Konfigurationsfehler durch und meldet mindestens einen
grünen Test. Ein absichtlich gebrochener Assert lässt ihn fehlschlagen — das ist der Beweis,
dass tatsächlich geprüft und nicht nur gestartet wird.
