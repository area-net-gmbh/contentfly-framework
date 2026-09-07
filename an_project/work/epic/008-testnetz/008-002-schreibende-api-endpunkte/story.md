---
id: 008-002-0000
title: Schreibende API-Endpunkte charakterisieren
status: review
depends_on: [008-001-0000]
---

# Schreibende API-Endpunkte charakterisieren

## Goal
Die Schreibseite ist der Teil, bei dem ein unbemerkter Verhaltenswechsel Daten kostet statt nur
Antworten zu verändern. Fünf Routen schreiben heute in die Datenbank, keine ist getestet. Diese
Story hält fest, **was sie tun — einschließlich dessen, was sie nebenbei tun**.

## Umfang

### Die Routen

| Route | Was festzuhalten ist |
|---|---|
| `/api/insert` | Anlegen, erzeugte ID (GUID-Strategie), Rückgabeobjekt, Pflichtfelder |
| `/api/update` | Teilaktualisierung — welche Felder unberührt bleiben |
| `/api/replace` | Vollersetzung — der Unterschied zu `update` ist genau das, was niemand dokumentiert hat |
| `/api/multiupdate` | mehrere Objekte in einem Aufruf; Verhalten, wenn eines davon scheitert |
| `/api/delete` | Löschen samt OneJoin-Kaskade und i18n-Geschwistern |

### Die Nebenwirkungen — der eigentliche Wert dieser Story
Ein Schreibvorgang ändert mehr als die Zieltabelle. Genau hier kippt beim Kernel-Tausch etwas,
ohne dass eine Antwort anders aussieht:

- **`Log`-Einträge.** Jedes Insert, Update, Delete und jedes Entfernen eines Benutzers schreibt
  eine `Log`-Zeile mit `modelId`, `modelName`, `mode` und — sofern die Entity ein `labelProperty`
  trägt — `modelLabel`. Dass `labelProperty` überhaupt noch existiert, ist das Ergebnis von
  `012-005-0002`; ein Test darauf schützt diese Entscheidung.
- **`encoded`-Verschlüsselung.** Felder mit `@PIM\Config(encoded=true)` werden über
  `StringType`/`TextareaType` mit `SECURITY_CIPHER_KEY` ver- und entschlüsselt. Zu prüfen:
  Der Wert liegt in der Datenbank **nicht** im Klartext, kommt über die API aber im Klartext
  zurück.
- **`unique`-Verletzungen** enden in einer `EntityDuplicateException` — mit welchem Statuscode
  und welcher Nutzlast, ist festzuhalten.
- **OneJoin-Kaskaden.** Beim Löschen entfernt `Api::delete()` die verjointen Objekte mit. Ohne
  Test fällt das beim Umbau nicht auf.
- **`sorting`** bei `BaseSortable`/`BaseI18nSortable` und `sortRestrictTo`.
- **`userCreated` / `modified`** werden automatisch gesetzt.

### Abgrenzung
Auch hier gilt die Regel des Epics: **festhalten, nicht verbessern.** Wenn `multiupdate` bei
einem Fehler die bereits geschriebenen Objekte stehen lässt, dann ist das der Test — nicht die
Transaktion, die man sich wünscht. Was dabei auffällt, wird als eigener Task notiert
(`/new-task`), nicht nebenbei repariert.

### Vorbedingung
Hängt an `008-001`, weil die Prüfung eines Schreibvorgangs über die Leseseite läuft: erst
schreiben, dann mit `/api/single` oder `/api/list` nachsehen, ob das Richtige ankam.

## Fertig, wenn
- Jede der fünf Routen hat Tests für den Erfolgsfall und den wichtigsten Fehlerfall.
- Der Unterschied zwischen `update` und `replace` ist durch einen Test festgelegt.
- Für jede der sechs Nebenwirkungen oben existiert mindestens ein Test.
- Bei `encoded` ist nachgewiesen, dass der Wert in der Datenbank verschlüsselt liegt — geprüft
  an der Datenbank, nicht nur an der API-Antwort.
- Auffälligkeiten sind als eigene Tasks notiert, nicht in dieser Story repariert.
- Die Suite läuft gegen den heutigen Silex-Stand grün.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [x] 008-002-0001 — Anlegen und Löschen festhalten
- [x] 008-002-0002 — update gegen replace abgrenzen
- [x] 008-002-0003 — /api/multiupdate festhalten
- [x] 008-002-0004 — Log-Nebenwirkungen festhalten
- [x] 008-002-0005 — unique, Sortierung — und die beiden Lücken
