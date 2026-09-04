---
id: 012-004-0000
title: Sessionbasierte Admin-Auth entfernen
status: review
depends_on: [012-001-0000]
---

# Sessionbasierte Admin-Auth entfernen

## Goal
Die PHP-Session existiert im Framework nur wegen der Admin-Oberfläche. Mit ihr fällt sie weg —
die API authentifiziert token-/JWT-basiert.

**Umfang**
- Den Session-Bootstrap in `Auth::init()` (`lib/contentfly/bootstrap.php`) entfernen; die Session
  wird heute bei **jedem** Request gestartet, auch bei reinen API-Aufrufen.
- `LoginManager` und `AuthController` (170 Zeilen) auf Session-Abhängigkeiten prüfen und diese
  herauslösen — der Token-Weg bleibt vollständig erhalten.
- Session-Verwendungen im übrigen Framework aufspüren (`$app['session']`, `$_SESSION`) und
  einzeln bewerten: Wirklich UI, oder trägt hier jemand API-Zustand in der Session?
- Die Vorlage `custom/app.php` nachziehen: Sie enthält heute eine Sonderbehandlung, die für
  `/api/v1/*` die Session frühzeitig schließt, um den Write-Lock freizugeben. Ohne Session ist
  dieser Workaround gegenstandslos und wird ersatzlos entfernt.

**Fertig, wenn**
- Kein Request startet mehr eine PHP-Session.
- Anmeldung und Autorisierung über Token funktionieren unverändert, durch Tests belegt.
- Der Session-Write-Lock-Workaround ist aus der Vorlage verschwunden.

**Nebeneffekt, der ausdrücklich erwünscht ist:** Der exklusive Lock auf der Session-Datei
serialisierte konkurrierende API-Aufrufe desselben Nutzers. Er verschwindet mit der Session —
nicht als Optimierung, sondern weil die Ursache entfällt.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [x] 012-004-0001 — Session-Verwendungen aufspüren und bewerten
- [x] 012-004-0002 — Session-Bootstrap entfernen
- [x] 012-004-0003 — Token-Authentifizierung mit Tests absichern
