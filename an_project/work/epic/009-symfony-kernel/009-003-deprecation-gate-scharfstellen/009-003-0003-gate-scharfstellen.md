---
id: 009-003-0003
title: Das Gate scharfstellen und den Rest an Epic 010 übergeben
status: done
depends_on: [009-003-0001, 009-003-0002]
---

# Das Gate scharfstellen und den Rest an Epic 010 übergeben

## Context
Der Abschluss. Was dann noch in PHPStan steht, kommt **ausschliesslich** aus Doctrine:

| Quelle | Fundstellen | Zuständig |
|---|---|---|
| `doctrine/orm` (entfallende Klassen, `EnsureProductionSettingsCommand`) | 19 | Epic `010` |
| `doctrine/cache` (abandoned, fällt in 2.0) | 7 | Epic `010` |
| `doctrine/annotations` (abandoned, Attribute statt Annotationen) | 4 | Epic `010` |

Die Laufzeit-Hälfte des Gates steht schon: `tools/ci/deprecations-ausnahmen.txt` ist mit
`009-002-0006` auf **null Einträge** gegangen, und das Serverlog zeigt **null Deprecations**.
Das Gate hat die Streichung der letzten Ausnahme selbst eingefordert.

Zu entscheiden bleibt die statische Hälfte, und die Konfiguration hat die Bedingung selbst
formuliert:

> **WANN DER JOB BLOCKIEREND WIRD.** Sobald `vendor/`-fremde Treffer auf null stehen. Solange
> die Liste von Silex und den Symfony-4.4-Komponenten dominiert wird, hinge das Gate an Epic
> 009 — und ein Gate, das eine andere Story erst grün machen muss, wird abgeschaltet.

Silex ist weg. Die Bedingung ist zu prüfen: Stehen die eigenen Treffer auf null, wenn man die
Doctrine-Meldungen ausnimmt? Und wenn ja — wie werden sie ausgenommen, ohne das Gate zu
entwerten? Eine Baseline würde alles einfrieren, auch das, was morgen dazukommt.

## Acceptance criteria
- [x] Es ist entschieden und begründet, ob der PHPStan-Job blockierend wird, und mit welchem
      Level. Beides steht in `phpstan.neon.dist`, wo die bisherigen Entscheidungen stehen.
- [x] Wenn ausgenommen wird, dann **benannt** — je Meldung mit Grund und aufl��sendem Epic, nach
      derselben Regel wie die Deprecation- und Audit-Ausnahmen aus `006-005`. Keine Baseline,
      solange sie mehr einfrieren würde als die benannten Fälle.
- [x] Der PHPStan-Lauf braucht mehr Speicher als die Vorgabe: Mit 128 MB stürzt er ab
      (`PHPStan process crashed because it reached configured PHP memory limit`). Der Aufruf in
      `.gitlab-ci.yml` trägt das, statt es dem Zufall der Runner-Konfiguration zu überlassen.
- [x] `tools/ci/deprecations-ausnahmen.txt` ist leer und bleibt es — der Zustand aus
      `009-002-0006` ist bestätigt, nicht angenommen.
- [x] Was an Epic `010` übergeht, steht dort oder in seinem Epic-Text, nicht nur hier.

## Verification
`./vendor/bin/phpstan analyse --memory-limit=1G` mit dem entschiedenen Level und den
entschiedenen Ausnahmen. `sh tools/ci/deprecations-pruefen.sh` gegen ein frisches Serverlog.
Beide Gates in der `.gitlab-ci.yml` so, wie sie laufen sollen.

## Ergebnis

**Beide Hälften des Gates sind scharf. PHPStan meldet `[OK] No errors`, und der Job ist
blockierend.**

### Die Bedingung war selbst formuliert, und sie ist erfüllt

`phpstan.neon.dist` schrieb: „Sobald `vendor/`-fremde Treffer auf null stehen." Von 115
Meldungen sind **31** übrig, und **alle 31 kommen aus Doctrine**:

