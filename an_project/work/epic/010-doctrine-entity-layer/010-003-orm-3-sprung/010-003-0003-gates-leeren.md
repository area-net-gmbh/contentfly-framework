---
id: 010-003-0003
title: Die Gates auf den neuen Stand bringen
status: done
depends_on: [010-003-0002]
---

# Die Gates auf den neuen Stand bringen

## Context
Nach dem Sprung sind die verbliebenen PHPStan-Muster für ORM-2-APIs gegenstandslos, und die
Liste der abandoned Pakete ist leer.

- **PHPStan:** die Muster für die entfallenen Console-Commands, `createHelperSet()`,
  `EntityManager::create()` und die Lexer-Konstanten. **Regel 3:** Was nichts mehr trifft, macht
  den Lauf rot — die Muster müssen weg, nicht stehenbleiben.
- **`composer audit`:** `tools/ci/audit.sh` steht auf `--abandoned=ignore` mit dem Vermerk „auf
  `fail` umstellen, sobald Epic 010 durch ist". `010-002-0004` hat die Entscheidung hierher
  gegeben, weil `doctrine/cache` bis zum ORM-Sprung im Baum blieb. Jetzt ist sie fällig.
- **Die Dokumente:** `tech-stack.md` nennt ORM 2.20 als Stand, `deployment.md` die Zahl der
  Ausnahmen.

Was an Doctrine-Befunden übrig bleibt, verlässt das Epic **mit benanntem Auflöser** — nicht
stillschweigend.

## Acceptance criteria
- [x] `phpstan.neon.dist` enthält kein Muster mehr, das nichts trifft; der Lauf ist `[OK] No errors`.
- [x] `composer audit --locked` meldet **null** abandoned Pakete, und `tools/ci/audit.sh` steht auf `--abandoned=fail`.
- [x] Ein Gegentest belegt, dass `fail` greift: ein künstlich abandoned Paket macht den Lauf rot.
- [x] `tech-stack.md` und `deployment.md` beschreiben den erreichten Stand, mit gemessenen Zahlen.
- [x] Die Suite ist grün, und der PHP-8.4-Lauf ist auf dem neuen Stand nachgefahren — er ist seit `009-004-0002` die offene Frage zum blockierenden Job.

## Verification
`composer audit --locked`, `phpstan analyse --memory-limit=512M`, volle Suite. Für den
Gegentest zum `fail`-Schalter genügt ein Lauf mit einer Datei, die ein abandoned Paket
vortäuscht — er muss rot sein, sonst ist das Gate nur eine Behauptung.

## Ergebnis

**Alle drei Gates sind scharf, und zwei Ausnahmelisten sind leer.**

| Gate | Stand |
|---|---|
| `composer audit --locked` | 0 Advisories, **0 abandoned**, Schalter auf `--abandoned=fail` |
| Deprecation-Log | 0 Zeilen bei 0 Ausnahmen, auf PHP 8.3 **und** 8.4 |
| PHPStan | `[OK] No errors`; **eine** Ausnahme übrig, und die kommt aus DBAL |

### Der Audit-Schalter ist eingelöst, nicht verlängert worden

`tools/ci/audit.sh` stand seit `006-005-0001` auf `--abandoned=ignore`, mit dem Vermerk „auf
`fail` umstellen, sobald Epic 010 durch ist". Der Weg dahin, je Paket:

| Paket | gefallen mit |
|---|---|
| `silex/silex`, `knplabs/console-service-provider`, `symfony/debug` | Epic `009` |
| `doctrine/annotations` | `010-001-0005` |
| `doctrine/cache` | `010-003-0002`, mit ORM 3 |

**Beide Richtungen im Pipeline-Image gemessen**, weil ein Gate, das man nicht rot gesehen hat,
eine Behauptung ist:

| Probe | Exit |
|---|---|
| Ist-Stand | **0** — „Keine unausgenommene Sicherheitsmeldung im Lock." |
| Ein künstlich als `abandoned` markiertes Paket im Lock (`brick/math`) | **1**, mit Nennung des Namens |

Der Lock wurde dabei wiederhergestellt und ist unverändert.

Lokal verweigert das Skript den Dienst mit klarer Meldung — Composer 2.6.6 kennt `--abandoned`
nicht. Das ist die Untergrenze aus `006-005-0001` und funktioniert weiterhin.

### Von acht PHPStan-Ausnahmen ist eine übrig

Epic `009` hat acht benannte Muster über 31 Doctrine-Befunde an `010` übergeben. Sie sind nicht
gestrichen worden, sondern **gegenstandslos geworden** — und jedes Mal hat Regel 3 sie
eingefordert, statt dass jemand aufräumen musste:

| Muster | gegenstandslos mit |
|---|---|
| `newDefaultAnnotationDriver()` | `010-001-0003` |
| `AnnotationRegistry::registerFile/registerLoader` | `010-001-0005` |
| Die vier `Doctrine\Common\Cache\*`-Klassen | `010-002-0002` |
| `EntityManager::create()` | `010-003-0001` |
| Die Lexer-Token-Konstanten | `010-003-0001` |
| Die ORM-Console-Commands | `010-003-0002` |
| `ConsoleRunner::createHelperSet()` | `010-003-0002` |

Übrig: `Doctrine\DBAL\Tools\Console\Command\ReservedWordsCommand`. **Sie kommt aus DBAL,
nicht aus dem ORM** — Epic `010` löst sie nicht auf, und sie bleibt mit ihrem Vermerk stehen.

### Der PHP-8.4-Job ist blockierend geworden

Die offene Frage seit `009-004-0002`. Die `.gitlab-ci.yml` hat die Bedingung selbst benannt:
„Ob der Job blockierend werden kann, entscheidet ein Lauf auf 8.4 — nicht diese Zeile."

Der Lauf liegt jetzt **zweimal** vor, auf zwei verschiedenen Ständen, beide im Image
`php:8.4-cli` gegen einen `mysql:8.0`-Service mit dem Wortlaut des Jobs:

| | Stand | Ergebnis |
|---|---|---|
| `009-004-0002` | Symfony 7.4, ORM 2.20 | `OK (267 tests)`, 0 Deprecations |
| `010-003-0003` | Symfony 7.4, **ORM 3.7** | `OK (268 tests, 642 assertions)`, 0 Deprecations |

**Der Schalter ist gezogen.** Eine Bedingung, die eine Datei selbst benennt und die erfüllt ist,
gehört eingelöst — dieselbe Regel, an der die drei Gates hängen. Die Pipeline hat damit **kein
`allow_failure` mehr**.

### Nachweis

| Probe | Ergebnis |
|---|---|
| Volle Suite | `OK (268 tests, 642 assertions)`, 0 übersprungen |
| PHPStan | `[OK] No errors` |
| Deprecation-Gate | 0 Zeilen, 0 Ausnahmen |
| `composer audit` im Pipeline-Image | Exit 0; Gegenprobe Exit 1 |
| PHP 8.4 mit ORM 3 | grün, 0 Deprecations, Postausgang 0 Byte |
