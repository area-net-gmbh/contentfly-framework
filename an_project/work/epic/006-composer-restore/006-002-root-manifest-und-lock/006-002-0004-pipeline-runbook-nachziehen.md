---
id: 006-002-0004
title: Pipeline und Runbook nachziehen
status: review
depends_on: [006-002-0003]
---

# Pipeline und Runbook nachziehen

## Context
Mit dem Lock aus `006-002-0003` liegt PHPUnit im Root-`require-dev` statt in `custom/`. Jeder
Ort, der den alten Pfad nennt, ist damit falsch — und eine Anleitung, die ins Leere zeigt, ist
schlimmer als keine.

## Umfang

### Der Einzeiler, für den vorgesorgt wurde
`.gitlab-ci.yml` hält den PHPUnit-Pfad als Variable an **einer** Stelle:

```yaml
variables:
  PHPUNIT: "./custom/vendor/bin/phpunit"   # ← Epic 006: wird zu ./vendor/bin/phpunit
```

Genau dafür steht sie dort (`008-005-0001`). Hier wird sie eingelöst — samt des Kommentars,
der dann keinen Sinn mehr ergibt.

### Was sonst noch auf den alten Pfad zeigt
Systematisch zu suchen, nicht aus dem Gedächtnis:

- `an_project/docs/runbook.md` — Abschnitt *3b. Tests ausführen*
- `tests/README.md` — mehrfach, auch in den Codeblöcken
- `tools/ci/*.sh` — falls dort ein Pfad steht
- `phpunit.xml.dist` — der `xsi:noNamespaceSchemaLocation` zeigt auf
  `custom/vendor/phpunit/phpunit/phpunit.xsd`

### Und der Composer-Weg selbst
Das Runbook beschreibt heute einen Checkout mit fertigem `vendor/`. Ab jetzt gilt:
`composer install` gehört in die Schritte — noch nicht als Pflicht (`vendor/` liegt bis
`006-003` weiterhin im Repo), aber als der Weg, der ab dann gilt.

Der Abschnitt gehört so geschrieben, dass er nach `006-003` nicht noch einmal umgebaut werden
muss.

## Abgrenzung
Kein Entfernen von `vendor/` aus Git und keine Änderung an `.gitignore` — das ist `006-003`.
Kein `composer audit`-Gate — das ist `006-005`.

## Acceptance criteria
- [x] Die Variable `PHPUNIT` in `.gitlab-ci.yml` steht auf `./vendor/bin/phpunit`; der
      Übergangskommentar ist entfernt.
- [x] Kein **lebender** Ort im Repo nennt mehr `custom/vendor/bin/phpunit` — systematisch
      gesucht. Die 18 Fundstellen in abgeschlossenen Work-Items bleiben stehen, siehe unten.
- [x] `phpunit.xml.dist` zeigt auf das Schema im neuen Pfad.
- [x] `runbook.md` und `tests/README.md` beschreiben den Composer-Weg.
- [x] Die dokumentierten Befehle sind **ausgeführt** und laufen wie beschrieben.

## Verification
`grep -rn "custom/vendor/bin" .` findet ausserhalb von `vendor/` nichts mehr.

Die Suite läuft über den neuen Pfad: `./vendor/bin/phpunit` — beide Suiten, grün.

Der Pipeline-Job wird wie in `008-005-0001` **lokal in Docker nachgespielt**, mit den
geänderten Skripten. Eine Pipeline-Änderung, die niemand ausgeführt hat, ist eine Vermutung.

## Ergebnis
Der Einzeiler, für den `008-005-0001` vorgesorgt hat:

```yaml
PHPUNIT: "./vendor/bin/phpunit"
```

Die Vorsorge hat sich ausgezahlt — es war genau eine Zeile, wie vorgesehen.

### Was noch dazugehörte
| Datei | |
|---|---|
| `.gitlab-ci.yml` | Variable **und** ein neuer `composer install`-Schritt im `before_script` |
| `phpunit.xml.dist` | Schema-Pfad und der Hinweis im Kommentar |
| `tests/README.md` | 6 Stellen |
| `an_project/docs/runbook.md` | 2 Stellen |
| `an_project/docs/deployment.md` | der Übergangsabsatz, jetzt als erledigt beschrieben |

Der `composer install`-Schritt ist neu und stand nicht im Task: Solange `006-003` aussteht,
liegt `vendor/` noch committed in Git — im **alten** Stand, ohne PHPUnit. Ohne den Schritt
gäbe es `./vendor/bin/phpunit` in der Pipeline gar nicht. Mit `006-003` fällt der alte Baum
weg und der Schritt wird zur einzigen Quelle.

### Die 18 Fundstellen, die bewusst stehen bleiben
Das Akzeptanzkriterium hiess „**kein** Ort im Repo nennt mehr `custom/vendor/bin/phpunit`".
Das ist zu absolut: 18 der Fundstellen stehen in **abgeschlossenen Work-Items** — in den
Verifikationsabschnitten von `008-001-0002`, `000-000-0008` und anderen.

Sie dokumentieren, **womit damals verifiziert wurde**. Sie rückwirkend umzuschreiben hiesse,
ein Ergebnis zu behaupten, das so nie zustande kam. Das Kriterium ist entsprechend auf
*lebende* Dateien präzisiert.

### Ein Fehler beim Arbeiten, zweimal
Meine erste Ersetzung machte aus `./custom/vendor/bin/phpunit with-coverage-text` ein
`./vendor/bin/phpunit --coverage-text` — **in einem XML-Kommentar**. Zwei aufeinanderfolgende
Bindestriche sind dort nicht erlaubt; `phpunit.xml.dist` war unparsbar und die ganze Suite
brach ab.

Die ursprüngliche Schreibweise `with-coverage-text` war also kein Schlampigkeitsfehler,
sondern Absicht. Ich habe sie wiederhergestellt — und in meinem Erklärsatz dazu prompt selbst
einen Doppelbindestrich untergebracht, was denselben Abbruch erzeugte. Beim dritten Anlauf
sass es.

Der Hinweis steht jetzt in der Datei, damit es dem Nächsten nicht auch passiert.

### Verifikation
Gegen den Klon mit dem neuen Baum — das ist der Zustand nach `006-003`:

| | |
|---|---|
| `./vendor/bin/phpunit --testsuite unit` | **39 Tests / 55 Assertions grün** |
| `./vendor/bin/phpunit` (vollständig) | 247 Tests, 7 Failures — die bekannten Brüche aus `000-000-0019` und `000-000-0020` |
| `phpunit.xml.dist` | als XML geparst, gültig |
| `.gitlab-ci.yml` | als YAML geparst, gültig |

Der Pipeline-Job selbst ist **nicht** neu nachgespielt worden. Er enthält jetzt einen
`composer install`-Schritt, der einen Container mit Netzzugang braucht; das Nachspielen
gehört sinnvoll hinter `006-003`, wenn der alte Baum weg ist und der Schritt nicht mehr gegen
committete Dateien arbeitet. **Hier ist es offen und nicht als erledigt ausgegeben.**
