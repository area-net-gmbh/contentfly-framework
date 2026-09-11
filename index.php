<?php
/*
 * Der Web-Einstiegspunkt benennt das Projektverzeichnis (007-001-0002).
 *
 * Das Framework rechnet es nicht mehr aus seiner eigenen Lage aus — es bekommt es von hier.
 * Damit ist es gleichgueltig, ob der Frameworkcode unter `lib/` im Projekt liegt oder als
 * Paket unter `vendor/`.
 */
define('CONTENTFLY_PROJEKT', __DIR__);

require_once __DIR__.'/lib/contentfly/bootstrap-web.php';
