---
id: 000-000-0017
title: Die Vorlage custom/ lehrt an drei Stellen Falsches
status: todo
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
- [ ] Für `jsonExample` ist entschieden und begründet: `JsonType` ergänzen oder Feld
      entfernen.
- [ ] Geprüft und entschieden, ob ein Feld mit unbekanntem Spaltentyp weiterhin still aus
      dem Schema fallen darf.
- [ ] Für `@PIM\Select` ist entschieden: prüfen oder ausdrücklich als Client-Metadatum
      dokumentieren — abgestimmt mit `000-000-0012`.
- [ ] Die projektfremden Kommentare in `Example.php` und `ExampleController.php` sind ersetzt
      durch Text, der beschreibt, was dort tatsächlich steht.
- [ ] Die Tests aus `008-004-0005`, die das heutige Verhalten festhalten, sind umgedreht.

## Verification
`custom/vendor/bin/phpunit` läuft vollständig grün; `example_entity` und die zugehörigen
`pim_log`-Zeilen sind nach dem Lauf auf dem Ausgangsstand. Wird ein `JsonType` ergänzt, kommt
ein Nachweis dazu, dass ein `json`-Feld über `/api/insert` und `/api/single` einen
verschachtelten Wert unverändert zurückliefert.
