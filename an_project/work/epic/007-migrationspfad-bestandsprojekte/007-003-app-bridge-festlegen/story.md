---
id: 007-003-0000
title: Die $app[...]-Bridge festlegen
status: review
depends_on: []
---

# Die `$app[...]`-Bridge festlegen

## Goal
Es steht fest — und ist durchgesetzt, nicht nur aufgeschrieben —, ob der `ArrayAccess`-Zugriff
`$app['orm.em']` dauerhaft zur öffentlichen Framework-API gehört oder eine befristete
Migrationshilfe mit Frist ist.

**Ohne diese Festlegung weiss kein Bestandsprojekt, ob es seine Controller anfassen muss.** Genau
deshalb ist es eine eigene Story und keine Fussnote im Leitfaden: Solange die Frage offen ist,
kann ein Projekt den Aufwand seiner Migration nicht abschätzen.

## Ausgangslage

Die Bridge stammt aus `009-002` und ist bewusst gebaut worden, um den Silex-Zugriff zu erhalten:
`Classes/Kernel/ApplicationInterface` erweitert `\ArrayAccess`, `Classes/Kernel/Container`
implementiert es. Bestandscode greift genau so auf Dienste zu.

## Die beiden Wege

1. **Dauerhaft.** Der Zugriff ist Teil der öffentlichen API und bleibt. Dann ist er zu
   dokumentieren wie jede andere Zusicherung, samt der Liste der Schlüssel, auf die man sich
   verlassen darf — heute ist das nirgends festgeschrieben.
2. **Befristet.** Der Zugriff ist eine Migrationshilfe mit Deprecation-Frist. Dann braucht es
   einen benannten Nachfolger, eine Frist, und einen Weg, auf dem ein Projekt merkt, dass es
   betroffen ist — eine Deprecation, die niemand sieht, ist keine.

**Beides ist vertretbar, aber nur eines ist entschieden.** Zu dieser Story gehört die
Entscheidung *und* ihre Durchsetzung, nicht die Aufzählung der Möglichkeiten.

## Abnahme

Die Festlegung steht in `an_project/docs/architecture.md` unter *Key decisions*, mit Begründung
und den verworfenen Alternativen. Fällt sie auf „befristet", macht ein Lauf sichtbar, welcher
Aufruf betroffen ist; fällt sie auf „dauerhaft", steht die Liste der zugesicherten Schlüssel im
`dev-guide.md` und ein Test hält sie fest.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [x] 007-003-0001 — Die Entscheidung festschreiben — der $app-Zugriff bleibt
- [x] 007-003-0002 — Die zugesicherten Schlüssel festschreiben — und ein Test hält sie
- [x] 007-003-0003 — Die Grenze der Zusicherung sagen

Die drei bauen aufeinander auf: `0001` entscheidet, `0002` sagt worauf man sich verlassen darf,
`0003` sagt worauf nicht — und erst damit ist die Liste brauchbar.

**Die Richtung steht seit dem 2026-09-11 fest: dauerhaft, mit fester Schlüsselliste.** Damit muss
ein Bestandsprojekt seine Controller nicht anfassen. Gegen die befristete Deprecation sprach,
dass das Framework die Bridge 134-mal selbst benutzt — sie müsste dort zuerst durchgezogen
werden, und das wäre ein eigener Umbau und kein Migrationsschritt.

## Ergebnis

**Die Frage ist beantwortet und durchgesetzt: der `$app[...]`-Zugriff bleibt, mit fester
Schlüsselliste.** Ein Bestandsprojekt muss seine Controller nicht anfassen — der grösste
Einzelposten, den Epic `007` ihm ersparen kann.

| Wo | Was |
|---|---|
| `architecture.md`, *Key decisions* | die Entscheidung mit beiden verworfenen Alternativen |
| `dev-guide.md` | die Liste in drei Stufen, die vier Grenzen, und was keine Liste haben kann |
| `tests/Integration/ContainerSchluesselTest.php` | sieben Tests, die beides halten |
| `breaking-changes.md`, `ApplicationInterface` | zeigen auf die Festlegung, statt sie zu wiederholen |

**Gegen die Deprecation sprach eine Zahl und ein fehlender Ersatz.** 134-mal benutzt das
Framework die Bridge selbst; eine Deprecation für Projekte müsste dort zuerst durchgezogen
werden, sonst wäre das Gate aus `006-005` ab dem ersten Tag rot. Und ein Nachfolger ist nicht
benannt — eine Deprecation ohne Ersatz verschiebt Arbeit, statt sie zu ersparen.

## Die Liste hat drei Stufen, und das ist der Ertrag

Eine flache Liste wäre an drei Stellen falsch gewesen, und jede davon ist eine Falle:

1. **`db` und `dbs` gibt es erst nach der Installation.** Auf einem frischen Checkout fehlen
   sie — Absicht, denn `appcms:install` muss laufen können, bevor es eine Datenbank gibt.
2. **`orm.em` ist immer da, aber `null`, solange nicht installiert ist.** „Fehlt" meldet den
   Namen des Schlüssels; `null` meldet *Call to a member function createQueryBuilder() on null*
   und handelt damit von der Methode statt von der fehlenden Installation.
3. **`auth.token` gibt es erst nach der Anmeldung — nicht `null`, sondern gar nicht.** Im
   Console-Lauf nie.

Dazu eine vierte Gruppe, die **gar keine Liste haben kann**: die `<präfix>.controller`-Einträge,
einer pro gemounteter Route, vom Framework wie vom Projekt.

## Der Wächter fragt zwei Quellen, weil eine nicht reicht

`Container::keys()` sagt, was beim Aufbau entstanden ist — die verlässliche Auskunft. Der
Quelltext sagt, was *später* dazukommt: `auth.token` wird erst gesetzt, wenn ein Request sich
ausgewiesen hat. Und der Test prüft beide Richtungen: Jeder zugesicherte Schlüssel ist da, **und**
jeder registrierte ist eingeordnet — ohne die zweite wüchse die Liste auseinander.

## Sechs eigene Fehlgriffe, und vier hatten dieselbe Wurzel

- **Mein erster Wächter fragte nur nach bekannten Schlüsseln** — ein neuer hätte nie auffallen
  können.
- **Mein erster Mutationstest lief ohne Testserver.** Die Tests übersprangen sich sauber, und
  die leere Ausgabe sah aus wie Zustimmung.
- **Ich parste den Quelltext, obwohl der Container sich selbst aufzählen kann.** `keys()` gibt es
  seit `008-004`, und ein eigener Test prüft die Methode.
- **Der Test hing daran, dass zufällig eine installierte Konfiguration danebenlag.**
- **`istInstalliert()` fragte nach `orm.em`** — der wegen des `else`-Zweigs immer da ist.
- **Und dann verglich es gegen `'1'`,** wo `var_export(true, true)` ein `'true'` liefert; der
  Test war im installierten Fall rot und im uninstallierten grün, genau verkehrt herum.

Die letzten drei hingen an derselben falschen Annahme über `orm.em` — und die aufzudecken ist
das, was den Task inhaltlich weitergebracht hat.

**Zahlen:** Die Suite wächst von 514 auf **521** Tests. PHPStan `[OK] No errors`, 0 Deprecations
bei 0 Ausnahmen, 0 Byte Postausgang. Der Container-Test ist in **beiden** Zuständen gefahren:
installiert alle sieben grün, uninstalliert grün mit zwei bewussten Übersprüngen.
