<?php
namespace Custom\Entity\Core;

use Areanet\PIM\Entity\Base;
use Areanet\PIM\Classes\Annotations as PIM;
use Doctrine\ORM\Mapping as ORM;

/**
 * Die Beispiel-Entity der Vorlage. Sie fuehrt vor, wie ein Projekt eigene Entities anlegt:
 * Ableitung von `Base`, `#[ORM\...]`-Attribute fuer die Spalten, `#[PIM\...]`-Attribute fuer
 * das, was die API darueber hinaus wissen muss.
 *
 * **Attribute statt Annotationen seit Epic `010`.** Bis dahin standen dieselben Angaben als
 * `@ORM\...` und `@PIM\...` im Docblock. Der Unterschied ist mehr als Schreibweise: Ein
 * Attribut ist PHP-Code. Es wird vom Parser gepruefft statt von einer Bibliothek gelesen, ein
 * Tippfehler faellt beim Laden auf, und eine Konstante wie `APPCMS_ID_TYPE` ist ein
 * gewoehnlicher konstanter Ausdruck.
 *
 * **Eine Falle, die dabei aufgefallen ist:** Ein Attribut wird gegen die `use`-Zeilen **seiner
 * eigenen Datei** aufgeloest. Wer Felder in einen Trait auslagert, braucht die Imports dort
 * ebenfalls — unter Annotationen ging es ohne, siehe `custom/Traits/User.php`.
 *
 * Die Felder sind Beispiele und stehen fuer nichts Bestimmtes — bis `000-000-0017` trugen
 * sie Kommentare aus dem Kundenprojekt, aus dem diese Vorlage einmal herausgeschnitten
 * wurde: Mandanten-Lebenszyklen, ein `TrialExpiryChecker`, eine Stripe-Steuerbefreiung.
 * Nichts davon gab es je in diesem Baum.
 *
 * **Index und UniqueConstraint stehen als eigene Attribute**, nicht mehr verschachtelt in
 * `Table`. Doctrine liest sie seit jeher als eigenstaendige Angaben; als Annotation mussten
 * sie mangels Wiederholbarkeit in ein Array unter `Table`. Ein Attribut darf sich
 * wiederholen (`Attribute::IS_REPEATABLE`), also stehen sie jetzt nebeneinander.
 *
 * `idx_example_slug` WIRD NICHT ANGELEGT, und das ist kein Fehler: DBAL laesst einen Index
 * weg, den ein vorhandener bereits erfuellt (`Table::isFulfilledBy()`) — der Unique-Index auf
 * derselben Spalte tut das. Nachgemessen mit `SHOW INDEX`, vor wie nach der Umstellung auf
 * Attribute. Die Deklaration bleibt hier stehen, weil sie die Schreibweise vorfuehrt; wer
 * einen Index braucht, den nichts anderes abdeckt, bekommt ihn auch.
 */
#[ORM\Entity]
#[ORM\Table(name: 'example_entity')]
#[ORM\Index(name: 'idx_example_slug', columns: ['slug'])]
#[ORM\Index(name: 'idx_example_state', columns: ['state'])]
#[ORM\UniqueConstraint(name: 'uniq_example_slug', columns: ['slug'])]
class Example extends Base {

	/**
	 * Ein Feld mit fester Werteliste.
	 *
	 * `#[PIM\Select]` veroeffentlicht die Optionen im Schema — ein Client kann daraus eine
	 * Auswahl bauen — und **prueft sie beim Schreiben**: Ein Wert ausserhalb der Liste wird
	 * mit `contentfly_general_invalid_params` abgewiesen (`000-000-0017`). Leer und `null`
	 * gehen durch; ob das erlaubt ist, entscheidet `nullable` an der Spalte.
	 *
	 * Die Werteliste selbst bleibt unveraendert: Sie ist Teil des Schemas, das die
	 * Charakterisierungstests aus `008-004-0005` zusichern. Umbenennen waere eine
	 * Datenaenderung, nicht das Entfernen eines Kommentars.
	 */
	#[ORM\Column(type: 'string', length: 32, options: ['default' => 'active'])]
	#[PIM\Select(options: 'provisioning,active,trial_expired,suspended,deactivated')]
	protected $state = 'active';

	#[ORM\Column(type: 'string', length: 255, nullable: true)]
	protected $name;

	#[ORM\Column(type: 'string', length: 255, nullable: true)]
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
	 */
	#[ORM\Column(type: 'json', nullable: true)]
	protected $jsonExample;

	/**
	 * Ein boolesches Feld mit Vorgabewert.
	 */
	#[ORM\Column(type: 'boolean', options: ['default' => false])]
	protected $boolExample = false;

}