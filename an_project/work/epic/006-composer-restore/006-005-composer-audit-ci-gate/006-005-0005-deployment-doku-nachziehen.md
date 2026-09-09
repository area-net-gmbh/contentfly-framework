---
id: 006-005-0005
title: deployment.md beschreibt die Gates und den Umgang mit einem Fund
status: review
depends_on: [006-005-0004]
---

# deployment.md beschreibt die Gates und den Umgang mit einem Fund

## Context
Ein Gate ist erst dann eines, wenn klar ist, was zu tun ist, wenn es anschlägt. Sonst geschieht
das Naheliegende: Der nächste, dem die Pipeline rot ins Haus fällt, setzt die Kennung auf die
Ausnahmeliste, und das Gate ist weg.

Die Story verlangt es ausdrücklich: *„Die Pipeline-Definition liegt im Repo, und
`an_project/docs/deployment.md` beschreibt, was sie prüft und wie man einen Fund behandelt."*

## Umfang

### Was in `deployment.md` gehört
Der Abschnitt über die Pipeline sagt heute, `composer audit --locked` und das
„0 Deprecations"-Gate „kommen mit `006-005` in dieselbe Pipeline". Ab hier sind sie da und
beschreiben sich selbst:

| Gate | prüft | blockiert |
|---|---|---|
| `composer audit --locked` | den Lock gegen die Advisory-Datenbank | ja, mit namentlicher Ausnahmeliste |
| abgelaufene Ausnahmen | ob jede Ausnahme noch greift | ja |
| Deprecations (Laufzeit) | das Serverlog nach dem Testlauf | auf 8.3 ja, auf 8.4 melden |
| PHPStan | deprecated APIs ohne Ausführung | nein, `allow_failure` |

### Der Ablauf bei einem Fund — der eigentliche Inhalt
Nicht „was tun", sondern **in welcher Reihenfolge gefragt wird**:

1. Gibt es ein Release, das die Meldung behebt? Dann Constraint anheben — und die
   Constraint-Kette aus `006-001-0003` gegenrechnen, bevor irgendetwas committet wird.
2. Kein Release, aber ein Weg um die Nutzung herum? Dann ist es ein Code-Ticket, kein
   Manifest-Ticket.
3. Weder noch → Ausnahme, **einzeln nach CVE-Kennung**, mit Begründung und dem Epic, das sie
   auflöst. Nie paketweise.

Und die Gegenregel, ohne die der Rest nichts wert ist: **Eine Ausnahme ohne benannten Auflöser
ist keine Ausnahme, sondern ein abgeschaltetes Gate.**

### Warum die fünf heutigen Ausnahmen dort erklärt gehören
Sie sind der Präzedenzfall, an dem der nächste sich orientiert. Wer nur die Liste sieht, hält
Ausnehmen für den Normalweg. Wer daneben liest, dass alle fünf zur selben unauflösbaren
Constraint-Kette gehören und mit **einem** Epic verschwinden, versteht die Regel.

### Was sonst noch nachzuziehen ist
- `tests/README.md`, Abschnitt *In der Pipeline* — er listet die Schritte des Nachspielens; die
  neuen Jobs gehören dazu.
- Der Satz in `deployment.md`, dass die Gates „mit `006-005`" kämen, ist danach falsch.

## Abgrenzung
Keine Änderung an den Gates selbst; die stehen nach `006-005-0001` bis `-0004` fest. Kein
Nachtragen in `tech-stack.md` — die Forderung dort bleibt, wie sie ist, sie ist ja jetzt erfüllt.

## Acceptance criteria
- [x] `deployment.md` beschreibt alle vier Prüfungen: was sie prüfen und ob sie blockieren.
- [x] Der Ablauf bei einem Fund steht als **Reihenfolge von Fragen** da, nicht als Aufzählung
      von Möglichkeiten.
- [x] Die Regel „einzeln nach Kennung, nie paketweise, nie ohne benannten Auflöser" steht dort.
- [x] Die fünf heutigen Ausnahmen sind als Präzedenzfall erklärt, mit dem Epic, das sie auflöst.
- [x] `tests/README.md` nennt die neuen Schritte im Abschnitt über das Nachspielen.
- [x] Kein Dokument sagt mehr, die Gates kämen erst noch.

## Verification
Die beschriebenen Befehle werden **ausgeführt**, nicht nur gelesen — dieselbe Regel wie in
`008-005-0004` und `006-003-0003`. Konkret: Der Abschnitt *In der Pipeline* aus
`tests/README.md` wird Schritt für Schritt in Docker nachgespielt, einschliesslich der neuen
Jobs, und muss zum beschriebenen Ergebnis führen.

Dazu die Probe aufs Exempel für den Fund-Ablauf: Eine der fünf Ausnahmen kurzzeitig entfernen,
dem beschriebenen Ablauf folgen und prüfen, ob er zu der Entscheidung führt, die tatsächlich
getroffen wurde. Führt er woandershin, ist der Text falsch, nicht die Entscheidung.

