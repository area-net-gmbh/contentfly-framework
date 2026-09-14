<?php
namespace Custom\Traits;

use Doctrine\ORM\Mapping as ORM;

/**
 * Extension point for custom fields on the user.
 *
 * THE IMPORT ABOVE IS NEW AND REQUIRED (010-001-0003). Before, `@ORM\Column` stood here without
 * any `use` — and it still worked, for a reason worth knowing:
 * for a property taken over from a trait, `ReflectionProperty::getDeclaringClass()` returns
 * the **using class**, not the trait. The `AnnotationReader` therefore resolved
 * `@ORM` against the imports of `Areanet\PIM\Entity\User`.
 *
 * An attribute does not work that way: it is resolved against the imports of **the file** it
 * appears in. Without the import, `ORM\Column` would be an unknown class here — and because
 * an unresolvable attribute is skipped, `nameExample` would **silently** drop out of the schema.
 * That is exactly what happened during the conversion and was caught by the schema comparison.
 */
trait User
{
	#[ORM\Column(type: 'string', length: 255, nullable: true)]
	protected $nameExample;
}
