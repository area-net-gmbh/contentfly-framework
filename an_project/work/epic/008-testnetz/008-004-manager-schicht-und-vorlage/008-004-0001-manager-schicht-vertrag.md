---
id: 008-004-0001
title: Manager-Schicht als Vertrag festhalten
status: todo
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
- [ ] Für jeden der vier Manager ist die nach außen zugesagte Wirkung durch mindestens einen
      Test festgehalten.
- [ ] `TypeManager::getType()` liefert für einen unbekannten Alias `null` — festgehalten, weil
      es von der sonstigen Fehlerbehandlung des Frameworks abweicht.
- [ ] `registerType()` weist einen `PluginType` mit der vorgesehenen Ausnahme ab.
- [ ] Die Aufteilung Unit- gegen Integrationstest ist begründet festgehalten.
- [ ] Der Alias-Präfix von `createManagedUser()` ist geprüft — zwei verschiedene LoginManager
      erzeugen für denselben Namen zwei verschiedene Benutzer.

## Verification
Die Unit-Suite muss **ohne** laufende Umgebung grün bleiben; die Gesamtsuite grün mit
laufender Umgebung.
