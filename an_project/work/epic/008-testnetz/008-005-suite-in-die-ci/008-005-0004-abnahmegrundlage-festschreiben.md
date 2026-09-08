---
id: 008-005-0004
title: Die Suite als Abnahmegrundlage festschreiben
status: review
depends_on: [008-005-0001]
---

# Die Suite als Abnahmegrundlage festschreiben

## Context
Epic `008` hat 232 Tests und 564 Assertions hervorgebracht. Damit ist die Suite mehr als ein
Testordner — sie ist die **Beschreibung des heutigen Verhaltens**, gegen die der Kernel-Tausch
geprüft wird.

Das steht heute nirgends. Ohne eine schriftliche Festlegung ist beim Umbau nicht entschieden,
was ein roter Test bedeutet: einen Fehler im Umbau — oder einen Test, der „eben angepasst
werden muss". Genau diese Unterscheidung ist der Grund, warum das Testnetz vor dem Umbau
gebaut wurde.

## Umfang

### Der Kernsatz
Eine Formulierung, die keinen Interpretationsspielraum lässt:

> Der Kernel-Tausch gilt als gelungen, wenn diese Suite **ohne inhaltliche Änderung** grün
> bleibt. Eine Testanpassung ist ein **Verhaltenswechsel** und braucht eine Begründung — sie
> ist kein Wartungsschritt.

Dazu gehört die Abgrenzung, was **keine** inhaltliche Änderung ist: ein geänderter
Namensraum, ein anderer Aufrufweg für dieselbe Zusicherung, ein umbenannter Helfer in
`IntegrationTestCase`. Ohne diese Abgrenzung wird der Satz entweder ignoriert oder er
blockiert legitime Umbauten.

### Für wen das gilt
- **Epic `009` (Kernel-Tausch).** Die Suite ist das Abnahmekriterium. Was heute grün ist,
  muss nach dem Tausch grün sein.
- **Epic `011` (Release).** Dieselbe Suite als Release-Kriterium.
- **Epic `007` (Migration der Bestandsprojekte).** Die Suite als Referenz, an der ein
  Bestandsprojekt prüfen kann, ob sein eigener Umstieg geglückt ist.

### Der ehrliche Teil: was die Suite nicht abdeckt
Eine Abnahmegrundlage, die ihre eigenen Grenzen verschweigt, wiegt in Sicherheit. Epic `008`
hat mehrfach dokumentiert, wo die Charakterisierung **nicht** hinreicht — das gehört
gesammelt an eine Stelle, nicht verstreut in Testkommentaren:

- **Codepfade ohne Auslöser**, für die nur die Vorbedingung geprüft ist: `excludeFromSync`,
  `i18n_universal`, `encoded`, die OneJoin-Kaskade, die Schreibprüfung des `MultijoinType`,
  `canExport`, `getExtended`.
- **Das Notschloss** im `before`-Hook des `SystemController` — nicht scharf geprüft, weil es
  ein beschädigtes Schema der gemeinsamen Testdatenbank verlangt (`008-004-0003`).
- **Der `before`-Hook der Vorlage** — setzt einen Wert, den niemand liest, und ist deshalb von
  aussen nicht nachweisbar (`008-004-0005`).
- **Sprachen und i18n** — die Vorlage konfiguriert keine Sprachen und bringt keine konkrete
  `BaseI18n`-Entity mit; abgedeckt ist nur die Logik im Unit-Test (`008-001-0005`).
- **Der Erfolgsfall von `/api/mail`** — existiert nicht, weil der Endpunkt an einer
  undefinierten Konstanten scheitert (`008-004-0004`).

Diese Liste ist keine Schwäche des Testnetzes, sondern seine Bedienungsanleitung: Wer beim
Umbau eine dieser Stellen anfasst, weiß, dass kein Test ihn auffängt.

### Was nachzuziehen ist
- **`an_project/docs/technical.md`** — der neue Abschnitt mit Kernsatz, Abgrenzung und der
  Liste der Lücken.
