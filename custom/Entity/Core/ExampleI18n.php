<?php
namespace Custom\Entity\Core;

use Areanet\PIM\Entity\BaseI18n;
use Doctrine\ORM\Mapping as ORM;

/**
 * The template's example of a TRANSLATABLE entity (000-000-0059).
 *
 * It extends `BaseI18n` instead of `Base`. That is the whole difference in the code, and it
 * changes the data model: a record is no longer one row but one row PER LANGUAGE, all sharing
 * the same `id`. `id` and `lang` together form the key. The API then expects a `lang` on every
 * read and write, and a group's `languages` decide which languages it may write.
 *
 * WHY IT IS IN THE TEMPLATE. Until 000-000-0059 neither the framework nor the template had a
 * single translatable entity. The i18n paths of the API were therefore unreachable for the test
 * suite — and two of them turned out not to narrow permissions by ownership. A feature that the
 * template does not show is also a feature nobody tests.
 *
 * A project that has no use for translations can delete this file; nothing refers to it
 * except the tests of the framework itself.
 */
#[ORM\Entity]
#[ORM\Table(name: 'example_i18n')]
class ExampleI18n extends BaseI18n {

	#[ORM\Column(type: 'string', length: 255, nullable: true)]
	protected $title;

}
