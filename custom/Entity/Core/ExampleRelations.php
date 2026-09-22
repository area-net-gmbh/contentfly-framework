<?php
namespace Custom\Entity\Core;

use Areanet\PIM\Entity\Base;
use Areanet\PIM\Classes\Annotations as PIM;
use Doctrine\ORM\Mapping as ORM;

/**
 * The template's example of the RELATION FIELD TYPES (000-000-0067).
 *
 * One field per type that neither the framework nor the template used anywhere before:
 * a single file, several files, a checkbox list and a radio choice from a managed option list,
 * and a one-to-one join to another entity. Which type a field gets is decided by its mapping —
 * the comment above each field names the combination that selects it.
 *
 * WHY IT IS IN THE TEMPLATE. Projects use these types, and every value of such a field passes
 * through their code on write and on read — including the permission checks that hide a joined
 * record the caller may not read (`pim_blocked`). Without an entity that has such a field, no
 * test reached that code: the first coverage measurement (000-000-0056) found `Classes/Types`
 * at 34 %. The same reasoning as `ExampleI18n`: a feature the template does not show is a
 * feature nobody tests.
 *
 * A project that needs none of this can delete this file; nothing refers to it except the tests
 * of the framework itself.
 */
#[ORM\Entity]
#[ORM\Table(name: 'example_relations')]
class ExampleRelations extends Base {

	#[ORM\Column(type: 'string', length: 255, nullable: true)]
	protected $title;

	/**
	 * One file — `ManyToOne` to `PIM\File` selects the `file` type.
	 *
	 * On write it takes the id of an uploaded file; an unknown id is rejected. On read it returns
	 * the file, or `{"id": …, "pim_blocked": true}` when the caller may not read files.
	 */
	#[ORM\ManyToOne(targetEntity: 'Areanet\\PIM\\Entity\\File')]
	#[ORM\JoinColumn(onDelete: 'SET NULL')]
	protected $attachment;

	/**
	 * Several files — `ManyToMany` to `PIM\File` selects the `multifile` type.
	 */
	#[ORM\ManyToMany(targetEntity: 'Areanet\\PIM\\Entity\\File')]
	#[ORM\JoinTable(name: 'example_relations_attachments')]
	protected $attachments;

	/**
	 * Several values from an option list — `ManyToMany` to `PIM\Option` plus `#[PIM\Checkbox]`.
	 *
	 * The option list itself is an `OptionGroup` named `Core\ExampleRelations/categories`,
	 * created on first use; the schema publishes its id under `group`.
	 */
	#[ORM\ManyToMany(targetEntity: 'Areanet\\PIM\\Entity\\Option')]
	#[ORM\JoinTable(name: 'example_relations_categories')]
	#[PIM\Checkbox]
	protected $categories;

	/**
	 * One value from an option list — `ManyToOne` to `PIM\Option` plus `#[PIM\Radio]`.
	 */
	#[ORM\ManyToOne(targetEntity: 'Areanet\\PIM\\Entity\\Option')]
	#[ORM\JoinColumn(onDelete: 'SET NULL')]
	#[PIM\Radio]
	protected $category;

	/**
	 * A record that belongs to this one alone — `OneToOne` selects the `onejoin` type.
	 *
	 * Written as a nested object: without an `id` it creates the joined record, with an `id` it
	 * updates it. A translatable target is not supported and fails at schema time.
	 */
	#[ORM\OneToOne(targetEntity: 'Custom\\Entity\\Core\\Example')]
	#[ORM\JoinColumn(onDelete: 'SET NULL')]
	protected $example;

	/**
	 * One record of another entity — `ManyToOne` selects the `join` type (000-000-0075).
	 *
	 * `/api/list` filters on it with `where: {"owner": <id>}`, and with `-1` on records that have none.
	 */
	#[ORM\ManyToOne(targetEntity: 'Custom\\Entity\\Core\\Example')]
	#[ORM\JoinColumn(onDelete: 'SET NULL')]
	protected $owner;

	/**
	 * Several records of another entity — `ManyToMany` selects the `multijoin` type (000-000-0075).
	 *
	 * Filtered like `owner`: `where: {"examples": <id>}`, or `-1` for records without any.
	 */
	#[ORM\ManyToMany(targetEntity: 'Custom\\Entity\\Core\\Example')]
	#[ORM\JoinTable(name: 'example_relations_examples')]
	protected $examples;

}
