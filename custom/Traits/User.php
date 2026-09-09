<?php
namespace Custom\Traits;

use Doctrine\ORM\Mapping as ORM;

/**
 * Erweiterungspunkt fuer eigene Felder am Benutzer.
 *
 * DER IMPORT OBEN IST NEU UND NOETIG (010-001-0003). Vorher stand hier `@ORM\Column` ohne
 * jeden `use` — und es funktionierte trotzdem, aus einem Grund, den man kennen muss:
 * `ReflectionProperty::getDeclaringClass()` liefert fuer eine aus einem Trait uebernommene
 * Eigenschaft die **benutzende Klasse**, nicht den Trait. Der `AnnotationReader` loeste
 * `@ORM` also gegen die Imports von `Areanet\PIM\Entity\User` auf.
 *
 * Bei einem Attribut geht das nicht: Es wird gegen die Imports **der Datei** aufgeloest, in
 * der es steht. Ohne den Import waere `ORM\Column` hier eine unbekannte Klasse — und weil
 * ein unaufloesbares Attribut uebergangen wird, fiele `nameExample` **still** aus dem Schema.
 * Genau das ist beim Umstellen passiert und im Schema-Vergleich aufgefallen.
 */
trait User
{
	#[ORM\Column(type: 'string', length: 255, nullable: true)]
	protected $nameExample;
}
