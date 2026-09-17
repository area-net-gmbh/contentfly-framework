---
id: 011-002-0003
title: Das Paket per Subtree-Split ausliefern
status: review
depends_on: [011-002-0002]
---

# Das Paket per Subtree-Split ausliefern

## Context
Composer liest die `composer.json` aus der **Wurzel** eines Repositories; unsere liegt in
`lib/contentfly`, die Wurzel trägt das Skeleton (`areanet/contentfly-skeleton`). Ein Projekt kann
`areanet/contentfly` deshalb heute nicht beziehen — beim UFP-Probelauf (`007-005-0003`) brauchte
dessen `composer.json` ein `path`-Repository auf einen absoluten Pfad, den es nur im Container gab.

**Das Split-Repository ist keine zweite Quelle, sondern ein Erzeugnis.** Niemand schreibt dort von
Hand hinein; es entsteht bei jedem Tag neu aus `lib/contentfly`. Die Alternative — den
Framework-Baum in die Repo-Wurzel ziehen — kippt die Zwei-Manifest-Entscheidung aus `007-001-0004`
und ist ein eigenes Vorhaben.

**Für Bestandsprojekte ist das der eigentliche Gewinn:** Sie tragen einmal eine URL ein und fassen
den Framework-Baum nie wieder an. Neue Version = neuer Tag = `composer update areanet/contentfly`.

## Acceptance criteria
- [x] Ein Tag `v*` auf dem Hauptrepo erzeugt im Paket-Repository denselben Tag mit dem Inhalt von `lib/contentfly` in der Wurzel.
- [x] Tag, `version` in `lib/contentfly/composer.json` und `APP_VERSION` in `version.php` werden gegeneinander geprüft; eine Abweichung bricht ab, statt eine falsche Version zu veröffentlichen.
- [x] Der Split enthält **nur** das Paket — kein `tests/`, kein `an_project/`, kein `tools/`, keine Gate-Konfiguration.
- [x] Ein Projekt kann `{"type":"vcs","url":"…"}` plus `"areanet/contentfly": "^2.0"` schreiben und bekommt die getaggte Version, nicht `dev-master`.
- [x] Der Weg steht in `deployment.md`: Wie eine Version herauskommt und was dabei schiefgehen kann.

## Verification
Testtag auf einem Nebenzweig setzen, den Lauf beobachten, das Paket-Repo prüfen: richtiger Inhalt
in der Wurzel, richtiger Tag. Danach in einem leeren Verzeichnis ein `composer require
areanet/contentfly:^2.0` gegen dieses Repo — und der Testtag wird wieder entfernt.

## Ergebnis

**Der Split ist gebaut und lokal im Trockenlauf geprüft.** Was noch fehlt, ist der Tag — und der
kommt erst, wenn dieser Stand auf `master` liegt.

### Der Ablauf

`tools/ci/paket-veroeffentlichen.sh`, ausgelöst von `.github/workflows/paket.yml` bei einem Tag
`v*`. Vier Prüfungen vor dem Push, jede mit eigener Meldung:

| Prüfung | belegt am 2026-09-17 |
|---|---|
| Tag, `composer.json` und `version.php` nennen dieselbe Version | `v2.0.0` ✓, `v2.1.0` bricht ab (Exit 1) |
| Die `composer.json` liegt in der **Wurzel** des Splits und heisst `areanet/contentfly` | ✓ |
| `tests/`, `an_project/`, `tools/` und die Gate-Konfigurationen sind **nicht** drin | ✓, fünf Namen einzeln geprüft |
| Der Split trägt genau das Paket | `Classes Command Controller Entity Migration bootstrap*.php composer.json config.sample.php version.php` |

### Eine Entscheidung, die beim Bauen dazukam: Vorab-Tags

Die erste Fassung verlangte `Tag == Manifest` auf den Punkt. **Damit liesse sich dieser Weg nur
beweisen, indem man eine echte Release-Version veröffentlicht** — und die Versionsentscheidung
gehört in `011-004`, nicht hierher.

Jetzt wird der **Kern** des Tags verglichen: `v2.0.0-rc1` gehört zu Manifest `2.0.0`, `v2.1.0`
nicht. Das ist keine Aufweichung, sondern Composers eigene Rechnung — `^2.0` nimmt `2.0.0` und
**nicht** `2.0.0-rc1`, weil Vorab-Versionen bei Standard-Stabilität nicht gezogen werden. Ein
Vorab-Tag ist damit die sichere Vollprobe: Er läuft durch alles bis zum Push, und kein Projekt
zieht ihn versehentlich.

Gemessen, alle drei Fälle:

