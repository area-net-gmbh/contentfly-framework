---
id: 006-001-0003
title: Belegen, dass der Ist-Stack auflösbar ist
status: todo
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
- Steht Composer auf der Maschine zur Verfügung, auf der das läuft? Wenn nicht, ist ein
  Container der Weg — dieselbe Überlegung wie bei der Pipeline aus `008-005-0001`, und der
  Vorteil ist derselbe: Der Lauf hängt dann nicht an der lokalen PHP-Version.

## Acceptance criteria
- [ ] Ein Auflösungslauf gegen `platform.php = 8.3` ist durchgeführt; sein vollständiges
      Ergebnis ist festgehalten — auflösbar oder nicht.
- [ ] Ist er auflösbar: Die aufgelösten Versionen der kritischen Pakete stehen im Ergebnis
      (`doctrine/orm`, `ramsey/uuid`, `silex/silex`, die Symfony-Komponenten).
- [ ] Ist er **nicht** auflösbar: Jeder Konflikt ist benannt, mit der Frage, ob er lösbar ist
      — und falls nicht, ist der Zuschnitt von Epic `006` als offene Entscheidung markiert.
- [ ] Für den Doctrine-Wechsel steht fest, ab welchem Release der Fix des Dev-Branch-Pins
      enthalten ist, oder dass sich das nicht klären lässt.
- [ ] Das Wegwerf-Verzeichnis ist entfernt; im Repo liegt nichts davon.

## Verification
Das Protokoll des Auflösungslaufs gehört wörtlich ins Ergebnis, nicht zusammengefasst — eine
Aussage wie „löst sauber auf" ist genau das, was in `006-002` niemandem hilft, wenn es dann
doch klemmt.

Der Lauf muss **wiederholbar** sein: Das benutzte `composer.json` gehört mit ins Ergebnis, samt
dem Befehl, der es aufgelöst hat.
