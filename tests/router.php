<?php
/**
 * Router für den eingebauten PHP-Server (`php -S`).
 *
 * Ohne ihn geht **jede** Anfrage durch index.php — auch die für eine Datei, die auf der Platte
 * liegt. Apache tut das nicht: Die `.htaccess` leitet nur um, wenn die angeforderte Datei
 * *nicht* existiert (`RewriteCond %{REQUEST_FILENAME} !-f`).
 *
 * Das ist kein Schönheitsfehler: `FileController::getAction()` beantwortet eine Auslieferung mit
 * einem Redirect auf den direkten Pfad unter `data/files/`. Ohne diesen Router landet dieser
 * Redirect wieder in der Anwendung, statt die Datei zu liefern — und Tests messen etwas anderes
 * als die Produktion.
 */
$pfad = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($pfad !== '/' && is_file(__DIR__.'/..'.$pfad)) {
    return false; // vom eingebauten Server direkt ausliefern
}

require __DIR__.'/../index.php';
