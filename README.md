# Contentfly

**Datenhaltung und Kernfunktionen als PHP-Bibliothek.** Contentfly nimmt Entities entgegen,
verwaltet sie über Doctrine, regelt Rechte, Anmeldung und Dateien — und stellt das über eine
JSON-API und eine Console bereit. Ein Projekt bringt seine eigenen Entities, Routen und Dienste mit
und bezieht Contentfly als Composer-Abhängigkeit.

## Was es nicht ist

**Contentfly 2 hat keine Oberfläche.** Die PIM-CMS-Oberfläche früherer Versionen ist mit Epic `012`
ersatzlos entfallen; mit ihr Twig und rund 31 MB Assets. Wer eine Oberfläche braucht, baut sie als
eigenes Frontend gegen die API.

Das unterscheidet Contentfly 2 von **Contentfly CMS 1.x**, das ein Produkt mit Oberfläche war und
unter `area-net-gmbh/contentfly-cms` liegt. Die beiden teilen den Namen und die Herkunft, sonst
wenig — der Weg von dort hierher steht in `an_project/docs/migration.md` und umfasst neun Phasen.

## Der Stand

| | |
|---|---|
| PHP | `^8.3` — Zielplattform 8.5 |
| Erweiterungen | `ext-openssl`, `ext-sodium` |
| Kernel | Symfony 7.4 LTS (HttpKernel, Routing, Console, EventDispatcher, Security) |
| Persistenz | Doctrine ORM 3 über DBAL 3.10, Entities mit PHP-Attributen |
| Antwortform | `data` / `errors` / `meta` — bei **jedem** Endpunkt, Erfolg wie Fehler |

Die Begründungen dahinter — warum 7.4 LTS und nicht 8.x, warum ORM 3, warum diese Antwortform —
stehen in `an_project/docs/architecture.md` unter *Key decisions*.

## Die drei Wege

**Ein neues Projekt aufsetzen** → `an_project/docs/runbook.md`, Abschnitt *So startet ein neues
Projekt*. Kurz: Contentfly kommt als Composer-Paket aus
`area-net-gmbh/contentfly-framework-dist`, die Startdateien kommen aus diesem Repository.

**Ein Bestandsprojekt von 1.x migrieren** → `an_project/docs/migration.md`. Neun Phasen, geordnet
nach dem, was ein Projekt tut; das Register der Einzelheiten ist
`an_project/docs/breaking-changes.md`. Der Leitfaden ist an einem echten Bestandsprojekt erprobt
worden, nicht am Reissbrett.

**Am Framework selbst arbeiten** → `an_project/docs/dev-guide.md` für die Struktur,
`an_project/docs/runbook.md` für die lokale Umgebung, `tests/README.md` für die Testsuiten.

## Was in diesem Repository liegt

**Zwei Dinge, und die Trennung ist Absicht** (Epic `007`):

- **`lib/contentfly/`** — das Paket `areanet/contentfly`. Was hier liegt, wird bei jedem Release
  als eigenständiges Repository ausgeliefert; ein Projekt fasst es nie an.
- **Alles andere** — das Skeleton `areanet/contentfly-skeleton`: `custom/`, `bin/`, `index.php`,
  `data/`, `plugins/`. Das ist der Startpunkt eines Projekts, und `custom/` zeigt mit
  Beispiel-Controller, -Entity, -Service, -Command und -Provider, wie man es macht.

Bis Contentfly 1.x lag das Framework als **Kopie** im Projekt. Eine neue Version bekam man nur,
indem man den Baum überschrieb, in dem auch der eigene Code stand — der Grund, aus dem Epic `007`
die Grenze gezogen hat.

Dazu, was nur der Entwicklung dient und **nicht** Teil eines Projekts ist: `tests/`, `tools/`,
`an_project/` (Backlog und Dokumentation) und die Gate-Konfigurationen.

## Eine Version herausgeben

Ein Tag `v*` auf `master` erzeugt das Paket-Repository neu. Der Ablauf, die Prüfungen davor und was
dabei schiefgehen kann: `an_project/docs/deployment.md`, Abschnitt *Eine Version herausgeben*.

## Prüfungen

Die Pipeline (`.github/workflows/pipeline.yml`) fährt bei jedem Push auf `master` und bei jedem
Pull Request fünf Prüfungen — Vorlagen-Konfiguration, `composer audit`, PHPStan, beide Testsuiten
auf PHP 8.3 und 8.4, und den Bezugsweg von aussen. **Alle blockierend.** Die Schritte stehen in
`tools/ci/*.sh` und lassen sich lokal nachspielen.

## Lizenz

**MIT**, siehe `LICENSE`. Das gilt für dieses Framework (Contentfly 2.x); das frühere
Contentfly CMS 1.x ist ein eigenes Produkt mit eigener Codebase und nicht betroffen.

---

<sub>**Zur Historie:** Bis Epic `009` lief unter Contentfly ein Silex-2-Kernel, bis Epic `010`
Doctrine ORM 2 mit Annotationen. Beides ist aus dem Baum. Wer auf ältere Anleitungen stösst, die
`appcms/`, ein Ant-Buildskript oder PHP 7 nennen, hat Contentfly 1.x vor sich.</sub>