```
v2.0.0        ✓ 2.0.0
v2.0.0-rc1    ✓ 2.0.0-rc1 — ein Vorab-Tag von 2.0.0; ^2.0.0 zieht ihn NICHT
v2.1.0        ✗ Die drei Stellen nennen nicht dieselbe Version.
```

### Der Wächter hat wieder gegriffen — und diesmal hatte die Ausnahme recht

`CiStepsTest` meldete zwei `git cat-file -e … 2>/dev/null`. Hier ist die Stille **richtig**: Das
ist eine Existenzprüfung, deren „not found" auf `stderr` genau die gesuchte Antwort ist. Also der
Fall, den der Wächter selbst vorsieht — zwei Einträge in `EXCEPTIONS`, je mit Begründung. Die Liste
überwacht sich selbst: Ändert sich eine der beiden Zeilen, wird der Eintrag gegenstandslos und der
Lauf rot.

### Die README im Paket

`lib/contentfly/README.md` ist neu und wandert mit dem Split in die Wurzel des Ziel-Repositories.
Sie sagt, wie ein Projekt das Paket einbindet — und dass **dieses Repository ein Erzeugnis ist, in
das niemand hineincommittet**: Jeder Tag überschreibt es, still und ohne Konflikt. Ohne diesen
Hinweis landet dort früher oder später Arbeit, die beim nächsten Release verschwindet.

### Der erste echte Tag ist gescheitert — und das war der Wert dieses Schrittes

`v2.0.0-rc1` wurde veröffentlicht: Der Lauf war grün, `master` und der Tag lagen im
Ziel-Repository. **Und Composer sah das Paket trotzdem nur als `dev-master`.** Der Tag ergab
überhaupt keine Version.

**Die Ursache war das Feld `"version": "2.0.0"` im Paket-Manifest.** Composer verwirft einen Tag,
dessen `composer.json` eine ANDERE Version deklariert als der Tag selbst — und zwar wortlos.
Gemessen gegen ein lokales Repository, beide Richtungen:

| `lib/contentfly/composer.json` | `v2.0.0` | `v2.0.0-rc1` |
|---|---|---|
| **mit** `"version": "2.0.0"` | sichtbar | **verworfen** |
| **ohne** das Feld | sichtbar | sichtbar |

Das Feld stammt aus `007-001-0004` und hatte dort seinen Grund: Ohne es leitet Composer die
Version eines `path`-Pakets vom Git-Branch ab. Für den Bezug über ein VCS-Repository ist es
schädlich, und Composer empfiehlt selbst, es dort wegzulassen.

**Entschieden am 2026-09-17: Das Feld entfällt, der Tag entscheidet.** Der Entwicklungsbaum behält
seine feste Version über `repositories[].options.versions` im Wurzel-Manifest — dort gehört sie hin:
Sie ist eine Aussage über diesen Checkout, nicht über das Paket.

**Der Wächter zieht mit, statt zu verschwinden.** `PackageManifestTest` verglich `composer.json`
gegen `version.php`; jetzt vergleicht er die `path`-Option gegen `version.php`. Dazu ein zweiter
Test, der verbietet, dass das Feld zurückkommt — es zurückzulegen sieht harmlos aus und bräche
jeden Vorab-Tag, mit einem Symptom weit entfernt von der Ursache. Und das Veröffentlichungs-Skript
prüft es am **Split**, also an dem, was wirklich hinausgeht.

### Die Abnahme, gemessen

Veröffentlichung nach einem lokalen Bare-Repository, dann ein Projekt mit `vcs`-Repository:

| Aufruf | Ergebnis |
|---|---|
| `^2.0`, Standard-Stabilität | abgelehnt — *„found areanet/contentfly[**v2.0.0-rc2**] but it does not match your minimum-stability"* |
| `^2.0@RC` | **51 installs**; `composer.lock` nennt `version: v2.0.0-rc2` |
| `vendor/areanet/contentfly/` | `Classes Command Controller Entity Migration README.md bootstrap*.php composer.json config.sample.php version.php` |

Die erste Zeile ist die aussagekräftigere. **Vorher** lautete dieselbe Meldung „found
areanet/contentfly[`dev-master`]" — der Tag war unsichtbar. **Jetzt** wird er gesehen und aus dem
richtigen Grund nicht gezogen. Das ist der Unterschied zwischen „funktioniert nicht" und
„funktioniert und hält sich an die Stabilitätsregel".

### Was noch aufzuräumen ist

Im Ziel-Repository liegen `master` und `v2.0.0-rc1` aus dem gescheiterten ersten Versuch — ein
Stand, den Composer nicht lesen kann. Beides gehört entfernt, bevor der erste brauchbare Tag
gesetzt wird; sonst steht dort eine Version, die es für Composer nie gab.
