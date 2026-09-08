---
id: 006-002-0004
title: Pipeline und Runbook nachziehen
status: todo
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
- [ ] Die Variable `PHPUNIT` in `.gitlab-ci.yml` steht auf `./vendor/bin/phpunit`; der
      Übergangskommentar ist entfernt.
- [ ] Kein Ort im Repo nennt mehr `custom/vendor/bin/phpunit` — systematisch gesucht, nicht
      aus dem Gedächtnis.
- [ ] `phpunit.xml.dist` zeigt auf das Schema im neuen Pfad.
- [ ] `runbook.md` und `tests/README.md` beschreiben den Composer-Weg.
- [ ] Die dokumentierten Befehle sind **ausgeführt** und laufen wie beschrieben.

## Verification
`grep -rn "custom/vendor/bin" .` findet ausserhalb von `vendor/` nichts mehr.

Die Suite läuft über den neuen Pfad: `./vendor/bin/phpunit` — beide Suiten, grün.

Der Pipeline-Job wird wie in `008-005-0001` **lokal in Docker nachgespielt**, mit den
geänderten Skripten. Eine Pipeline-Änderung, die niemand ausgeführt hat, ist eine Vermutung.
