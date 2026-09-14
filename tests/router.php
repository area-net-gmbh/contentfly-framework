<?php
/**
 * Router for the built-in PHP server (`php -S`).
 *
 * Without it **every** request goes through index.php — even the one for a file that exists on
 * disk. Apache does not do that: the `.htaccess` only rewrites when the requested file does
 * *not* exist (`RewriteCond %{REQUEST_FILENAME} !-f`).
 *
 * This is not a cosmetic flaw: `FileController::getAction()` answers a delivery with a redirect
 * to the direct path under `data/files/`. Without this router that redirect ends up back in the
 * application instead of delivering the file — and the tests measure something other than
 * production.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

/*
 * Diagnostic path — only this router knows it, the application does not.
 *
 * `MailTrapTest` has to be able to prove that the test server does **not** deliver to a real MTA.
 * From the outside that cannot otherwise be determined: an empty outbox proves nothing as long as
 * it is open whether the server uses the catch script at all.
 *
 * The endpoint that made the trap necessary was removed with `000-000-0016`. The trap stays
 * anyway: `$app['mailer']` remains available to projects, and a safeguard that is dismantled with
 * its first occasion is missing at the second.
 *
 * Deliberately here and not in the application: the router belongs to the test infrastructure and
 * does not run in any installation.
 */
if ($path === '/__test/sendmail-path') {
    header('Content-Type: application/json');
    echo json_encode(array('sendmail_path' => ini_get('sendmail_path')));

    return true;
}

if ($path !== '/' && is_file(__DIR__.'/..'.$path)) {
    return false; // served directly by the built-in server
}

require __DIR__.'/../index.php';