- **`an_project/docs/deployment.md`** — die Pipeline aus `008-005-0001` beschreiben; der
  Abschnitt *Umgebungen* ist heute ein leerer Platzhalter.
- **`an_project/docs/runbook.md`** — der lokale Ablauf auf den Stand nach Epic `008`,
  einschliesslich der Versandfalle und des `config.php`-Hooks aus `008-005-0003`.
- **`tests/README.md`** — Verweis auf die Abnahmerolle; die Datei beschreibt heute nur das
  Ausführen.

## Nachgetragen aus 008-005-0003
Beim Bauen des `config.php`-Schutzes war die Suite zwischendurch mit **91 Errors** rot —
`PDOException: Connection refused`, weil der Datenbank-Container gestoppt war. Der Wächter aus
`008-005-0002` hat das **nicht** gemeldet: Er prüft den HTTP-Server, nicht die Datenbank.
Statt einer Meldung über die Umgebung gab es 91 nichtssagende Stacktraces — genau das Problem,
gegen das er gebaut wurde.

Der fehlende Test kommt hier dazu. Die Zugangsdaten gehören dabei an **eine** Stelle: Heute
stehen die Vorgaben in `IntegrationTestCase::pdo()`; der Wächter darf sie nicht ein zweites
Mal hinschreiben, sonst laufen sie auseinander.

## Abgrenzung
Die Epic-Dateien `009`, `011` und `007` werden **nicht** angefasst. Ein Verweis von dort auf
die Abnahmegrundlage gehört in deren eigenes Refinement — sonst greift diese Story in die
Planung anderer Epics ein.

## Acceptance criteria
- [x] `an_project/docs/technical.md` enthält den Kernsatz, wörtlich und ohne Weichmacher.
- [x] Die Abgrenzung, was **keine** inhaltliche Teständerung ist, steht daneben.
- [x] Die Rolle der Suite ist für `009`, `011` und `007` je in einem Satz benannt.
- [x] Die Liste dessen, was die Suite **nicht** abdeckt, ist vollständig aus den Stories
      `008-001` bis `008-004` zusammengetragen, je mit Verweis auf den Task, der es
      festgestellt hat.
- [x] `deployment.md`, `runbook.md` und `tests/README.md` sind auf dem Stand nach Epic `008`.
- [x] Keine Änderung an den Epic-Dateien `007`, `009` oder `011`.
- [x] Der Wächter meldet eine nicht erreichbare **Testdatenbank** mit einer Meldung über die
      Umgebung, statt sie in Stacktraces einzelner Tests untergehen zu lassen; die
      Zugangsdaten stehen dabei nur an einer Stelle.

## Verification
Kein Testlauf — dies ist ein Dokumentationstask. Der Nachweis ist eine Durchsicht:

- Jede Lücke in der Liste lässt sich auf einen Testkommentar oder ein Task-Ergebnis in
  `008-001` bis `008-004` zurückführen; Stichproben belegen das.
- Die in `runbook.md` und `tests/README.md` beschriebenen Befehle werden **ausgeführt** und
  laufen wie beschrieben — eine Anleitung, die niemand nachgespielt hat, ist keine.
- Für die Datenbankprüfung: die Datenbank anhalten, die Suite starten, und die Meldung
  vergleichen mit dem, was heute passiert (91 Stacktraces).

## Ergebnis
Ein neuer Abschnitt *Die Testsuite ist die Abnahmegrundlage* in `an_project/docs/technical.md`;
`deployment.md`, `runbook.md` und `tests/README.md` nachgezogen. Dazu der nachgetragene
Wächter-Test. Suite: **238 Tests / 575 Assertions**.

### Der Kernsatz, wörtlich
> Der Kernel-Tausch gilt als gelungen, wenn diese Suite **ohne inhaltliche Änderung** grün
> bleibt. Eine Testanpassung ist ein **Verhaltenswechsel** und braucht eine Begründung — sie
> ist kein Wartungsschritt.

