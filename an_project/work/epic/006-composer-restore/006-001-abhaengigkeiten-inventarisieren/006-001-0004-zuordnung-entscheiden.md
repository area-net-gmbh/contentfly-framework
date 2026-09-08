---
id: 006-001-0004
title: Zuordnung entscheiden und begründen
status: review
depends_on: [006-001-0002, 006-001-0003]
---

# Zuordnung entscheiden und begründen

## Context
Hier wird entschieden, was `006-001-0002` nur erfasst hat: Jedes Paket bekommt eine der vier
Kategorien **Root**, **`custom/`**, **`require-dev`** oder **entfällt** — je mit einem Satz
Begründung.

Das ist mehr als Buchhaltung. Die Zuordnung prägt die Vorlage, an der sich jedes migrierende
Projekt orientiert (Epic `007`): **Was im Root steht, ist Framework-Sache und wird
mitgeliefert; was in `custom/` steht, verantwortet das Projekt.** Diese Grenze zieht dieser
Task, und sie wird danach nur noch schwer verschoben.

## Umfang

### Die vier Grenzfälle — der eigentliche Kern
Vier Pakete liegen heute in `custom/`, gehören aber möglicherweise ins Framework. Der
Story-Text macht Vorschläge; sie sind zu **prüfen und zu begründen**, nicht zu übernehmen:

| Paket | Vorschlag | Begründung im Story-Text |
|---|---|---|
| `firebase/php-jwt` | Root | Epic `013` baut JWT in das Framework ein, nicht in ein Projekt. |
| `vlucas/phpdotenv` | Root | Konfiguration über Umgebungsvariablen ist Infrastruktur jedes Projekts. |
| `sentry/sentry` | Root | Fehler-Reporting gehört zum Betrieb, nicht zur Fachlichkeit. |
| `onelogin/php-saml` | `custom/` | Ein SAML-Identity-Provider ist die Entscheidung eines konkreten Kunden. |

Die Prüffrage ist jeweils dieselbe: **Benutzt der Framework-Code das Paket heute?** Ein
`grep` über `lib/` beantwortet das. Ein Paket, das nur `custom/` benutzt, gehört nicht ins
Root — auch wenn es „nach Infrastruktur klingt". Umgekehrt gehört ein Paket, das `lib/`
benutzt, ins Root, selbst wenn es heute in `custom/composer.json` steht.

`phpmailer` ist damit zugleich entschieden: Das Framework verschickt selbst Mails
(`MAILER_*` in `Classes/Config.php`, `Classes/Mailer.php`) — Root. Das löst auch die
Autoloader-Dublette auf.

> **Vorsicht bei `sentry/sentry` und `vlucas/phpdotenv`:** Beide „klingen" nach Framework.
> Wenn `lib/` sie nicht anfasst, wäre die Zuordnung ins Root eine Entscheidung über
> *künftige* Nutzung — die ist zulässig, muss aber als solche benannt werden, nicht als
> Ist-Beschreibung getarnt.

### Die Dev-Tools
`phpstan/phpstan` und `rector/rector` liegen heute im Root-**Produktions**baum,
`phpunit/phpunit` und `mockery/mockery` in `custom/`. Alle vier gehören in `require-dev` des
Roots.

Das ist die Zuordnung, die `.gitlab-ci.yml` betrifft: Der PHPUnit-Pfad steht dort als Variable
an einer Stelle, damit die Umstellung von `./custom/vendor/bin/phpunit` auf
`./vendor/bin/phpunit` **ein Einzeiler** bleibt (`008-005-0001`).

### Die Entfaller
Aus `006-001-0002` und `006-001-0003`: `ellumilel/php-excel-writer`, `twig/twig`, `scssphp`
(falls in `006-001-0001` noch nicht gefallen) und alles Weitere, dessen Konsument mit Epic
`012` verschwunden ist. Je mit dem Vermerk, **wer** es benutzt hat und wann das entfiel — sonst
fragt in einem Jahr jemand nach.

### Das Ergebnis
Die Tabelle aus `006-001-0002` wird um die Spalten **Kategorie** und **Begründung** ergänzt.
Sie bleibt an derselben Stelle unter `an_project/docs/` und ist ab hier das, worauf `006-002`
sein Manifest baut.

## Abgrenzung
Kein Manifest, kein Lock, kein `vendor/`-Umbau — das sind `006-002` und `006-003`. Hier
entsteht die Entscheidungsgrundlage, sonst nichts.

Die Autoloader-**Auflösung** (welcher Baum gewinnt, wie die Dubletten verschwinden) ist
`006-004`. Hier wird nur zugeordnet, wohin ein Paket künftig gehört.

## Acceptance criteria
- [x] Jedes Paket aus dem Inventar trägt eine der vier Kategorien und einen Satz Begründung.
- [x] Die vier Grenzfälle sind entschieden; bei jedem steht, **ob** der Framework-Code das
      Paket heute benutzt, und die Entscheidung folgt daraus — oder weicht begründet ab.
- [x] Wo eine Zuordnung auf künftiger statt heutiger Nutzung beruht, ist das ausdrücklich
      benannt und nicht als Ist-Beschreibung formuliert.
- [x] Die vier Dev-Tools stehen in `require-dev`.
- [x] Bei jedem Entfaller steht, wer ihn benutzt hat und wodurch er entfiel.
- [x] Die Begründungen taugen als Vorlage für Epic `007` — sie erklären das **Prinzip**
      (Framework-Sache gegen Projekt-Sache), nicht nur den Einzelfall.

## Verification
Kein Testlauf — eine Durchsicht.

