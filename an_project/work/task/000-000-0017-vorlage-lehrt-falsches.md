---
id: 000-000-0017
title: Die Vorlage custom/ lehrt an drei Stellen Falsches
status: review
depends_on: [008-004-0005]
---

# Die Vorlage custom/ lehrt an drei Stellen Falsches

## Context
`008-004-0005` hat die Vorlage `custom/` als mitlaufendes Projekt abgesichert und dabei drei
Stellen gefunden, an denen sie das Falsche vorführt. Das wiegt schwerer als ein gewöhnlicher
Fehler: **An dieser Vorlage orientiert sich jedes neue und jedes migrierende Projekt**
(Epic `007`). Was sie zeigt, wird kopiert.

Keiner der drei Punkte ist ein Fehler *im Framework* — die Vorlage benutzt es nur so, wie es
niemand benutzen sollte.

## Umfang

### 1 — `jsonExample` existiert für die API nicht
`custom/Entity/Core/Example.php` führt ein Feld vor:

```php
/**
 * @ORM\Column(type="json", nullable=true)
 */
protected $jsonExample;
```

Der `TypeManager` kennt keinen `json`-Typ — `lib/contentfly/Classes/Types/` hat zwanzig
Typen, keiner deckt ihn ab. Die Folge:

- Das Feld **fällt still aus dem Schema**. Kein Eintrag, keine Warnung, kein Hinweis.
- Ein Schreibversuch scheitert mit `ContentflyException: contentfly_general_unknown_property`.
- Beim Lesen fehlt das Feld in der Antwort.

Die Spalte existiert in der Datenbank, das Feld existiert in der Entity — für die API
existiert es nicht. Wer sich an der Vorlage orientiert und ein `json`-Feld baut, läuft in
dieselbe Wand, ohne eine Fehlermeldung zu bekommen, die den Grund nennt.

Zwei Wege, und die Entscheidung gehört begründet:

- **Einen `JsonType` ergänzen.** Doctrine kann den Spaltentyp längst; es fehlt nur der
  Framework-Typ, der ihn auf das Schema abbildet. Das macht die Vorlage ehrlich und schliesst
  eine echte Lücke — `json` ist kein exotischer Spaltentyp.
- **Das Feld aus der Vorlage nehmen.** Billiger, aber die Lücke bleibt und der nächste
  stolpert darüber, nur später.

> **Das stille Verschlucken ist der eigentliche Punkt.** Ein Feld ohne passenden Typ sollte
> nicht wortlos verschwinden. Unabhängig vom gewählten Weg gehört geprüft, ob `getSchema()`
> hier nicht wenigstens warnen müsste — sonst bleibt jeder unbekannte Spaltentyp eine stumme
> Falle.

### 2 — `@PIM\Select` validiert nichts
Die Optionen aus

```php
@PIM\Select(options="provisioning,active,trial_expired,suspended,deactivated")
```

stehen im Schema, aber niemand vergleicht einen Schreibwert damit. `state: "gibtsnicht"` wird
angenommen und landet unverändert in der Spalte.

Dasselbe Muster wie bei `canExport` und `getExtended` (`000-000-0012`): veröffentlicht, aber
nirgends durchgesetzt — der einzige Konsument war die gelöschte Oberfläche. Hier ist es
allerdings **besonders leicht zu beheben**, weil die erlaubten Werte direkt danebenstehen und
`Api` beim Schreiben ohnehin durch die Feldtypen läuft.

Zu entscheiden ist, ob die Annotation künftig prüft oder ob sie ausdrücklich als
Client-Metadatum dokumentiert wird. Prüft sie, ist das eine **Verhaltensänderung für
Bestandsprojekte**: Ein Projekt, dessen Daten heute Werte ausserhalb der Liste enthalten,
bekommt plötzlich Fehler. Das gehört zu `000-000-0012` abgestimmt, nicht getrennt entschieden.

### 3 — Kommentare aus einem fremden Projekt
`Example.php` spricht von Mandanten-Lebenszyklen, einem `TrialExpiryChecker`, einer
„trial-end paywall spec" und davon, dass Stripe den Kunden als steuerbefreit meldet.
`ExampleController::bootstrapAction()` dokumentiert eine Auflösung über den
`X-Origin-Host`-Header, einen `originHost`-Parameter und einen Mandanten-Slug — der Rumpf der
Methode enthält nichts davon, er gibt eine leere Beispielantwort zurück.

