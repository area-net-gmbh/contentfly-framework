---
id: 011-002-0004
title: Den Weg von aussen fahren, ins Runbook schreiben und als Gate festnageln
status: review
depends_on: [011-002-0003]
---

# Den Weg von aussen fahren, ins Runbook schreiben und als Gate festnageln

## Context
Das Ziel der Story ist erst eingelöst, wenn ein Projekt **ohne Kenntnis dieses
Arbeitsverzeichnisses** installiert werden kann. Bis hierher ist das gebaut, aber nicht gefahren —
und ein Weg, den niemand fährt, verrottet. Entschieden am 2026-09-16: echter Durchlauf **und**
CI-Gate, nicht nur ein Datum in einem Dokument.

**Der Lauf muss das `path`-Repository dieses Repos umgehen.** Läuft er im Baum, beweist er nichts:
`composer install` fände das Paket lokal und niemand merkte, dass es von aussen nicht beziehbar ist.

## Acceptance criteria
- [x] Ein Durchlauf von aussen ist einmal von Hand gefahren: frisches Verzeichnis ausserhalb des Repos, Skeleton-Dateien hinein, `composer install` gegen das Paket-Repository, `appcms:install`, ein API-Aufruf antwortet.
- [x] Der Lauf bezieht das Paket nachweislich **nicht** über `path` — belegt an `composer.lock` (`source`/`dist` zeigen auf das Paket-Repo).
- [x] `runbook.md` beschreibt genau diesen Weg als „So startet ein neues Projekt", inklusive der Zugangsdaten, die ein Entwicklerrechner dafür braucht.
- [x] Ein Gate im Actions-Workflow wiederholt ihn bei jedem Lauf und wird rot, sobald das Paket nicht mehr beziehbar ist.
- [x] `migration.md` Phase 2 nennt den Bezugsweg für ein Bestandsprojekt — was in dessen `composer.json` steht und wie ein Update ankommt.
- [x] Volle Suite, PHPStan, Deprecation-Gate grün.

## Verification
Das Gate läuft grün. Gegenprobe: Mit einer Constraint auf eine Version, die es nicht gibt, wird es
rot — und die Meldung sagt, woran es lag.

## Ergebnis

**Ein Projekt kann Contentfly aufsetzen, ohne dieses Arbeitsverzeichnis zu kennen.** Von Hand
gefahren und als Gate festgenagelt.

### Der Durchlauf, gemessen am 2026-09-17

Frisches Verzeichnis **ausserhalb** des Repos, die acht Skeleton-Einträge hinein, ein eigenes
`composer.json` mit `vcs`-Repository statt `path`:

| Schritt | Ergebnis |
|---|---|
| `composer install` gegen die echte GitHub-URL | **55 installs**, `areanet/contentfly (v2.0.0-rc2)` |
| `composer.lock` | Version `v2.0.0-rc2`, Quelle `git@github.com:area-net-gmbh/contentfly-framework-dist.git` — **kein Pfad, kein `dev-`** |
| `appcms:install` | Schema angelegt, Basisdaten erzeugt |
| `GET /api/config` | `{"data":{"devmode":false},"errors":null,"meta":{…,"version":"2.0.0","projectVersion":"0.0.0",…}}` |
| `POST /auth/login` → `POST /api/list` | Token, dann ein Benutzer im Envelope aus `011-001` |

Die letzte Zeile ist mehr als „es antwortet": Wer das Paket bezieht, bekommt **die API, die dieses
Release zusichert** — Erfolg und Fehler in derselben Hülle.

### Das Gate prüft zwei Dinge, die still durchgingen

`tools/ci/bezugsweg-pruefen.sh`, eigener Job `check: Bezugsweg von aussen` in der Pipeline. Es
wiederholt den Durchlauf — und sieht dabei auf zwei Dinge, die ein blosses „läuft durch" nicht
erwischt:

- **Die Quelle darf kein Pfad sein.** Liefe die Prüfung im Baum, fände `composer install` das
  Paket lokal, der Lauf wäre grün, und dass es von aussen nicht beziehbar ist, fiele niemandem auf.
- **Die Version darf nicht mit `dev-` beginnen.** Genau der Fehler aus `011-002-0003`: Ein
  `version`-Feld im Paket-Manifest verwirft jeden abweichenden Tag, wortlos. Der Lauf wäre grün
  gewesen, und das Paket trotzdem unbrauchbar. Die Fehlermeldung des Gates nennt diesen Grund
  zuerst, weil er der wahrscheinlichste ist.