Daneben steht, was **keine** inhaltliche Änderung ist: geänderter Namensraum, anderer
Aufrufweg für dieselbe Zusicherung, umbenannter Helfer, ergänzende Assertion. Ohne diese
Abgrenzung wird der Satz entweder ignoriert oder er blockiert legitime Umbauten.

**Die Regel hat sich in diesem Task gleich selbst bewährt:** Der Pfadvergleich in
`MailApiTest` musste von `assertSame` auf `realpath` umgestellt werden. Das ist ein anderer
Aufrufweg für dieselbe Zusicherung — nach der eigenen Abgrenzung nicht begründungspflichtig,
und der Test sagt weiterhin genau dasselbe.

### Was die Suite nicht abdeckt — als Tabelle mit Herkunft
Sieben Einträge, jeder mit Grund und dem Task, der ihn festgestellt hat. **Stichprobe: alle
elf genannten Begriffe** (`excludeFromSync`, `i18n_universal`, OneJoin, `MultijoinType`,
`canExport`, `getExtended`, Notschloss, `request.startedAt`, `BaseI18n`, `APP_MAILFROM`,
`jsonExample`) **sind in Testdateien belegt** — die Liste ist keine Behauptung, sondern eine
Zusammenfassung dessen, was in den Kommentaren steht.

Dazu die Grenze des Wächters selbst: Er prüft Vorbedingungen, nicht jeden Übersprung.

### Der nachgetragene Wächter-Test
Aus `008-005-0003`: Bei gestopptem Datenbank-Container meldete die Suite **91 Errors** — lauter
`PDOException: Connection refused`, keiner davon mit der Ursache. Jetzt:

```
Die Testdatenbank auf 127.0.0.1:3307 antwortet nicht.
SQLSTATE[HY000] [2002] Connection refused
Ohne sie scheitert jeder Integrationstest an seiner eigenen Verbindung — mit je einer
PDOException, die nie sagt, dass die Datenbank fehlt.
Hochfahren: docker compose up -d (siehe an_project/docs/runbook.md).
```

Scharf nachgewiesen durch Anhalten des Containers. Der Test prüft ausserdem, dass `pim_user`
existiert — verbunden zu sein genügt nicht, eine leere Datenbank antwortet auch.

**Die Zugangsdaten stehen jetzt an einer Stelle:** `IntegrationTestCase::dbZugangsdaten()`,
öffentlich und statisch, weil der Wächter sie braucht, aber bewusst nicht von der Klasse erbt.
Ein zweites Mal hingeschrieben wären sie irgendwann auseinandergelaufen — und der Wächter
prüfte eine andere Datenbank als die Tests.

### Die Anleitungen sind nachgespielt, nicht nur geschrieben
Der Ablauf aus `tests/README.md` wurde **wörtlich** ausgeführt: `docker compose up -d`,
`appcms:install`, Versandfalle anlegen, Server starten, Suite fahren, Vorlage wiederherstellen.

Dabei fielen zwei Dinge auf, die eine bloss gelesene Anleitung verschluckt hätte:

1. **Der Ablauf war unvollständig.** Er nannte die Versandfalle nicht, obwohl sie seit
   `008-004-0004` dazugehört — drei Tests hätten sich still übersprungen. Jetzt ist sie
   Schritt 2, und das Wiederherstellen der Vorlage ist Schritt 5 statt einer Randbemerkung.
2. **Der Pfadvergleich war zerbrechlich.** `/tmp` ist auf macOS ein Symlink auf `/private/tmp`;
   wer Server und Suite mit unterschiedlicher Schreibweise startet, bekam einen Fehlschlag
   trotz korrekter Einrichtung — die Sorte Fehlschlag, die als „Test kaputt" abgetan wird.
   Verglichen wird jetzt die Datei (`realpath`), nicht die Schreibweise, und die Meldung fragt
   nach einem noch laufenden älteren Testserver.

### Nicht angefasst
Die Epic-Dateien `007`, `009` und `011` — geprüft. Ein Verweis von dort auf die
Abnahmegrundlage gehört in deren eigenes Refinement.
