---
id: 006-001-0003
title: Belegen, dass der Ist-Stack auflösbar ist
status: done
depends_on: [006-001-0002]
---

# Belegen, dass der Ist-Stack auflösbar ist

## Context
Dieser Task steht **nicht** im ursprünglichen Text von `006-001`. Er kommt hinzu, weil der
Zuschnitt des gesamten Epics auf einer Aussage ruht, die bisher niemand geprüft hat:

> Der Ist-Stack ist per Composer unter PHP 8.3 auflösbar, weil `silex/silex` und die
> Symfony-3.4-Komponenten nach oben offene `php`-Constraints haben. Nur `ramsey/uuid` und
> `doctrine/orm` müssen punktuell angehoben werden.

Stimmt das nicht, ist `006-002` (Root-Manifest für den Ist-Stack) nicht baubar — und die
Entscheidung vom 2026-09-08, `006` auf den Ist-Stack statt den Ziel-Stack zu schneiden, muss
zurückgenommen werden. **Das gehört vor das Manifest, nicht mitten hinein.**

Der Anlass ist konkret: Beim Planen dieser Story sah `doctrine/orm` mit `php: ^7.1` kurz wie
ein harter Blocker aus. Er ist es nicht, weil das Paket ohnehin auf ein Release wechselt —
aber genau solche Fälle findet man nur, indem man Composer wirklich rechnen lässt.

## Umfang

### Der Auflösungsversuch
In einem **Wegwerf-Verzeichnis**, nicht im Repo: ein `composer.json`, das den Ist-Stack
beschreibt, und ein Auflösungslauf ohne Installation (`composer update --dry-run`).

Zu setzen sind mindestens:
- `config.platform.php` auf **8.3** — die Version, auf der die Suite heute grün ist,
- die Pakete aus dem Inventar (`006-001-0002`), die im Code benutzt werden,
- `ramsey/uuid` auf `^4` und `doctrine/orm` auf ein **Release** statt des Dev-Branch-Pins,
- **ohne** `ellumilel/php-excel-writer` und `twig/twig` — ihre Konsumenten sind mit Epic `012`
  gefallen.

Ergebnis ist entweder ein auflösbarer Graph oder eine Liste konkreter Konflikte. Beides ist
ein gültiges Ergebnis; nur das Nichtwissen ist keins.

### Die Fragen, die dabei zu beantworten sind
- Welche **Doctrine-Version** löst gegen den heutigen Code auf? Der Dev-Branch-Pin
  `dev-bugfix-many2many` existiert, weil jemand einen Fehler brauchte, der in keinem Release
  war. Zu klären ist, ab welcher Version dieser Fix enthalten ist — und ob der Wechsel
  überhaupt einen Verhaltensunterschied bringt.
- Hält `dflydev/doctrine-orm-service-provider` mit seinem Pin `doctrine/orm ~2.3` die
  Auflösung auf? Der Epic-Text nennt ihn als ORM-3-Blocker; für den Ist-Stack (ORM 2) ist er
  womöglich unproblematisch.
- Zieht `ramsey/uuid ^4` weitere Änderungen nach sich? Die ID-Strategie `UUID` hängt daran.
- Bleiben `silex/silex` und Symfony 3.4 tatsächlich installierbar, oder fällt einer der
  transitiven Abhängigkeiten doch über eine PHP-Grenze?

### Was **nicht** dazugehört
Kein Manifest im Repo, kein `composer.lock`, kein `vendor/`-Umbau — das ist `006-002` und
`006-003`. Hier entsteht **Wissen**, kein Artefakt. Das Wegwerf-Verzeichnis wird nach dem
Lauf gelöscht; was bleibt, ist das Protokoll.

## Offene Fragen
- ~~Steht Composer zur Verfügung?~~ Ja, 2.6.6 lokal. Ein Container war nicht nötig, weil
  `config.platform.php` die PHP-Version der Maschine ohnehin überschreibt.

## Acceptance criteria
- [x] Ein Auflösungslauf gegen `platform.php = 8.3` ist durchgeführt; sein vollständiges
      Ergebnis ist festgehalten — auflösbar oder nicht.
- [x] Ist er auflösbar: Die aufgelösten Versionen der kritischen Pakete stehen im Ergebnis
      (`doctrine/orm`, `ramsey/uuid`, `silex/silex`, die Symfony-Komponenten).
- [x] Ist er **nicht** auflösbar: Jeder Konflikt ist benannt, mit der Frage, ob er lösbar ist
      — und falls nicht, ist der Zuschnitt von Epic `006` als offene Entscheidung markiert.
- [x] Für den Doctrine-Wechsel steht fest, ab welchem Release der Fix des Dev-Branch-Pins
      enthalten ist, oder dass sich das nicht klären lässt.
- [x] Das Wegwerf-Verzeichnis ist entfernt; im Repo liegt nichts davon.

## Verification
Das Protokoll des Auflösungslaufs gehört wörtlich ins Ergebnis, nicht zusammengefasst — eine
Aussage wie „löst sauber auf" ist genau das, was in `006-002` niemandem hilft, wenn es dann
doch klemmt.

Der Lauf muss **wiederholbar** sein: Das benutzte `composer.json` gehört mit ins Ergebnis, samt
dem Befehl, der es aufgelöst hat.

## Ergebnis

### Die Antwort: **ja, der Ist-Stack ist auflösbar** — aber nicht so, wie erwartet
Der Zuschnitt von Epic `006` hält. Allerdings ist der Preis höher als angenommen: Es sind
**nicht** nur `ramsey/uuid` und `doctrine/orm` anzuheben.

### Die Zwangskette, die den Umfang bestimmt
Der entscheidende Fund, aus dem alles Weitere folgt:

```
Your requirements could not be resolved to an installable set of packages.
  - doctrine/orm[2.14.0, ..., 2.20.13] require symfony/console ^4.2 || ^5.0 || ^6.0 || ^7.0
    -> found symfony/console[v4.2.0, ...] but it conflicts with your root
       composer.json require (^3.4).
```

Daraus:

1. PHP 8.3 → `doctrine/orm` muss auf ein **Release** (der Dev-Branch-Pin cappt auf `^7.1`).
2. Jedes ORM-Release ab 2.14 → verlangt **`symfony/console ^4.2`** oder höher.
3. Also: **Symfony 3.4 ist ausgeschlossen.** Der Sprung auf Symfony 4.4 ist keine Wahl,
   sondern eine Folge.
4. Nach oben gedeckelt wird Symfony 4 wiederum von `knplabs/console-service-provider`, das in
   seiner neuesten Fassung (v2.2.0) `symfony/console ^2.3|^3.0|^4.0` verlangt.

**Symfony 4.4 ist damit exakt bestimmt** — nicht 3.4, nicht 5.4.

### Zwei gültige Varianten für `006-002`
| Paket | heute (eingefroren) | **A** — frei aufgelöst | **C** — DBAL 2 behalten |
|---|---|---|---|
| `silex/silex` | v2.2.2 | v2.3.0 | v2.3.0 |
| `symfony/http-kernel` | v3.4.38 | v4.4.51 | v4.4.51 |
| `symfony/console` | 3.4 | v4.4.49 | v4.4.49 |
| `doctrine/orm` | `dev-bugfix-many2many` | 2.20.13 | 2.20.13 |
| **`doctrine/dbal`** | **v2.6.3** | **3.10.6** | **2.13.9** |
| **`doctrine/annotations`** | **v1.8.0** | **2.0.2** | **1.14.4** |
| `ramsey/uuid` | 3.8.0 | 4.9.3 | 4.9.3 |

Beide lösen auf (Exit 0). **Variante C ist die schonendere** und die Empfehlung für `006-002`:
Sie vermeidet den DBAL-2→3-Sprung, der der grösste API-Bruch der ganzen Liste ist
(`fetchAll()` entfernt, `Statement::execute()` umgebaut), und lässt `doctrine/annotations`
auf 1.x — beides Pakete, die der Framework-Code direkt anfasst.

Variante A ist erreichbar, sobald der Code darauf vorbereitet ist. Das ist keine Frage für
`006`.

### Das eigentliche Risiko: ausgerechnet dort ist die Suite dünn
`doctrine/orm` steht heute auf einem **eigenen Fork**:

```
source: github.com/area-net-gmbh/doctrine2
branch: bugfix-many2many
commit: 57e64c2730102f3f1f0072ee1c48f4d1c46c4026
Stand:  2018-08-07
```

Kein offizieller Doctrine-Branch, sondern ein sieben Jahre alter Firmen-Fork mit einem
**ManyToMany-Fix**. Der Fork ist noch erreichbar (HTTP 200), der Ist-Stand also reproduzierbar.

Ob der Fix in 2.20.13 enthalten ist, lässt sich aus dem Baum **nicht** klären — im Paket
selbst steht keine Spur davon, und ein Vergleich mit dem Upstream ist Arbeit für `006-002`.
Der praktische Nachweis wäre die Suite.

**Und genau da liegt das Problem:** Die Schreibprüfung des `MultijoinType` — der
ManyToMany-Pfad — ist eine der in `an_project/docs/technical.md` dokumentierten **Lücken** der
Suite. Sie ist mit der Vorlage nicht auslösbar, weil der einzige Multijoin (`PIM\File.tags`)
kein `acceptFrom` hat.

> **Für `006-002` heisst das:** Der Doctrine-Wechsel ist die riskanteste Einzeländerung des
> Epics, und ausgerechnet dort trägt das Testnetz am wenigsten. Wer ihn macht, sollte vorher
> entscheiden, ob ein gezielter ManyToMany-Test dazugehört — oder das Risiko ausdrücklich
> annehmen.

### Fünf abandoned Pakete — relevant für `006-005`
`doctrine/annotations`, `doctrine/cache`, `knplabs/console-service-provider`, `silex/silex`
(→ `symfony/flex`), `symfony/debug` (→ `symfony/error-handler`).

`composer audit` meldet das. Für das CI-Gate aus `006-005` ist vorher zu entscheiden, ob
„abandoned" den Lauf rot macht — sonst steht das Gate am ersten Tag auf rot, ohne dass eine
Sicherheitslücke vorliegt.

### Das benutzte Manifest (Variante C, wiederholbar)
```json
{
    "config": { "platform": { "php": "8.3.0" } },
    "require": {
        "php": "^8.3",
        "silex/silex": "^2.2",
        "doctrine/orm": "^2.14",
        "doctrine/dbal": "^2.13",
        "doctrine/annotations": "^1.14",
        "dflydev/doctrine-orm-service-provider": "^2.0",
        "knplabs/console-service-provider": "^2.0",
        "ramsey/uuid": "^4.0",
        "phpmailer/phpmailer": "^6.10",
        "symfony/console": "^4.4",
        "symfony/validator": "^4.4",
        "symfony/translation": "^4.4"
    }
}
```

```sh
composer update --dry-run --no-interaction   # Exit 0, 47 Pakete
```

`dflydev/doctrine-orm-service-provider` (v2.0.1) hält die Auflösung **nicht** auf — sein Pin
`doctrine/orm ~2.3` schliesst 2.20 ein. Der Epic-Text nennt ihn als ORM-3-Blocker; für den
Ist-Stack ist er unproblematisch.

### Aufgeräumt
Alle drei Wegwerf-Verzeichnisse sind entfernt; im Repo liegt nichts davon.
