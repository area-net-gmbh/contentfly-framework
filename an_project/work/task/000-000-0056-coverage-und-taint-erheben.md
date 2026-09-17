---
id: 000-000-0056
title: Coverage und Datenflüsse einmalig erheben
status: todo
depends_on: []
---

# Coverage und Datenflüsse einmalig erheben

## Context
**Zwei Erhebungen, die noch nie gemacht wurden — und beide beantworten dieselbe Frage: Wo sehen
607 Tests und PHPStan *nicht* hin?**

**Coverage.** `phpunit.xml.dist` verdrahtet bewusst keinen Coverage-Report — die Begründung steht
dort und ist gut: Ein fest verdrahteter Report verlangt bei **jedem** Lauf einen Coverage-Treiber.
Das heisst aber auch: **Die Zahl ist nie erhoben worden.** „Gut getestet" ist damit eine
Vermutung. `./vendor/bin/phpunit with-coverage-text` ist im Runbook beschrieben.

**Datenflüsse.** PHPStan findet Typfehler, **keine Taint-Pfade**. Für eine API mit Datei-Upload,
LDAP-Anbindung und JWT ist die Frage „kommt Benutzereingabe ungefiltert in eine Query, einen
Dateipfad oder eine Shell" die, die heute **kein einziges** Gate stellt. `000-000-0038` (eine
hochgeladene PHP-Datei wurde ausgeführt) war genau so ein Pfad — gefunden von Hand.

## Warum das vor einem Pentest kommt
Was eine Erhebung für ein paar Stunden findet, muss kein Tagessatz finden. Die Befunde daraus
werden einzeln ticketiert — dieser Task behebt nichts.

## Zu entscheiden
- **Welches Werkzeug für Taint.** Psalm mit `taint-analysis` (kennt PHP-Semantik genau, verlangt
  aber eine zweite statische Analyse neben PHPStan) oder Semgrep (regelbasiert, sprachübergreifend,
  weniger tief). **Für einen einmaligen Durchlauf zählt die Trefferquote, nicht die Integrierbarkeit.**
- **Ob daraus ein Gate wird.** Meine Empfehlung: **nein, nicht sofort.** Eine Coverage-Schwelle
  erzeugt Tests, die Zeilen berühren statt zu prüfen — das ist in diesem Projekt genau die
  Eigenschaft, gegen die alle bestehenden Gates gebaut sind. Erst die Zahl kennen, dann
  entscheiden.

## Acceptance criteria
- [ ] Ein Coverage-Bericht liegt vor, und die **drei am schwächsten abgedeckten Bereiche** sind benannt — nicht nur die Gesamtzahl.
- [ ] Für jeden dieser drei ist entschieden: Test nachziehen (Ticket) oder begründet nicht abdecken.
- [ ] Ein Taint-Durchlauf ist gefahren; jeder Treffer ist als „echt" oder „falsch positiv" eingeordnet, mit Begründung.
- [ ] Jeder echte Treffer hat ein eigenes Ticket. **Dieser Task behebt nichts** — er erhebt.
- [ ] Die Entscheidung „Gate oder nicht" ist getroffen und begründet, nicht offengelassen.

## Verification
Der Coverage-Bericht und die Taint-Ausgabe liegen als Ergebnis im Task. Ein Leser kann sagen:
Was ist abgedeckt, was nicht, und was folgt daraus.
