---
id: 008-005-0003
title: custom/config.php gegen versehentliches Committen absichern
status: review
depends_on: []
---

# custom/config.php gegen versehentliches Committen absichern

## Context
`custom/config.php` ist eine **Vorlage** und liegt als solche im Repo — mit
`$SET_DB_HOST`-Platzhaltern, die `appcms:install` durch die eingegebenen Werte ersetzt. Nach
jeder Installation trägt die Datei also echte Zugangsdaten, und `git status` meldet sie als
geändert.

`tests/README.md` warnt bereits davor. Eine Warnung ist aber nur so gut wie die
Aufmerksamkeit dessen, der sie liest — **in dieser Sitzung ist die Datei mehrfach im
Arbeitsbaum stehen geblieben** und musste vor jedem Commit von Hand zurückgesetzt werden. Ein
einziges `git add -A` genügt, damit Zugangsdaten in der Historie landen.

Ein zweiter Punkt: Wer das übersieht und committet, macht die Vorlage für den nächsten
Checkout **unbrauchbar** — `bootstrap.php` erkennt an einem gesetzten `DB_HOST`, dass das
System bereits installiert sei, und der Installer bricht ab.

## Umfang

### Zwei Schichten
**1. Ein Pipeline-Job.** Er prüft jeden Commit darauf, dass `custom/config.php` die
Platzhalter trägt, und macht den Lauf sonst rot. Das greift immer, unabhängig davon, was
jemand lokal eingerichtet hat — aber erst nach dem Push. Die Zugangsdaten sind dann schon in
der Historie, und der Job ist die Meldung, nicht die Rettung.

**2. Ein `pre-commit`-Hook zum Mitnehmen.** Er fängt früher, aber nur bei dem, der ihn
installiert hat. Git-Hooks lassen sich nicht committen — der Hook gehört deshalb als Vorlage
ins Repo (etwa unter `tools/hooks/`), zusammen mit einer Zeile im Runbook, wie man ihn
aktiviert (`git config core.hooksPath` oder ein Kopierschritt).

Beides zusammen: Der Hook verhindert den Normalfall, der Job fängt den Rest.

### Was genau geprüft wird
Nicht „die Datei ist unverändert" — das wäre zu streng, denn die Vorlage darf sich
weiterentwickeln. Geprüft gehört, dass die **Platzhalter stehen**: `$SET_DB_HOST`,
`$SET_DB_PORT`, `$SET_DB_NAME`, `$SET_DB_USER`, `$SET_DB_PASS`, `$SET_DB_GUID_STRATEGY`.

Zu entscheiden ist, ob die Prüfung auch auf andere Muster achtet — ein gesetztes
`APP_MASTER_PASSWORD` etwa, oder Zugangsdaten in anderen Dateien der Vorlage. Der Zuschnitt
soll knapp bleiben; eine allgemeine Geheimnis-Suche ist **nicht** Teil dieses Tasks.

### Die Meldung zählt
Ein Gate, das nur „Prüfung fehlgeschlagen" sagt, kostet den nächsten zehn Minuten. Die
Meldung soll benennen, **was** zu tun ist: `git checkout -- custom/config.php` stellt die
Vorlage wieder her, und danach muss neu installiert werden, wenn lokal weitergearbeitet wird.

## Abgrenzung
- Keine allgemeine Secret-Erkennung und kein Scannen der Historie. Wenn Zugangsdaten schon
  eingecheckt sind, ist das ein eigener Vorgang.
- Kein Umbau der Vorlage selbst — dass die Installation in eine versionierte Datei schreibt,
  ist ein Entwurfsproblem, das zu Epic `007` gehört (Migrationspfad und Auslieferung). Hier
  wird nur der Schaden abgefangen.
- Der `.gitignore` ist **kein** Weg: Die Datei ist eine Vorlage und muss versioniert bleiben.

## Offene Fragen
- ~~Ist `tools/hooks/` der richtige Ort?~~ Geklärt: `tools/ci/` war mit `008-005-0001`
  entstanden, `tools/hooks/` fügt sich ein. Das gemeinsame Prüfskript liegt eine Ebene höher
  unter `tools/`, weil es beide Aufrufer bedient.

## Acceptance criteria
- [x] Ein Pipeline-Job macht den Lauf rot, wenn `custom/config.php` keine Platzhalter mehr
      trägt.
