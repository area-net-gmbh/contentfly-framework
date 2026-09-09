---
id: 006-003-0003
title: Die Dokumentation auf den neuen Ablauf bringen
status: done
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
- [x] `runbook.md` beginnt mit der Installation der Abhängigkeiten.
- [x] `tests/README.md` nennt sie vor dem Datenbank-Schritt.
- [x] `technical.md` beschreibt den eingefrorenen Baum in der Vergangenheitsform, mit dem
      Hinweis, wodurch er abgelöst wurde — die Begründung von damals bleibt lesbar.
- [x] `deployment.md` beschreibt den Zustand als erreicht.
- [x] Kein Dokument behauptet mehr, der Vendor-Baum liege im Repo.
- [x] Die Dauer aus `006-003-0002` steht im Runbook — wer zum ersten Mal installiert, soll
      wissen, ob er eine Minute oder zehn wartet.

## Verification
Die beschriebenen Befehle werden **ausgeführt**, nicht nur gelesen — dieselbe Regel wie in
`008-005-0004`. Eine Anleitung, die niemand nachgespielt hat, ist keine.

Konkret: Aus dem frischen Klon von `006-003-0002` heraus Schritt für Schritt dem Runbook
folgen, bis die Suite läuft.

## Ergebnis
**Fünf Dokumente nachgezogen, und beim Nachspielen zwei Fehler im Runbook gefunden, die nichts
mit dem Vendor-Ausbau zu tun haben.** Genau dafür verlangt dieser Task, die Befehle
auszuführen statt sie zu lesen — beide hätten sich beim Lesen nicht gezeigt.

| Datei | Was geändert wurde |
|---|---|
| `an_project/docs/runbook.md` | Neuer **Schritt 1 „Abhängigkeiten installieren"**, alles danach um eins verschoben. Dazu die zwei gefundenen Fehler (unten). |
| `tests/README.md` | `composer install` als Schritt 0 vor der Datenbank, in beiden Ablauffolgen und im Pipeline-Nachspielen. |
| `an_project/docs/deployment.md` | Zustand als **erreicht** beschrieben, mit den gemessenen Zahlen aus `006-003-0002`. |
| `an_project/docs/technical.md` | *Warum `vendor/` in Git liegt* → *lag*, plus vier weitere Stellen (siehe unten). |
| `README.md` (Repo-Wurzel) | `composer install` als Schritt (2) der manuellen Installation, Schritte umnummeriert. |

### `technical.md` — nicht nur der eine Abschnitt
Der Task nannte den Abschnitt *Warum `vendor/` in Git liegt*. Beim Durchsehen standen **vier
weitere Stellen** in der Gegenwartsform, die dasselbe behaupteten:

| Stelle | war |
|---|---|
| `custom/` als Vorlage | „Dasselbe gilt für `custom/vendor` — der Inhalt stammt aus dem Kundenprojekt" |
| Auth-Abschnitt | „`firebase/php-jwt` liegt ausschliesslich in `custom/vendor`" |
| *Zwei Autoloader in einem Prozess* | beschrieb wirksame Paket-Doppelungen, die es nicht mehr gibt |
| *Was den Alt-Baum an PHP 8.5 hindert* | „Dev-Werkzeuge liegen **heute** im ausgelieferten Baum" |

Alle vier in die Vergangenheit gesetzt, mit dem Hinweis, wodurch sie abgelöst wurden. Der
Autoloader-Abschnitt ist dabei **nicht gelöscht**, sondern mit einem Vorspann versehen: Die
Doppelungen erklären, warum Pakete im Altbestand die Versionen tragen, die sie tragen — und
was mit dem zweiten Manifest geschieht, entscheidet `006-004`, nicht dieser Task.

Der Hauptabschnitt trägt jetzt eine Tabelle vorher/jetzt und den Satz, der überall fehlte:
**ein frischer Checkout ist ohne `composer install` nicht lauffähig**, und er meldet sich mit
fehlenden Klassen statt mit seinem Grund. Derselbe Hinweis steht im Runbook, in
`tests/README.md`, in `deployment.md` und in der Repo-`README.md` — an jeder Stelle, die einen
Ablauf beschreibt, wie der Task es verlangt.

## Die Verification — und was sie zutage gefördert hat

Ein frischer Klon, und dann das **neue** Runbook Schritt für Schritt, wörtlich:

| Schritt | Ergebnis |
|---|---|
| 1 · `composer install` | 77 Pakete, 2 s (warmer Cache) |
| 2 · `docker compose up -d` | healthy nach ~15 s |
| 3 · `appcms:install --dry-run` | **fehlgeschlagen** — siehe unten |
| 3 · `appcms:install` (korrigiert) | Schema und Basisdaten angelegt |
| 3 · `git checkout HEAD -- custom/config.php` | Vorlage sauber |
| 4a · Console + HTTP-Smoke | Exit 0 · `"success":true` |
| 4b · `phpunit --testsuite unit` | **OK (39 tests, 55 assertions)** |
| 4b · volle Suite (`CI=true`) | 247 Tests / 595 Assertions, **7 bekannte Failures, 0 übersprungen**, Postausgang 0 Byte |

### Fehler 1: Das Runbook konnte nicht funktionieren — `--db-port` fehlte
Schritt 3 wörtlich ausgeführt:

```
Die Installation kann nicht starten:
  - database: SQLSTATE[HY000] [2002] Connection refused
```

`appcms:install` hat den Default `3306` (`InstallCommand.php:37`), `docker-compose.yml`
veröffentlicht aber `3307` — und der dokumentierte Befehl nannte den Schalter überhaupt nicht.
Die Anleitung war also **nie durchführbar**, seit es beide Dateien gibt. `tests/README.md`
hatte `--db-port=3307` von Anfang an; nur das Runbook nicht.

Behoben, mit der Begründung daneben, damit es niemand wieder wegkürzt. Dass die Meldung wie
ein nicht laufender Container aussieht und keiner ist, steht dabei — das ist der Teil, der
Zeit kostet. Gut gelöst ist der `--dry-run`: Er fing es ab, bevor irgendetwas geschrieben wurde.

### Fehler 2: Der Smoke-Test verspricht 405 und liefert 500
Das Runbook stand: „Ein `HTTP 405` auf `/` oder einem unbekannten Pfad ist **kein** Fehler."
Gemessen kommt **500** — und mit `tests/router.php` und `APP_DEBUG=0` sogar ein **302 auf `/`**,
also eine Umleitung auf sich selbst.

Die Erklärung des Runbooks stimmt trotzdem: Im Rumpf steht „Method Not Allowed", der
OPTIONS-Catch-All greift wie beschrieben. Falsch ist nur der Statuscode — das ist `000-000-0006`,
unverändert und hier bloss wiedergesehen. Der Text sagt das jetzt so und nennt das Ticket,
damit niemand den Smoke-Test für gescheitert hält.

**Beides sind keine Folgen des Vendor-Ausbaus.** Sie standen vorher schon dort und sind nur
aufgefallen, weil dieser Task die Anleitung zum ersten Mal von Null durchgespielt hat. Kein
eigenes Ticket: Fehler 1 ist innerhalb dieses Dokuments behoben, Fehler 2 hat mit
`000-000-0006` längst eines.

### Ein Fehlgriff im Messen selbst
Der erste Suite-Lauf des Nachspielens meldete **191 Failures**. Bevor ich das irgendwo
festhielt, habe ich nachgesehen — und die Ursache lag bei mir: Ein Testserver aus dem Lauf zu
`006-003-0002` hielt Port 8145 noch besetzt (`Failed to listen on 127.0.0.1:8145`), der neue
startete nie, und die Suite lief gegen einen Server, dessen Arbeitsverzeichnis ich zwischendurch
gelöscht hatte. Nach dem Aufräumen: wieder 247 Tests und dieselben 7 Failures. Eine Zahl, die
sich um zwei Grössenordnungen ändert, ohne dass sich der Code geändert hat, ist zuerst ein
Verdacht gegen den Messaufbau.

### Zwei Abweichungen vom dokumentierten Ablauf
- **Der Container-Name.** `docker-compose.yml` vergibt ein festes `container_name:
  contentfly-db`, und ein solcher Container existiert auf der Maschine bereits — der des
  Hauptarbeitsverzeichnisses. Der Klon lief deshalb über eine `docker-compose.override.yml` mit
  eigenem Namen und Port 3317, in eigenem Volume. Der bestehende Container wurde nicht
  angefasst und steht nach dem Lauf unverändert da; Klon, Volume und Netz sind entfernt.
- **`an_project/docs/abhaengigkeiten-inventar.md` blieb unberührt.** Es inventarisiert die
  beiden alten Bäume, trägt aber im Kopf sein Erzeugungsdatum und den Vermerk „ERZEUGT von
  `tools/dependency-inventory.php` — nicht von Hand pflegen". Ein datierter Schnappschuss
  behauptet nichts über den Jetzt-Zustand; von Hand hineinzuschreiben hiesse, gegen die eigene
  Regel der Datei zu verstossen. Wer den Stand neu braucht, erzeugt ihn neu.