Dazu die Antwortform: `data` / `errors` / `meta`. Ein Paket, das antwortet, aber in alter Form,
wäre ein falscher Stand im Paket-Repository — sichtbar nur, wenn man nicht bloss den Statuscode
zählt.

**Eigener Job, nicht Teil des Testlaufs.** Ein Fehlschlag hier heisst etwas anderes: nicht „der
Code ist kaputt", sondern „das Paket ist von aussen nicht beziehbar". Zwei Aussagen, zwei Jobs.

### Eine Zeichenkette an zwei Stellen — und warum das so bleibt

Die Constraint `^2.0@RC` steht im Runbook **und** im Gate. Das ist Absicht und im Skript
begründet: Eine Constraint, die in der Doku anders lautet als im Gate, prüft einen anderen Weg als
den beschriebenen. Das `@RC` fällt weg, sobald `011-004` die Release-Version entscheidet und
`v2.0.0` gesetzt ist — beide Stellen tragen den Hinweis.

### Der Sprachwächter, zum dritten Mal in dieser Story

`CiStepsTest` meldete vier stille Stellen — Aufräum-`kill`, das `wait` dahinter, den
Hintergrundserver und den Wartelauf. Bei allen vieren ist die Stille richtig, und es sind
dieselben Muster, die für `prepare-test-environment.sh` längst begründet stehen; also vier
Einträge in `EXCEPTIONS` statt eines Umbaus.

`EnglishOnlyTest` verlangte danach eine Ausnahme für den deutschen Skriptnamen — und **wies eine
zweite zurück**, die ich vorsorglich dazugelegt hatte: `paket-veroeffentlichen.sh` wird gar nicht
als deutsch erkannt, die Ausnahme wäre eine Erlaubnis ins Leere gewesen. Die Liste überwacht sich
selbst, und sie hatte recht.

**Verifiziert:** Unit-Suite `312 Tests, 1026 Assertions`; das Gate lokal gegen das echte
Paket-Repository grün.

### Der erste CI-Lauf des Gates: Exit 127

`ssh-keyscan` gibt es in `php:8.3-cli` nicht — das Image bringt kein `openssh-client` mit. Die
Meldung nannte nur dieses eine Kommando; **gefehlt hätte auch `ssh` selbst**, und damit wäre der
`composer install` über SSH ohnehin unmöglich gewesen.

**Warum es in `paket.yml` ohne ging:** Jener Job läuft **ohne** `container:` auf `ubuntu-latest`,
und das Runner-Image bringt `openssh-client` mit. Wer die beiden Workflows einmal vereinheitlicht,
fällt genau hier hinein — deshalb steht die Begründung in `install-php-extensions.sh` und nicht als
Zeile in einem der beiden Workflows.

Nebenbei: Die naheliegende Ausgabe `ssh -V 2>&1` hätte `CiStepsTest` ausgelöst — `ssh` schreibt
seine Version nach `stderr`, und das Einfangen sieht aus wie ein unterdrückter Schritt. Statt einer
Ausnahme im Wächter fragt die Zeile jetzt `dpkg-query`. Eine Ausnahme einzutragen, nur um eine
Versionsnummer zu zeigen, wäre ein schlechter Tausch gewesen.

### Der zweite CI-Lauf: `plugins/` gibt es in einem frischen Checkout nicht

```
cp: cannot stat '/__w/.../plugins': No such file or directory
```

