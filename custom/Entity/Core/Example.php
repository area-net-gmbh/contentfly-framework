<?php
namespace Custom\Entity\Core;

use Areanet\PIM\Entity\Base;
use Areanet\PIM\Classes\Annotations as PIM;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="example_entity", indexes={
 *     @ORM\Index(name="idx_example_slug", columns={"slug"}),
 *     @ORM\Index(name="idx_example_state", columns={"state"}),
 * }, uniqueConstraints={
 *     @ORM\UniqueConstraint(name="uniq_example_slug", columns={"slug"})
 * })
 *
 * Die Beispiel-Entity der Vorlage. Sie fuehrt vor, wie ein Projekt eigene Entities anlegt:
 * Ableitung von `Base`, Doctrine-Annotationen fuer die Spalten, `@PIM`-Annotationen fuer
 * das, was die API darueber hinaus wissen muss.
 *
 * Die Felder sind Beispiele und stehen fuer nichts Bestimmtes — bis `000-000-0017` trugen
 * sie Kommentare aus dem Kundenprojekt, aus dem diese Vorlage einmal herausgeschnitten
 * wurde: Mandanten-Lebenszyklen, ein `TrialExpiryChecker`, eine Stripe-Steuerbefreiung.
 * Nichts davon gab es je in diesem Baum.
 */
class Example extends Base {

	/**
	 * Ein Feld mit fester Werteliste.
	 *
	 * `@PIM\Select` veroeffentlicht die Optionen im Schema — ein Client kann daraus eine
	 * Auswahl bauen — und **prueft sie beim Schreiben**: Ein Wert ausserhalb der Liste wird
	 * mit `contentfly_general_invalid_params` abgewiesen (`000-000-0017`). Leer und `null`
	 * gehen durch; ob das erlaubt ist, entscheidet `nullable` an der Spalte.
	 *
	 * Die Werteliste selbst bleibt unveraendert: Sie ist Teil des Schemas, das die
	 * Charakterisierungstests aus `008-004-0005` zusichern. Umbenennen waere eine
	 * Datenaenderung, nicht das Entfernen eines Kommentars.
	 *
	 * @ORM\Column(type="string", length=32, options={"default": "active"})
	 * @PIM\Select(options="provisioning,active,trial_expired,suspended,deactivated")
	 */
	protected $state = 'active';

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 */
	protected $name;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 */
	protected $slug;

	/**
	 * Ein JSON-Feld.
	 *
	 * Doctrine kodiert und dekodiert die Spalte; ueber die API kommt und geht ein
	 * verschachteltes Objekt, kein String. Der zugehoerige `JsonType` ist mit `000-000-0017`
	 * dazugekommen — davor fiel ein solches Feld **still aus dem Schema**: Lesen lieferte es
	 * nicht, Schreiben scheiterte mit `contentfly_general_unknown_property`, und niemand
	 * erfuhr, warum.
	 *
	 * Beispielwert:
	 * {
	 *   "titel": "Beispiel",
	 *   "merkmale": ["a", "b"]
	 * }
	 *
	 * @ORM\Column(type="json", nullable=true)
	 */
	protected $jsonExample;

	/**
	 * Ein boolesches Feld mit Vorgabewert.
	 *
	 * @ORM\Column(type="boolean", options={"default": false})
	 */
	protected $boolExample = false;

}