| Quelle | Meldungen | Was Epic `010` daran tut |
|---|---|---|
| `doctrine/orm` 2.x (Console-Commands, `EntityManager::create()`, Lexer-Konstanten) | 20 | ORM 2 → 3 |
| `doctrine/cache` (abandoned) | 7 | Ersatz |
| `doctrine/annotations` (abandoned) | 4 | Attribute statt Annotationen |

Sie stehen als **benannte Ausnahmen** in `phpstan.neon.dist` — je Eintrag ein Muster über die
**Meldung**, nicht über Datei und Zeile, mit Epic `010` als auflösendem Ticket. Derselbe Aufbau
wie `tools/ci/deprecations-ausnahmen.txt` und `config.audit.ignore`, und aus demselben Grund:
Eine Zeilennummer hätte die Liste bei jeder Codeänderung brechen lassen.

### Die Liste überwacht sich selbst — in beide Richtungen gemessen

| Probe | Ergebnis |
|---|---|
| Eine Ausnahme, die nichts mehr trifft | rot: „Ignored error pattern … was not matched" |
| Eine neu eingebaute eigene Deprecation (`Console::add()`) | rot, mit Datei und Zeile |
| Keins von beidem | `[OK] No errors` |

Das ist genau die Eigenschaft, die bei den anderen beiden Gates Regel 3 heisst, und PHPStan
bringt sie von sich aus mit. **Keine Baseline** — sie hätte den Ist-Stand eingefroren statt ihn
zu benennen.

### Level 0 bleibt, aber die Begründung ist eine andere

Der ursprüngliche Grund war, dass ein höherer Level auf einem Silex-2-Baum viele Typfehler
hereinzöge, in denen die Deprecations untergehen. Silex ist weg, und die Rechnung sieht anders
aus: **Auf Level 0 stehen null eigene Typfehler.** Die sieben, die es gab, sind mit
`009-003-0002` behoben — darunter eine Konstante, die es nicht gibt.

Der Level bleibt trotzdem bei 0. Ihn anzuheben ist eine eigene Entscheidung mit eigenem
Aufwand, und dieses Gate hat einen anderen Zweck; die Deprecation-Regeln greifen ohnehin
unabhängig vom Level. Das steht als Notiz in der Konfiguration, nicht als stille Beibehaltung.

### Der Speicher

Die Pipeline ruft PHPStan seit `006-005-0004` mit `--memory-limit=512M`, mit einer Messung
daneben. **Das reicht weiterhin** — der Lauf ist damit vollständig. Lokal ohne Angabe stürzt er
ab (`PHPStan process crashed because it reached configured PHP memory limit: 128M`), was den
Grund für die Angabe bestätigt.

### Ein Nebenbefund: eine stale Begründung in der Pipeline

Der PHP-8.4-Job trug „Der Silex-Stand ist auf 8.4 nicht erprobt" als Grund für
`allow_failure: true`. Silex ist weg; der Satz stimmte nicht mehr. Er ist richtiggestellt: Was
bleibt, ist, dass der **Symfony-7.4**-Stand auf 8.4 in dieser Pipeline noch nicht gemessen
wurde. Ob der Job blockierend werden kann, entscheidet ein Lauf auf 8.4 — nicht eine Zeile
Kommentar. **Nicht gemessen in diesem Task**, weil er ein anderes Gate betrifft.

### Nachweis

| | |
|---|---|
| `phpstan analyse --memory-limit=512M` | `[OK] No errors` |
| `allow_failure` beim PHPStan-Job | entfernt |
| `tools/ci/deprecations-ausnahmen.txt` | 0 aktive Zeilen |
| Deprecation-Gate gegen frisches Serverlog | 0 Zeilen, 0 Paare, 0 Ausnahmen |
| Volle Suite | `OK (266 tests, 641 assertions)`, 0 übersprungen |