Nichts davon existiert im Baum. Das sind Überbleibsel aus einer realen Anwendung, aus der die
Vorlage einmal herausgeschnitten wurde.

Kein Verhaltensfehler — aber eine Vorlage, die Dinge erklärt, die es nicht gibt, ist
schlimmer als eine, die nichts erklärt. Wer sie liest, sucht nach `TrialExpiryChecker`.

## Abgrenzung
Der Unterschied der Zeitstempel-Formate zwischen Vorlage (ISO 8601 mit Millisekunden) und
Framework (`Y-m-d H:i:s`) bleibt aussen vor — er gehört zu `000-000-0014`.

Ebenso der `before`-Hook der Vorlage, der einen Wert setzt, den niemand liest: Er ist als
Muster nicht falsch, nur schlecht gewählt. Ob die Vorlage dort ein sprechenderes Beispiel
zeigen sollte, ist eine Frage für Epic `007`, wenn die Vorlage ohnehin überarbeitet wird.

## Acceptance criteria
- [x] Für `jsonExample` ist entschieden und begründet: `JsonType` ergänzen oder Feld
      entfernen.
- [x] Geprüft und entschieden, ob ein Feld mit unbekanntem Spaltentyp weiterhin still aus
      dem Schema fallen darf.
- [x] Für `@PIM\Select` ist entschieden: prüfen oder ausdrücklich als Client-Metadatum
      dokumentieren — abgestimmt mit `000-000-0012`.
- [x] Die projektfremden Kommentare in `Example.php` und `ExampleController.php` sind ersetzt
      durch Text, der beschreibt, was dort tatsächlich steht.
- [x] Die Tests aus `008-004-0005`, die das heutige Verhalten festhalten, sind umgedreht.

## Verification
`custom/vendor/bin/phpunit` läuft vollständig grün; `example_entity` und die zugehörigen
`pim_log`-Zeilen sind nach dem Lauf auf dem Ausgangsstand. Wird ein `JsonType` ergänzt, kommt
ein Nachweis dazu, dass ein `json`-Feld über `/api/insert` und `/api/single` einen
verschachtelten Wert unverändert zurückliefert.

## Ergebnis
**Alle drei Punkte gelöst, und zwar in die Richtung, die die Lücke schliesst statt sie zu
verstecken** — auf deine Entscheidung: JSON-Feld behalten und `JsonType` ergänzen, `@PIM\Select`
prüfen lassen, Kommentare ersetzen.

### 1 — `JsonType`, und warum er so kurz ist
Der Typ hat keine `fromDatabase()` und keine `toDatabase()`. Das ist kein Auslassen: **Doctrine
macht die Umwandlung bereits.** Beim Lesen kommt aus der Spalte ein PHP-Array, beim Schreiben
kodiert Doctrine zurück. Getter und Setter der Basisklasse reichen — ein eigenes `json_encode()`
hätte doppelt kodiert.

Der Rundlauf ist mit einem **verschachtelten** Wert belegt, nicht mit einem flachen:

```json
{"titel":"Beispiel","merkmale":["a","b"],"tiefer":{"zahl":42,"flag":true,"leer":null}}
```

Er kommt vollständig zurück, mit `null` und `true` als solchen.

> **Eine Eigenschaft, die dabei auffiel und in den Test gehört:** MySQLs nativer JSON-Typ
> **normalisiert die Schlüsselreihenfolge**. Der Wert kommt vollständig zurück, aber `tiefer`
> steht danach vor `merkmale`. Mein erster Anlauf verglich mit `assertSame` und schlug fehl —
> er verglich die Speicherform von MySQL, nicht die Zusicherung der API. Jetzt `assertEquals`,
> mit dem Grund daneben.

### 2 — Das stille Verschlucken war der eigentliche Punkt
Der Task nannte es in einem eingerückten Block, und er hat recht: Ein `JsonType` schliesst
**eine** Lücke, das Verschlucken bleibt für jeden anderen unbekannten Spaltentyp.

`Api::getSchema()` gibt jetzt eine `E_USER_WARNING` aus, wenn eine Eigenschaft eine
`@ORM\Column`-Annotation trägt und **kein** Typ auf sie passt — mit Entity, Eigenschaft und
Spaltentyp im Text.

