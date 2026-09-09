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

/*
 * Diagnosepfad — nur dieser Router kennt ihn, die Anwendung nicht.
 *
 * `VersandfalleTest` muss belegen können, dass der Testserver **nicht** an einen echten MTA
 * zustellt. Von aussen ist das sonst nicht feststellbar: Ein leerer Postausgang beweist
 * nichts, solange offen ist, ob der Server das Fangskript überhaupt benutzt.
 *
 * Der Endpunkt, der die Falle nötig machte, ist mit `000-000-0016` entfernt. Sie bleibt
 * trotzdem: `$app['mailer']` steht Projekten weiter zur Verfügung, und eine Sicherung, die man
 * mit ihrem ersten Anlass abbaut, fehlt beim zweiten.
 *
 * Bewusst hier und nicht in der Anwendung: Der Router gehört zur Testinfrastruktur und läuft
 * in keiner Installation mit.
 */
if ($pfad === '/__test/sendmail-path') {
    header('Content-Type: application/json');
    echo json_encode(array('sendmail_path' => ini_get('sendmail_path')));

    return true;
}

if ($pfad !== '/' && is_file(__DIR__.'/..'.$pfad)) {
    return false; // vom eingebauten Server direkt ausliefern
}

require __DIR__.'/../index.php';
