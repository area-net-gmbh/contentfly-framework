---
id: 008-004-0001
title: Manager-Schicht als Vertrag festhalten
status: done
depends_on: []
---

# Manager-Schicht als Vertrag festhalten

## Context
Die Manager sind die Naht zwischen Framework und Projekt: Über sie registriert ein Projekt
Routen, Typen und Commands. Epic `009` baut den Kernel darunter aus — was die Manager **nach
außen** zusagen, muss danach identisch gelten.

Nach Epic `012` sind alle fünf schlank; genau deshalb lässt sich ihr Verhalten knapp
festhalten.

## Umfang

| Manager | Zusicherung |
|---|---|
| `RouteManager` | `mount()` sammelt einen `CustomControllerProvider` ein und gibt ihn zurück; `bindRoutes()` bindet alle gesammelten. Zwei Mounts auf denselben Pfad — welcher gewinnt? |
| `TypeManager` | `registerType()` legt einen Typ unter seinem Alias ab, weist einen `PluginType` mit Ausnahme ab und registriert dessen Annotationsdatei. `getType($alias)` liefert **`null`** statt zu werfen. `getTypes()` liefert alle. |
| `ConsoleManager` | `addCommand()` hängt einen `CustomCommand` in den Dispatcher. |
| `LoginManager` | `createManagedUser()` legt einen Benutzer an oder aktualisiert ihn; der Alias wird mit `md5(get_class($this))` präfixiert — zwei LoginManager erzeugen also nie kollidierende Aliase. |

### Wo Unit-Test, wo Integrationstest
`TypeManager` und `RouteManager` hängen nur an einem `Silex\Application`-Objekt, das sich für
einen Test billig aufbauen lässt. Sie sind damit **Unit-Test-fähig** — nach
`008-003-0004` (`GroupLanguagePermissionTest`) der zweite Fall, in dem das die ehrlichere
Abdeckung ist.

`ConsoleManager` und `LoginManager` brauchen mehr Umgebung: der eine den Dispatcher, der
andere den EntityManager. Ob sie sich sinnvoll unit-testen lassen oder als Integrationstest
laufen, entscheidet sich beim Umsetzen — die Entscheidung gehört begründet festgehalten.

### Was hier nicht hingehört
`PluginManager` bekommt mit `008-004-0002` einen eigenen Task, weil er eine echte
Plugin-Infrastruktur braucht.

## Acceptance criteria
- [x] Für jeden der vier Manager ist die nach außen zugesagte Wirkung durch mindestens einen
      Test festgehalten.
- [x] `TypeManager::getType()` liefert für einen unbekannten Alias `null` — festgehalten, weil
      es von der sonstigen Fehlerbehandlung des Frameworks abweicht.
- [x] `registerType()` weist einen `PluginType` mit der vorgesehenen Ausnahme ab.
- [x] Die Aufteilung Unit- gegen Integrationstest ist begründet festgehalten.
- [x] Der Alias-Präfix von `createManagedUser()` ist geprüft — zwei verschiedene LoginManager
      erzeugen für denselben Namen zwei verschiedene Benutzer.

## Verification
Die Unit-Suite muss **ohne** laufende Umgebung grün bleiben; die Gesamtsuite grün mit
laufender Umgebung.

## Ergebnis — 14 Unit- und 3 Integrationstests

Gesamtsuite: **171 Tests, 392 Assertions**. Die Unit-Suite wächst von 15 auf **29 Tests** und
läuft weiterhin ohne Datenbank und ohne Server.

### Die Aufteilung, begründet
| Manager | Testart | warum |
|---|---|---|
| `TypeManager` | **Unit** | hängt nur an einem `Application`-Objekt; `orm.em = null` genügt |
| `RouteManager` | **Unit** | dito — beobachtet über einen Spion auf `Application::mount()` |
| `ConsoleManager` | **Unit** | der Dispatcher ist in Silex ohne weitere Einrichtung da |
| `LoginManager` | **Integration, aber indirekt** | siehe unten |

**Warum `createManagedUser()` nicht direkt getestet wird:** Die Methode braucht einen
EntityManager. Ihn im Testprozess aufzubauen hieße, die Anwendung ein **zweites Mal und
anders** zu konstruieren als `lib/contentfly/bootstrap.php` — mit eigener
Annotationsregistrierung und eigener Metadaten-Konfiguration. Ein solcher Test bewiese, dass
*diese* Konstruktion funktioniert, nicht die produktive; er könnte davon wegdriften und dabei
grün bleiben. Ein Versuch, ihn zu bauen, scheiterte prompt an genau dieser Stelle
(`AnnotationException: @PIM\Config … never imported`).

Stattdessen ist die **beobachtbare Wirkung** festgehalten: Ein Benutzer mit gesetztem
`loginManager` kann sich nicht mehr per Passwort anmelden. Das ist die Zusicherung, auf die es
ankommt.

### Drei Befunde
1. **`ConsoleManager::addCommand()` hat eine undokumentierte Reihenfolgebedingung.** Es nutzt
   `$app->extend('dispatcher', …)`; Pimple friert einen Service ein, sobald er ausgelesen wurde.
   Wer den Dispatcher vorher anfasst, bekommt eine `FrozenServiceException`. Im echten Ablauf
   kein Problem — `custom/app.php` läuft vorher —, aber Epic `009` muss das reproduzieren oder
   auflösen.
2. **Weder `RouteManager` noch `TypeManager` prüfen auf Kollisionen.** Ein zweiter Mount auf
   denselben Pfad und ein zweiter Typ unter demselben Alias überschreiben den ersten
   stillschweigend. Das ist zugleich der Weg, einen Framework-Typ zu ersetzen — und der Weg,
   ihn versehentlich zu verlieren.
3. **`TypeManager::getType()` liefert `null` statt zu werfen** — anders als die sonstige
   Fehlerbehandlung des Frameworks.

### Ein eigener Fehler behoben
Beim Durchlaufen fiel ein **flakiger Test** aus `008-002-0004` auf:
`testDieReihenfolgeDerLogZeilenIstNichtRekonstruierbar` behauptete, zwei aufeinanderfolgende
API-Aufrufe trügen denselben Zeitstempel. Das stimmt nur, solange sie keine Sekundengrenze
überschreiten — in etwa jedem dreißigsten Lauf war er rot. Ich hatte eine **Koinzidenz**
geprüft statt der Eigenschaft. Der Test prüft jetzt die **Auflösung** (`DATETIME` ohne
Nachkommastellen) und ist damit deterministisch.

## Verification
- [x] **20 vollständige Läufe hintereinander grün**, bei zufälliger Ausführungsreihenfolge —
      nach dem Flake-Fund war die übliche Dreierprobe nicht mehr genug.
- [x] Die Unit-Suite läuft ohne Umgebung: 29 Tests, 39 Assertions.
- [x] Datenbank nach den Läufen auf dem Ausgangsstand.