**Nicht geworfen, und das ist die Abwägung:** Ein Projekt mit einem exotischen Spaltentyp könnte
sonst nach einem Update sein Schema nicht mehr aufbauen. Eine Warnung landet im Log, und die
Suite setzt `failOnWarning` — dort fällt es sofort auf, ohne im Betrieb etwas umzuwerfen.

### 3 — `@PIM\Select` prüft
Die Prüfung sitzt in `SelectType::toDatabase()`, also dort, wo der Wert ohnehin durchläuft.
Zwei Entscheidungen darin:

- **`null` und `''` gehen durch.** Ob ein Feld leer sein darf, entscheidet `nullable` an der
  Spalte. Sonst wäre nebenbei jedes Select-Feld zum Pflichtfeld geworden — eine zweite,
  ungewollte Verhaltensänderung im selben Handgriff.
- **Verglichen wird gegen `options[].id`**, mit `strval` auf beiden Seiten. Ein Select über einer
  `integer`-Spalte funktioniert damit weiter; der Typ castet danach wie zuvor.

**Abstimmung mit `000-000-0012`, wie der Task sie verlangt:** Dort geht es um `canExport` und
`getExtended` — dasselbe Muster (veröffentlicht, nicht durchgesetzt), aber eine andere Frage.
Bei Berechtigungen ist die Durchsetzung eine Entscheidung über Zugriff und trifft jeden
Bestandsnutzer. Hier stehen die erlaubten Werte direkt neben dem Feld, und die Liste war
erklärtermassen für Clients gedacht. Die beiden Fälle laufen deshalb auseinander — der Vermerk
steht im umgedrehten Test.

### 4 — Die Kommentare
`Example.php` sprach von Mandanten-Lebenszyklen, einem `TrialExpiryChecker` und einer
Stripe-Steuerbefreiung; `ExampleController::bootstrapAction()` dokumentierte eine Auflösung über
`X-Origin-Host`, einen `originHost`-Parameter und einen Mandanten-Slug — der Rumpf gibt eine
leere Beispielantwort zurück. Nichts davon gab es je im Baum.

Ersetzt durch Text, der beschreibt, was dort steht. Die alten Begriffe kommen nur noch in der
Herkunftsnotiz vor, damit klar bleibt, was verschwunden ist.

> **Dabei zu weit gegriffen und zurückgenommen.** Ich hatte auch die Options-Werte umbenannt
> (`provisioning, active, …` → `entwurf, aktiv, archiviert`) und den Standardwert mit. Das war
> falsch: Die Werte sind Teil des Schemas, das die Charakterisierungstests aus `008-004-0005`
> zusichern, und der ORM-Default `"active"` wäre stehen geblieben — mit der neuen Prüfung ein
> Wert ausserhalb der eigenen Liste. Zurückgenommen; der Task verlangt Kommentare zu ersetzen,
> nicht Daten.

### Die Tests sind umgedreht, nicht gelöscht
| vorher | jetzt |
|---|---|
| `testDasJsonFeldDerVorlageHatKeinenTypUndFehltDamitImSchema` | `testDasJsonFeldDerVorlageStehtImSchema` |
| `testDasJsonFeldLaesstSichWederSchreibenNochLesen` | `testDasJsonFeldNimmtEinenVerschachteltenWertUndGibtIhnZurueck` |
| `testDieSelectAnnotationPruefteNichtsWasSieAuflistet` | `testDieSelectAnnotationWeistEinenUnbekanntenWertAb` |

Dazu **ein neuer**: `testEinErlaubterSelectWertGehtWeiterhinDurch()`. Ohne ihn wäre ein
Select-Feld, das gar nichts mehr annimmt, ebenso grün — die Gegenrichtung gehört dazu.

### Verification
| Prüfung | Ergebnis |
|---|---|
| volle Suite mit `CI=true` | **OK (237 tests, 584 assertions)**, 0 übersprungen |
| JSON-Rundlauf verschachtelt | belegt, inkl. `null` und `true` |
| Deprecation-Gate | grün, 1 Paar, 1 ausgenommen |
| Postausgang der Versandfalle | 0 Byte |

Zwei Breaking Changes vermerkt: die Select-Prüfung (mit der SQL-Abfrage, mit der ein Projekt
seine Daten vorab prüft) und die Warnung bei unbekanntem Spaltentyp.