- [x] Eine `pre-commit`-Hook-Vorlage liegt im Repo und ist mit einem dokumentierten Schritt
      aktivierbar.
- [x] Beide melden dasselbe und nennen den Weg zurück (`git checkout -- custom/config.php`).
- [x] `an_project/docs/runbook.md` beschreibt, wie der Hook aktiviert wird, und warum es ihn
      gibt.
- [x] Der Ablauf „installieren → testen → Vorlage wiederherstellen" ist in `tests/README.md`
      als fester Schritt beschrieben, nicht als Warnung am Rand.

## Verification
Beide Schichten scharf geprüft, nicht nur gelesen:

- Datei installiert lassen → Hook lehnt den Commit ab, mit der erwarteten Meldung.
- Dieselbe Datei an der Prüfung des Pipeline-Jobs vorbeiführen → Job rot.
- Vorlage wiederhergestellt → beide grün.

Der Hook ist dabei tatsächlich auszuführen, nicht nur sein Skript zu lesen.

## Ergebnis
`tools/check-template-config.sh` (die Prüfung), `tools/hooks/pre-commit` (die frühe Schicht)
und der Job `check:template-config` in der Stage `check` (die späte). **Ein Skript, zwei
Aufrufer** — damit melden Hook und Job zwangsläufig dasselbe, statt zweimal dieselbe Regel zu
formulieren und auseinanderzulaufen.

### Was geprüft wird
Die **Zuweisungen**, nicht blosse Vorkommen der Zeichenkette:

```
$configDefault->DB_HOST                 = '$SET_DB_HOST';
```

Der Unterschied kam beim Testen ans Licht: `$SET_DB_HOST` steht auch im Kommentar am
Dateikopf. Eine Prüfung, die nur irgendwo sucht, hinge daran, wie dieser Kommentar formuliert
ist — und meldete eine halb ersetzte Datei als in Ordnung.

Der Hook prüft die **gestagte** Fassung, nicht den Arbeitsbaum. Das ist der Unterschied
zwischen brauchbar und lästig: Wer lokal installiert hat und etwas ganz anderes committet,
wird nicht aufgehalten.

### Der Fund, der die Meldung gerettet hat
Die erste Fassung nannte als Weg zurück `git checkout -- custom/config.php`. Beim scharfen
Durchspielen des Hooks zeigte sich: **Das funktioniert genau dann nicht, wenn der Hook
anspringt.** Ist die Datei gestagt, holt `git checkout -- <pfad>` sie aus dem *Index* und
schreibt die installierte Fassung erneut in den Arbeitsbaum — es sieht aus wie eine
Wiederherstellung und ist keine.

Richtig ist `git checkout HEAD -- custom/config.php`. Nachgemessen: nach dem ersten Befehl
steht ein Platzhalter in der Datei, nach dem zweiten stehen beide, und `git status` ist
sauber. Die Meldung erklärt jetzt auch, **warum** das `HEAD` dort steht — sonst kürzt es der
Nächste weg.

### Der Nachweis — beide Schichten scharf, nicht gelesen
**Prüfskript:**

| Fall | Exit |
|---|---|
| unversehrte Vorlage | 0 |
| installierte Datei | 1, alle sechs Platzhalter genannt |
| nur die Zuweisungen ersetzt, Kommentar unberührt | 1 — der Grenzfall greift |

**Hook**, in einem eigenen Klon mit `git config core.hooksPath tools/hooks` aktiviert und
tatsächlich ausgeführt:

| Fall | Ergebnis |
|---|---|
| Commit ohne `config.php` | durchgegangen |
| `config.php` lokal installiert, aber **nicht gestagt** | durchgegangen |
| `config.php` installiert und **gestagt** | **abgelehnt**, `HEAD` unverändert |
| nach `git checkout HEAD -- custom/config.php` | durchgegangen |

**Job** im `alpine:3`-Image, wie in der YAML: Exit 0 gegen die Vorlage, Exit 1 gegen eine
installierte Datei.

Gesamtsuite unverändert grün: 237 Tests / 574 Assertions.

### Nebenbei im Runbook korrigiert
`an_project/docs/runbook.md` trug noch den Hinweis, die Installation sei „heute noch nicht
durchführbar" — beide genannten Gründe (`012-002`, `000-000-0002`) sind längst `done`. Der
Block stand unmittelbar über dem, was hier zu ergänzen war, und hätte ihm widersprochen.
Ersetzt statt stehen gelassen.