- Für jeden der vier Grenzfälle wird der `grep` über `lib/` im Ergebnis gezeigt, nicht nur
  sein Resultat behauptet.
- Die Summe der vier Kategorien ergibt die Gesamtzahl aus `006-001-0002`. Ein Paket, das in
  keiner Kategorie landet, ist ein übersehenes Paket.
- Gegenprobe an einem Beispiel: Wer die Tabelle liest und ein neues Paket hinzufügen will,
  muss allein daraus entscheiden können, wohin es gehört. Gelingt das nicht, erklärt die
  Tabelle Einzelfälle statt eines Prinzips.

## Ergebnis
`tools/dependency-assignment.json` hält die Entscheidungen, `tools/dependency-inventory.php`
wertet sie aus. Getrennt gehalten, weil das eine Fakten erhebt und das andere Entscheidungen
festhält.

### Kontrollsumme — sie geht auf
| Kategorie | Pakete |
|---|---|
| `root` | 39 |
| `custom` | **0** |
| `require-dev` | 28 |
| `entfaellt` | 22 |
| **Summe** | **89** |

**`custom` = 0 ist kein Versehen, sondern das Ergebnis.** Es gibt kein einziges
projektspezifisches Paket mehr — genau der Zustand, den das Epic als Erfolgskriterium nennt:
„`custom/vendor` ist initial leer — ein Slot für projektbezogene Pakete."

### Die vier Grenzfälle — der Story-Text lag bei dreien daneben
Die Prüffrage war „benutzt der Framework-Code das Paket heute?". Die Antwort:

| Paket | Story-Vorschlag | Befund | Entscheidung |
|---|---|---|---|
| `firebase/php-jwt` | Root | **niemand benutzt es** | entfällt |
| `vlucas/phpdotenv` | Root | nur `custom/config.php` | **root** |
| `sentry/sentry` | Root | **niemand benutzt es** | entfällt |
| `onelogin/php-saml` | `custom/` | **niemand benutzt es** | entfällt |

Der `grep`, wie ihn die Verifikation verlangt:

```
Firebase   lib/: 0   custom/: 0
Dotenv     lib/: 0   custom/: 1   → custom/config.php
Sentry     lib/: 0   custom/: 0
OneLogin   lib/: 0   custom/: 0
Stripe     lib/: 0   custom/: 1   → custom/Entity/Core/Example.php (ein Kommentar)
```

**Sechs der neun `custom/`-Abhängigkeiten werden nirgends benutzt** — dasselbe Muster wie die
projektfremden Kommentare aus `000-000-0017`: Reste aus der Kundenanwendung, aus der die
Vorlage geschnitten wurde. Der Stripe-Treffer *ist* sogar wörtlich einer dieser Kommentare.

Entschieden am 2026-09-08: alle streichen. Ein Manifest beschreibt, was gebraucht wird, nicht
was gebraucht werden könnte. `firebase/php-jwt` nimmt Epic `013` wieder auf, wenn JWT
tatsächlich eingebaut wird.

`vlucas/phpdotenv` ist der einzige, der bleibt — mit einer **anderen Begründung** als der
Story-Text: nicht „Infrastruktur klingt nach Framework", sondern die ausgelieferte
`custom/config.php` benutzt es, und die gehört zum Framework. Der dortige
`class_exists`-Schutz war nur nötig, weil das Paket in `custom/` lag und fehlen konnte.

### Warum die Ableitung über den Graphen läuft
Der erste Anlauf benutzte eine Faustregel: „alles unter `custom/`, was nicht ausdrücklich
zugeordnet ist, entfällt". Das ergab **52 Entfaller** — und war falsch: PHPUnit bleibt als
Werkzeug, also bleiben auch seine achtundzwanzig transitiven Abhängigkeiten. Sie entfallen
nicht, sie wandern aus `custom/vendor` ins Root-`require-dev`.

Jetzt läuft die Ableitung den Abhängigkeitsgraphen aus der `installed.json`: Wer von einem
behaltenen Wurzelpaket erreichbar ist, wandert mit. Aus 52 Entfallern wurden 22 — und die
Zahl beschreibt jetzt, was tatsächlich verschwindet.

### Die Dubletten lösen sich dabei auf
| Paket | Root | `custom/` | Ergebnis |
|---|---|---|---|
| `phpmailer/phpmailer` | (von Hand) | v6.10.0 | beide → `root`, die Handkopie fällt |
| `psr/log` | 1.1.3 | 3.0.2 | Paket bleibt, die `custom/`-Fassung nicht |
| `symfony/polyfill-ctype` | v1.14.0 | v1.37.0 | beide → `root` |
| `symfony/polyfill-mbstring` | v1.14.0 | v1.38.2 | beide → `root` |

Das Dokument stellt ausdrücklich klar: **Die Kategorie gilt dem Paket, nicht der Version.**
Sonst liest man aus „`psr/log` Root=root, custom=entfaellt" heraus, die ältere Fassung solle
bleiben. Welche Version es wird, entscheidet Composer — bei `psr/log` weder 1.1.3 noch 3.0.2,
sondern 2.0.0 (`006-001-0003`).

### Die Gegenprobe aus der Verifikation
*„Wer die Tabelle liest und ein neues Paket hinzufügen will, muss allein daraus entscheiden
können, wohin es gehört."*

Probe: Ein Projekt will `league/csv` für einen eigenen Import. → Zeile 1 nein, Zeile 2 nein,
Zeile 3 **ja** → `custom`. Das Prinzip trägt, ohne den Einzelfall zu kennen.