## Ergebnis
**`deployment.md` hat einen neuen Abschnitt *Die Gates*, und er beschreibt nicht nur, was
geprüft wird, sondern was zu tun ist, wenn es anschlägt.** Das ist der Teil, an dem solche
Dokumente sonst aufhören — und genau dort entscheidet sich, ob ein Gate überlebt.

### Vier Prüfungen, zwei blockierend
| Prüfung | blockiert |
|---|---|
| `composer audit --locked` | ja |
| abgelaufene Audit-Ausnahmen | ja |
| Deprecations zur Laufzeit | ja auf PHP 8.3, melden auf 8.4 |
| PHPStan | nein (`allow_failure`) |

Dazu, warum es **zwei** getrennte Ausnahmelisten gibt: `config.audit.ignore` in `composer.json`
für die CVEs, `tools/ci/deprecations-ausnahmen.txt` für die Deprecations. Sie nehmen
Verschiedenes aus, werden aber nach demselben Muster geprüft — und in beiden macht ein Eintrag,
der nicht mehr greift, den Lauf rot.

### Der Fund-Ablauf steht als Reihenfolge von Fragen
Nicht als Liste von Möglichkeiten, sondern als Reihenfolge — **wer gleich bei Frage 3 anfängt,
schafft das Gate ab**:

1. Gibt es ein Release, das behebt? Dann Constraint anheben, und die Kette aus `006-001-0003`
   gegenrechnen.
2. Kein Release, aber ein Weg um die Nutzung herum? Dann Code-Ticket, nicht Manifest-Ticket.
3. Weder noch → Ausnahme, einzeln nach Kennung, nie paketweise, mit benanntem Auflöser.

Und die Gegenregel, ohne die der Rest nichts wert ist: **Eine Ausnahme ohne benannten Auflöser
ist keine Ausnahme, sondern ein abgeschaltetes Gate.**

### Die neun heutigen Ausnahmen als Präzedenzfall
Wer nur die Listen sieht, hält Ausnehmen für den Normalweg. Der Abschnitt erklärt deshalb, dass
**acht der neun an einer einzigen Ursache hängen** — einem Stack, der bis zum Kernel-Tausch
festliegt — und dass sie zusammen verschwinden. Das ist der Unterschied zu einer gewachsenen
Liste, und der Grund, warum sie sich nicht vermehren darf.

### Was sonst überholt war
| Stelle | war |
|---|---|
| `deployment.md`, *Composer als Quelle* | „`composer audit --locked` … **Steht noch aus** — Story `006-005`" |
| `deployment.md`, *Der Baum entsteht im Job* | ein Warnkasten, der `composer install` im CI-Image für kaputt erklärte — behoben mit `000-000-0021` |
| `deployment.md`, Ende des Pipeline-Abschnitts | „`composer audit` und das ‚0 Deprecations'-Gate **kommen mit `006-005`**" |
| `tests/README.md`, *In der Pipeline* | derselbe überholte Warnkasten, und der Ablauf ohne die neuen Schritte |

`tests/README.md` nennt jetzt beide Blöcke getrennt: den Testlauf mit dem Deprecation-Schritt
dahinter, und die drei Prüfungen der Stage `check`, die weder Datenbank noch Testserver
brauchen. Mit dem Hinweis, **warum** der Deprecation-Schritt auch nach einem roten PHPUnit
laufen muss.

### Verification: ausgeführt, nicht gelesen
Der Abschnitt *In der Pipeline* wurde Schritt für Schritt in Docker nachgespielt, mit
MySQL-Service und dem Job-Image `php:8.3-cli`:

| Befehl aus dem Dokument | Ergebnis |
|---|---|
| `install-php-extensions.sh` · `composer install` · `prepare-test-environment.sh` | durchgelaufen |
| `./vendor/bin/phpunit` | 249 Tests / 598 Assertions, 7 Failures |
| `deprecations-pruefen.sh` | **Exit 0** — 148 Zeilen, 4 Paare, 4 ausgenommen |
| `audit.sh` | **Exit 0** |
| `audit-ausnahmen-pruefen.sh` | **Exit 0** — 5 eingetragen, 5 greifen |
| `phpstan analyse --memory-limit=512M` | Exit 1, 47 Treffer (nicht blockierend) |

**Und die Probe aufs Exempel für den Fund-Ablauf.** Eine der fünf Ausnahmen (`CVE-2024-50343`)
kurzzeitig entfernt, dann dem dokumentierten Ablauf gefolgt:

- `audit.sh` wird rot und nennt die Kennung.
- Frage 1: Die betroffenen Bereiche lauten `>=4.0.0,<5.0.0` — **ohne Obergrenze innerhalb 4.x**.
  Kein Release behebt es.
- Frage 2: `symfony/validator` wird vom Framework benutzt; ein Weg darum herum existiert nicht.
- Frage 3: Ausnahme, einzeln nach Kennung, mit Epic `009` als Auflöser.

Das ist **genau die Entscheidung, die tatsächlich getroffen wurde**. Der Text führt also
dorthin, wohin er führen soll — was der Task ausdrücklich verlangt hat: Führt er woandershin,
ist der Text falsch, nicht die Entscheidung.
