---
id: 007-005-0000
title: Am echten Bestandsprojekt durchspielen
status: in-progress
depends_on: [007-004-0000]
---

# Am echten Bestandsprojekt durchspielen

## Goal
Der Leitfaden ist mindestens einmal an einem **realen** Bestandsprojekt gefahren worden, nicht
nur an der `custom/`-Vorlage. Was dabei hakt, fliesst zurück in den Leitfaden und in die
Werkzeuge.

Gleichzeitig fällt hier die Antwort auf die sieben Codepfade ohne Auslöser (siehe Epic): Werden
sie von Bestandsprojekten tatsächlich benutzt — dann bleiben sie und der Leitfaden benennt sie —
oder sind es Überbleibsel der Oberfläche, die mit dem Umstieg fallen dürfen?

## Der Block ist gelöst (2026-09-15)

**Bis hierher stand die Story auf `blocked`:** Ein echtes Bestandsprojekt lag ausserhalb dieses
Repos, und ohne Zugang liess sich weder der Leitfaden abnehmen noch die Frage nach den sieben
Pfaden beantworten.

**Das Projekt ist jetzt benannt und zugänglich:** UFP, lokal unter
`/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Ionic/ufp.teamviewer.com`, Branch `development`.

| | Stand |
|---|---|
| Framework | Contentfly **1.6.0** als Kopie unter `backend/lib/contentfly/`, Silex 2.2.2, Doctrine-Fork auf einem Dev-Branch |
| PHP | **7.4** (`docker/php`, `php:7.4-apache`) |
| Datenbank | MySQL 8.2, Daten per Bind-Mount in `docker/mysql8/`, keine personenbezogenen Daten (Auskunft Auftraggeber) |
| Umfang | 10 Entities unter `custom/Entity`, eigene Controller in zehn Bereichen |
| Authentifizierung | **sechs eigene LoginManager**, darunter OAuth 2.0 (SSO) mit anschliessender Profilabfrage an eine Community-API |
| Frontend | Ionic/Angular-SPA, schickt `APPCMS-Token` |
| Oberfläche | wird **nicht** genutzt (Auskunft Auftraggeber) — die Entscheidung aus `migration.md` ist damit getroffen |

**Warum sich gerade dieses Projekt eignet:** Die Authentifizierung ist der Teil, den Epic `013`
am stärksten umgebaut hat — der `LoginManager` ist durch `LoginProvider` ersetzt. Ein Projekt mit
sechs davon und einem echten OAuth-Fluss prüft genau diesen Vertrag.

**Am Rand gesehen, von Task `0001` zu klären:** `backend/lib/contentfly/` weicht in 76 Dateien vom
Import-Stand dieses Repos (`b9284090`) ab, und die Historie des Projekts enthält eigene
Security-Änderungen an Framework-Routen. Ob das Projekt-Patches am Framework sind oder nur ein
anderer Versionsstand, entscheidet, wie viel beim Wechsel auf das Paket verloren gehen würde.

## Abgrenzung

Die Migration dieses Projekts ist damit **nicht** erledigt — sie findet in dessen eigenem Repo
statt. Hier wird der Weg geprüft, nicht das Projekt umgestellt.

**Arbeitsweise, festgelegt beim Schnitt:**

- **Nur an einer Kopie.** Der Code wird auf einem eigenen Branch im Projekt-Repo umgestellt, die
  Datenbank in eine eigene Instanz kopiert. Der Bind-Mount `docker/mysql8/` wird nie beschrieben.
- **Das Framework kommt aus diesem Repo**, über einen Composer-`path`-Eintrag. Ein Fehler, den
  die Migration im Framework findet, wird hier als eigener Task behoben und wirkt sofort.
- **Erst messen, dann umbauen.** Das Verhalten des alten Projekts wird aufgezeichnet, bevor
  etwas geändert wird; die Abnahme vergleicht dagegen.
- **Jede Hürde wird einsortiert:** Leitfaden, Werkzeug, Framework oder projektspezifisch. Die
  ersten drei werden Tasks in diesem Repo, das vierte bleibt im Projekt.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [ ] 007-005-0001 — Kopie bereitstellen und Bestandsaufnahme
- [ ] 007-005-0002 — Das Ist-Verhalten des alten Projekts aufzeichnen
- [ ] 007-005-0003 — Phasen 1 bis 5 am Projekt durchspielen
- [ ] 007-005-0004 — Phasen 6 bis 9 am Projekt durchspielen
- [ ] 007-005-0005 — Rückfluss in Leitfaden, Werkzeuge und Framework

`0001` misst, bevor etwas geändert wird, und entscheidet, ob `0003`/`0004` das ganze Projekt
oder einen Ausschnitt nehmen. `0002` hält fest, was die App heute bekommt, und ist der Massstab
für `0004`. `0003` und `0004` sind die beiden Hälften des Leitfadens, getrennt am ersten Start.
`0005` sorgt dafür, dass das nächste Projekt nicht dasselbe lernen muss.

## Framework-Befunde aus dieser Story

Jeder Befund, der nicht das Projekt, sondern das Framework betrifft, ist ein eigener Task
(Stand 2026-09-15):

| Task | Befund | Status |
|---|---|---|
| `000-000-0038` | Upload führt PHP aus | done |
| `000-000-0039` | CORS spiegelt jede Herkunft | done |
| `000-000-0040` | eigene Config-Schlüssel zerstören die Antwort | done |
| `000-000-0041` | Dateiablage aus 1.x nach der Migration unerreichbar | **offen** |
| `000-000-0042` | kein Upload-Grössenlimit | **offen** |
| `000-000-0043` | Tabellenname `pim_navItem` | **offen** |
| `000-000-0044` | Roh-SQL liefert Zahlen statt Strings | done |
| `000-000-0045` | kein Haken nach dem Login | done |
| `000-000-0046` | Spalte ohne Typ zerstört die Debug-Antwort | done |
| `000-000-0047` | Proxies bei jedem Request | done |
| `000-000-0048` | deutscher Variablenname in `bin/console.php` | **offen** |

Die offenen hängen nicht an dieser Story: Der Leitfaden nennt `0041` und `0043` in Phase 4 als Stellen,
an denen ein Projekt heute anhalten muss.
