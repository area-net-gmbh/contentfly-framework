---
id: 006-003-0003
title: Die Dokumentation auf den neuen Ablauf bringen
status: todo
depends_on: [006-003-0002]
---

# Die Dokumentation auf den neuen Ablauf bringen

## Context
Nach `006-003-0001` ist ein frischer Checkout **ohne `composer install` nicht lauffähig**.
Jede Anleitung, die das nicht sagt, führt den nächsten in die Irre — und zwar mit einer
Fehlermeldung, die den Grund nicht nennt (fehlende Klassen, kein Autoloader).

## Umfang

### Wo `composer install` hingehört
| Datei | Was zu ändern ist |
|---|---|
| `an_project/docs/runbook.md` | Ein Schritt **vor** allem anderen. Heute steht dort noch, Composer sei erst nach Epic `006` nutzbar und der Baum liege eingefroren im Repo. |
| `tests/README.md` | Der Ablauf beginnt heute mit `docker compose up -d`; davor gehört die Installation. |
| `an_project/docs/deployment.md` | Beschreibt den Zustand bereits als Ziel — jetzt als erreicht. |
| `an_project/docs/technical.md` | Der Abschnitt *Warum `vendor/` in Git liegt* beschreibt eine Notlösung, die es nicht mehr gibt. |

### `technical.md` — nicht löschen, umschreiben
Der Abschnitt erklärt, **warum** der Baum eingefroren wurde: um Contentfly ohne Ausfallzeit
auf PHP 8 weiterzubetreiben. Das ist Projektgeschichte und erklärt, warum der Baum aussah, wie
er aussah — 27 Pakete als `source` ohne `.git`, kein Manifest, ein Doctrine-Fork von 2018.

Wer das streicht, nimmt dem nächsten die Erklärung für Dinge, die noch eine Weile
nachwirken. Der Abschnitt gehört in die **Vergangenheitsform** und mit dem Vermerk, wodurch
er abgelöst wurde.

### Der Hinweis, der überall fehlt
„Ohne `composer install` läuft nichts" ist kein Nebensatz. Es ist die erste Änderung seit
Jahren, die einen frischen Checkout unbrauchbar macht, bis ein Befehl lief.

An jede Stelle, die einen Ablauf beschreibt — nicht nur in eine.

## Abgrenzung
Keine Änderung an der Pipeline; `.gitlab-ci.yml` hat den `composer install`-Schritt seit
`006-002-0004`. Keine Änderung an `breaking-changes.md` — der Ausbau betrifft dieses Repo,
nicht die Bestandsprojekte, die eine ausgelieferte Version bekommen.

## Acceptance criteria
- [ ] `runbook.md` beginnt mit der Installation der Abhängigkeiten.
- [ ] `tests/README.md` nennt sie vor dem Datenbank-Schritt.
- [ ] `technical.md` beschreibt den eingefrorenen Baum in der Vergangenheitsform, mit dem
      Hinweis, wodurch er abgelöst wurde — die Begründung von damals bleibt lesbar.
- [ ] `deployment.md` beschreibt den Zustand als erreicht.
- [ ] Kein Dokument behauptet mehr, der Vendor-Baum liege im Repo.
- [ ] Die Dauer aus `006-003-0002` steht im Runbook — wer zum ersten Mal installiert, soll
      wissen, ob er eine Minute oder zehn wartet.

## Verification
Die beschriebenen Befehle werden **ausgeführt**, nicht nur gelesen — dieselbe Regel wie in
`008-005-0004`. Eine Anleitung, die niemand nachgespielt hat, ist keine.

Konkret: Aus dem frischen Klon von `006-003-0002` heraus Schritt für Schritt dem Runbook
folgen, bis die Suite läuft.
