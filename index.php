<?php
/*
 * Der Web-Einstiegspunkt (007-001-0003).
 *
 * Er tut zwei Dinge und kennt dabei keinen Pfad in den Frameworkcode: Er laedt den Autoloader
 * und benennt das Projektverzeichnis. Ob Contentfly unter `lib/` im Projekt liegt oder unter
 * `vendor/areanet/contentfly/`, sieht diese Datei nicht.
 *
 * Bis 007-001-0002 stand hier `require_once __DIR__.'/lib/contentfly/bootstrap-web.php';` — der
 * Bootstrap lud daraufhin selbst den Autoloader. Ein Paket wird vom Autoloader geladen, es
 * laedt ihn nicht.
 */
require_once __DIR__ . '/vendor/autoload.php';

\Areanet\PIM\Classes\Kernel\Start::web(__DIR__);
