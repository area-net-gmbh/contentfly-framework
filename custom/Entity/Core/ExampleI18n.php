<?php
namespace Custom\Entity\Core;

use Areanet\PIM\Entity\BaseI18n;
use Areanet\PIM\Classes\Annotations as PIM;
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

	/**
	 * The same in every language — `i18n_universal` (000-000-0075).
	 *
	 * A new translation takes the value from the main language, and writing it in one language
	 * writes it into every other language the group may write.
	 */
	#[ORM\Column(type: 'string', length: 64, nullable: true)]
	#[PIM\Config(i18n_universal: true)]
	protected $code;

	/**
	 * A reference to another translatable record — in the same language (000-000-0075).
	 *
	 * A translatable target has a key of two columns, so the reference needs two as well: the
	 * target's `id` and its `lang`, as `BaseI18nTree::$parent` does. The API reads it in the
	 * language of the request, or in `loadJoinedLang`.
	 *
	 * Universal like the parent of a tree (000-000-0078): every language points to the same
	 * record, each to its own translation of it. A new translation takes it from the main
	 * language and binds it to the language being written.
	 */
	#[ORM\ManyToOne(targetEntity: 'Custom\\Entity\\Core\\ExampleI18n')]
	#[PIM\Config(i18n_universal: true)]
	#[ORM\JoinColumn(name: 'related_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
	#[ORM\JoinColumn(name: 'related_lang', referencedColumnName: 'lang', onDelete: 'SET NULL')]
	protected $related;

}
