---
id: 006-001-0004
title: Zuordnung entscheiden und begründen
status: todo
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
- [ ] Jedes Paket aus dem Inventar trägt eine der vier Kategorien und einen Satz Begründung.
- [ ] Die vier Grenzfälle sind entschieden; bei jedem steht, **ob** der Framework-Code das
      Paket heute benutzt, und die Entscheidung folgt daraus — oder weicht begründet ab.
- [ ] Wo eine Zuordnung auf künftiger statt heutiger Nutzung beruht, ist das ausdrücklich
      benannt und nicht als Ist-Beschreibung formuliert.
- [ ] Die vier Dev-Tools stehen in `require-dev`.
- [ ] Bei jedem Entfaller steht, wer ihn benutzt hat und wodurch er entfiel.
- [ ] Die Begründungen taugen als Vorlage für Epic `007` — sie erklären das **Prinzip**
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
