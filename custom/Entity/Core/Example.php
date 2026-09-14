<?php
namespace Custom\Entity\Core;

use Areanet\PIM\Entity\Base;
use Areanet\PIM\Classes\Annotations as PIM;
use Doctrine\ORM\Mapping as ORM;

/**
 * The template's example entity. It demonstrates how a project creates its own entities:
 * extending `Base`, `#[ORM\...]` attributes for the columns, `#[PIM\...]` attributes for
 * whatever else the API needs to know.
 *
 * **Attributes instead of annotations since Epic `010`.** Until then the same information was
 * written as `@ORM\...` and `@PIM\...` in the docblock. The difference is more than notation: an
 * attribute is PHP code. It is checked by the parser instead of being read by a library, a
 * typo shows up at load time, and a constant such as `APPCMS_ID_TYPE` is an
 * ordinary constant expression.
 *
 * **A trap that surfaced along the way:** an attribute is resolved against the `use` lines of
 * **its own file**. Anyone moving fields into a trait needs the imports there
 * as well — with annotations it worked without them, see `custom/Traits/User.php`.
 *
 * The fields are examples and stand for nothing in particular — until `000-000-0017` they
 * carried comments from the customer project this template was once cut out of:
 * tenant lifecycles, a `TrialExpiryChecker`, a Stripe tax exemption.
 * None of that ever existed in this tree.
 *
 * **Index and UniqueConstraint are separate attributes**, no longer nested inside
 * `Table`. Doctrine has always read them as independent declarations; as annotations they had
 * to go into an array under `Table` because annotations cannot repeat. An attribute may
 * repeat (`Attribute::IS_REPEATABLE`), so they now sit side by side.
 *
 * `idx_example_slug` IS NOT CREATED, and that is not a bug: DBAL omits an index
 * that an existing one already fulfils (`Table::isFulfilledBy()`) — the unique index on
 * the same column does. Verified with `SHOW INDEX`, both before and after the switch to
 * attributes. The declaration stays here because it demonstrates the notation; anyone who
 * needs an index that nothing else covers does get it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'example_entity')]
#[ORM\Index(name: 'idx_example_slug', columns: ['slug'])]
#[ORM\Index(name: 'idx_example_state', columns: ['state'])]
#[ORM\UniqueConstraint(name: 'uniq_example_slug', columns: ['slug'])]
class Example extends Base {

	/**
	 * A field with a fixed list of values.
	 *
	 * `#[PIM\Select]` publishes the options in the schema — a client can build a
	 * selection from them — and **validates them on write**: a value outside the list is
	 * rejected with `contentfly_general_invalid_params` (`000-000-0017`). Empty and `null`
	 * pass; whether that is allowed is decided by `nullable` on the column.
	 *
	 * The list of values itself stays unchanged: it is part of the schema that the
	 * characterization tests from `008-004-0005` guarantee. Renaming would be a
	 * data change, not the removal of a comment.
	 */
	#[ORM\Column(type: 'string', length: 32, options: ['default' => 'active'])]
	#[PIM\Select(options: 'provisioning,active,trial_expired,suspended,deactivated')]
	protected $state = 'active';

	#[ORM\Column(type: 'string', length: 255, nullable: true)]
	protected $name;

	#[ORM\Column(type: 'string', length: 255, nullable: true)]
	protected $slug;

	/**
	 * A JSON field.
	 *
	 * Doctrine encodes and decodes the column; through the API a nested object comes in and
	 * goes out, not a string. The matching `JsonType` was added with `000-000-0017`
	 * — before that such a field **silently dropped out of the schema**: reading did not return it,
	 * writing failed with `contentfly_general_unknown_property`, and nobody
	 * found out why.
	 *
	 * Example value:
	 * {
	 *   "title": "Example",
	 *   "features": ["a", "b"]
	 * }
	 */
	#[ORM\Column(type: 'json', nullable: true)]
	protected $jsonExample;

	/**
	 * A boolean field with a default value.
	 */
	#[ORM\Column(type: 'boolean', options: ['default' => false])]
	protected $boolExample = false;

}