**Git führt keine leeren Verzeichnisse**, und `.gitignore` ignorierte `plugins/*` ohne Ausnahme —
also lag dort nichts, und das Verzeichnis existierte in keinem frischen Checkout. Das
Wurzel-Manifest bildet `Plugins\` aber darauf ab, und der Runbook-Abschnitt sagt, man solle es
mitkopieren.

**Lokal ist das nie aufgefallen**, und der Grund ist der Kern der Sache: Auf dieser Maschine lag
`plugins/` da — mit acht Verzeichnissen aus einem Testlauf vom 14. September, der hart abgebrochen
war, bevor sein `tearDown()` greifen konnte. Ein Fehler, den eigener Müll verdeckt.

Behoben an der Quelle statt im Gate: `plugins/.gitkeep` plus die Ausnahme in `.gitignore` — genau
das Muster, das `data/` seit jeher hat (`data/*/*` und `!data/*/.gitkeep`) und mit derselben
Begründung: „Laufzeitdaten bleiben draussen, die Verzeichnisse selbst gehören ins Repo."

**Geprüft, ob die Altlasten Schaden anrichten konnten:** nein. Plugins werden nicht automatisch
erkannt — weder `custom/app.php` noch der `PluginManager` durchsuchen das Verzeichnis, sie müssen
ausdrücklich registriert werden. Die acht Verzeichnisse lagen wirkungslos herum. Entfernt sind sie
trotzdem.

### Der dritte CI-Lauf: `Host key verification failed` — und meine eigene unterdrückte Diagnose

Composer kam bis zum Klonen und scheiterte an der Host-Key-Prüfung. **Und ich konnte nicht sagen,
warum**, weil der Schritt davor seine Ausgabe wegwarf:

```
ssh-keyscan -t ed25519 github.com >> ~/.ssh/known_hosts 2>/dev/null
```

Leer geblieben oder nie gelesen — beides sah gleich aus. **Derselbe Fehler, den ich zwei Stunden
vorher am Datenbank-Wartelauf behoben hatte**, und ich habe ihn beim Schreiben dieses Workflows
noch einmal gemacht.

Drei Änderungen, jede gegen eine andere der möglichen Ursachen:

| | |
|---|---|
| kein `2>/dev/null` mehr, dazu eine Gegenprobe auf `^github.com ` | eine leere Datei bricht jetzt **hier** ab, mit einer Meldung, die auf sie zeigt — statt zwei Schritte später auf den Deploy Key |
| `$HOME/...` statt `~/...` | die Tilde löst die Shell auf, die git für `GIT_SSH_COMMAND` startet; welche das ist, hängt vom Aufrufer ab |
| `-o UserKnownHostsFile=` ausdrücklich | damit nicht offenbleibt, welche Datei `ssh` liest |

Dazu `-t rsa,ecdsa,ed25519` statt nur `ed25519`: Wenn GitHub für eine Verbindung einen anderen
Algorithmus aushandelt, hilft ein einzelner Schlüssel nicht.

**Dieselbe Lücke stand in `paket.yml`** und ist dort mitbehoben — sie fiel nur nicht auf, weil
jener Job ohne `container:` läuft. Eine Lücke, die sich erst woanders zeigt, ist trotzdem eine.

### Der vierte CI-Lauf: `404` von der GitHub-API — und der wichtigste Fund für ein Projekt

SSH trug, Composer löste das Paket auf, fand `v2.0.0-rc2` und lud alle 55 Pakete. Dann:

```
https://api.github.com/repos/…/zipball/b8e22a7a…  →  404 Not Found
Source fallback is disabled. Not trying alternative sources.
```

**Composer bezieht ein Paket am liebsten als `dist`, also als Zip, und holt es bei GitHub über die
REST-API.** Die kennt einen SSH-Deploy-Key **nicht** — ein SSH-Schlüssel authentifiziert `git`,
nicht die API. Bei einem **privaten** Repository antwortet sie mit `404`, also so, als gäbe es das
Paket gar nicht.

**Lokal war es nie zu sehen**, und der Grund ist derselbe wie beim `plugins/`-Fund: Meine Maschine
wich auf `source` aus („Cloning … from cache"), weil dort der Rückfall erlaubt ist. In der CI ist
er abgeschaltet.

**Das trifft nicht die CI, sondern jedes Projekt.** Deshalb steht die Behebung nicht im Gate,
sondern in der Anleitung — und im Gate nur, weil es dieselbe Anleitung fährt:

```json
"config": { "preferred-install": { "areanet/contentfly": "source" } }
```

`source` heisst *klonen statt herunterladen*, und das geht über SSH — mit genau dem Zugang, den ein
Projekt ohnehin braucht. Die Zeile betrifft **nur dieses eine Paket**; die anderen 54 kommen von
Packagist und weiterhin als Zip.

**Die Alternative wäre ein API-Token** je Entwickler und je CI (`composer config
github-oauth.github.com …`). Das war schon bei der Wahl des Deploy Keys die verworfene Variante:
Ein Token hängt an einem Konto, ein Deploy Key an einem Repository.
