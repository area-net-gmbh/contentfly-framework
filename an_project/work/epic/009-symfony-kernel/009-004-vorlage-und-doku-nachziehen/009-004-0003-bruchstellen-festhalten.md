---
id: 009-004-0003
title: Die Bruchstellen des Kernel-Wechsels festhalten
status: review
depends_on: []
---

# Die Bruchstellen des Kernel-Wechsels festhalten

## Context
`an_project/docs/breaking-changes.md` ist die Grundlage für den Migrationsleitfaden aus Epic
`007`. Der Kernel-Wechsel hat mehrere Stellen erzeugt, an denen ein Bestandsprojekt bricht —
und keine davon steht bisher dort.

Bekannt aus den Stories:

- **`getSilexApplication()` gibt es nicht mehr.** Sie war an `Knp\Command\Command` geerbt und
  mit dem Paket weg. Ein Projekt-Command, der sie ruft, bekommt einen Fehler. Ersatz:
  `anwendung()`.
- **`$app['request']` ist entfallen.** Der Schlüssel war eine Falle — der Container merkt sich
  Factory-Ergebnisse, hätte also ab dem ersten Zugriff denselben Request geliefert.
- **`$app['controllers_factory']` ist entfallen.** Ein Projekt, das einen eigenen
  Controller-Provider schreibt, benutzt jetzt `Kernel\Routing\Routensammlung`.
- **`connect()` hat einen Rückgabetyp.** Ein eigener Provider muss eine `RouteCollection`
  liefern.
- **Der Container ist nicht mehr Pimple.** `share()`, `protect()`, `raw()`, `factory()` und
  `register()` gibt es nicht; `extend()` wirft eine `\RuntimeException` statt Pimples
  `FrozenServiceException`.
- **`Classes\Event` erbt von den Contracts.** Ein Projekt, das davon ableitet und die alte
  Basisklasse type-hinted, bricht.
- **`dispatch()` hat die umgekehrte Argumentreihenfolge.** Ein Projekt, das eigene Ereignisse
  verteilt, muss sie drehen.
- **Eine `unique`-Verletzung antwortet mit 409 statt 500** (`009-003-0002`).

## Acceptance criteria
- [x] Jede Bruchstelle steht in `breaking-changes.md`, im dortigen Format: was war, was ist,
      wer betroffen ist, was zu tun ist.
- [x] Die Liste ist **vollständig** — gegen die Ergebnisse der vier Stories geprüft, nicht aus
      dem Gedächtnis geschrieben.
- [x] Was sich für ein Projekt **nicht** ändert, steht ebenfalls da: `$app['schlüssel']`,
      `before()`/`after()`/`error()`, der `RouteManager`, `CustomCommand`. Eine Liste nur der
      Brüche liest sich, als bräche alles.
- [x] Epic `007` wird nicht vorweggenommen: Hier steht, **was** bricht, nicht die Rector-Regel,
      die es repariert.

## Verification
Die Einträge gegen die Ergebnisteile von `009-001` bis `009-003` gelesen — jede dort genannte
Änderung an einer öffentlichen Oberfläche taucht auf oder ist ausdrücklich als nicht relevant
eingestuft.

## Ergebnis

**21 Einträge in `breaking-changes.md`, in zwei neuen Abschnitten *Kernel (Epic 009)* und
*Doctrine (Story 009-005)* plus einem im bestehenden API-Abschnitt.** Der Task nannte acht
Bruchstellen; gegen die Ergebnisteile von `009-001` bis `009-005` gelesen kamen **dreizehn
weitere** dazu.

### Was der Task nicht auf der Liste hatte

| Bruchstelle | Quelle | warum sie fehlte |
|---|---|---|
| `$app` ist kein Action-Argument mehr | `009-001-0002` | Der Task las die Story-Ergebnisse von `009-002` an, nicht die von `009-001`. |
| Ein Projekt-Command muss die Signaturen von Console 7 treffen | `009-002-0005` | Der Task nannte nur `getSilexApplication()`. **Die härtere Hälfte:** PHP lehnt eine aufweichende Signatur *beim Laden* ab — die Konsole startet dann gar nicht. |
| `mount()` ruft `connect()` nicht mehr selbst | `009-001-0003` | Der Task nannte nur den Rückgabetyp. |
| `$app->redirect()` / `->stream()` | `009-001-0004` | Beide sind planmässig aus der Schnittstelle verschwunden, als ihre letzten Aufrufer fielen. |
| `Application::add()` → `addCommand()` | `009-003-0002` | Steckte im PHPStan-Task, nicht in einer Kernel-Story. |
| `symfony/validator` und `-translation` sind aus dem Baum | `009-002-0001` | Sie waren registriert und unbenutzt — für *dieses* Repo folgenlos, für ein Projekt nicht. |
| Andere Ausnahmetypen im Container | `009-002-0002` | `InvalidArgumentException` statt Pimples Meldung, `RuntimeException` statt `FrozenServiceException`. |
| DBAL 2 → 3 | `009-005-0001` | Story `009-005` entstand erst während des Epics; der Task-Text ist älter. |
| Neue Ids sind UUID v4 statt v1 | `009-005-0002` | dito — und die folgenreichste Zeile der ganzen Liste. |
| Konsole: Provider statt `HelperSet`, `ImportCommand` entfällt | `009-005-0003` | dito |
| Cache-Namensräume getrennt | `009-003-0002` | dito |
| `Entity\Serializable::getId()` abstrakt | `009-003-0002` | dito |
| `NoLogger` gelöscht | `009-003-0002` | dito |

Der Hook-Defekt aus `009-004-0004` steht ebenfalls drin, obwohl er behoben ist: Ein Projekt, das
eine Zwischenfassung zwischen `009-002` und `009-004` erwischt hat, sucht den Fehler sonst bei
sich.

### Die wichtigere Hälfte steht zuerst

Der Kernel-Abschnitt beginnt mit **Was sich für ein Projekt nicht ändert** — neun Zeilen:
`$app['schlüssel']`, `routeManager` samt `isSecure`, `before`/`after`/`error` samt Priorität,
`mount()`, `extend()`, die vier Datenbank- und Mail-Schlüssel, `CustomCommand` mit seinem
Präfix, die vier unveränderten Dateien und alle 27 Routen. Das war der Zweck der Schnittstelle
aus `009-001`, und eine Liste nur der Brüche liest sich, als bräche alles.

### Epic `007` ist nicht vorweggenommen

Jeder Eintrag sagt, **was** bricht und was ein Projekt stattdessen schreibt. Keiner nennt eine
Rector-Regel, ein Migrationsskript oder eine Reihenfolge — das ist die Arbeit von `007`, und
diese Datei ist dessen Quelle, nicht dessen Entwurf.

### Nachgeprüft statt abgeschrieben

Jede Aussage über die neue Oberfläche ist am Code belegt, nicht aus dem Changelog übernommen:
`Container::offsetGet()`/`extend()` für die Ausnahmetypen, `ControllerProviderInterface` für den
Rückgabetyp, `Kernel\Command::anwendung()`, `CustomCommand::setName()`, `bootstrap.php` für
`APPCMS_ID_STRATEGY` und `setNamespace()`, `Entity\Serializable` für `getId()`. **Eine
Formulierung ist dabei richtiggestellt worden:** `Serializable` ist eine abstrakte Klasse, keine
Schnittstelle, und `Entity\Base` erbt bereits davon — betroffen ist nur, wer an `Base` vorbei
ableitet.
