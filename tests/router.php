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

/*
 * Server-side coverage — only when asked for (000-000-0056).
 *
 * 364 of the tests are integration tests: they send HTTP to this server, and the framework code
 * they exercise runs HERE, not in PHPUnit. `phpunit --coverage-*` only sees its own process, so a
 * coverage report without this block would be systematically too low — and lowest exactly where
 * the integration suite tests hardest: permissions, authentication, uploads.
 *
 * With `CONTENTFLY_COVERAGE_DIR` set and PCOV loaded, every request writes its own partial report
 * there; `phpcov merge` joins them with PHPUnit's report into one. Without the variable nothing
 * here runs, and the server behaves exactly as before.
 *
 * WHICH CODE COUNTS comes from the `<source>` section of phpunit.xml.dist — one list for both
 * halves. Two lists would drift apart, and the merged number would compare different sets.
 */
$coverageDirectory = getenv('CONTENTFLY_COVERAGE_DIR') ?: null;

if ($coverageDirectory !== null && extension_loaded('pcov')) {
    require_once __DIR__.'/../vendor/autoload.php';

    $root   = dirname(__DIR__);
    $config = simplexml_load_file($root.'/phpunit.xml.dist');
    $files  = new SebastianBergmann\FileIterator\Facade();

    // A file list instead of includeDirectory()/excludeDirectory(), which php-code-coverage 10.1
    // deprecates. An exclude is a set difference — computed here, so the list stays right if
    // phpunit.xml.dist gains one.
    $collect = static function (iterable $directories) use ($root, $files): array {
        $found = array();
        foreach ($directories as $directory) {
            $found = array_merge($found, $files->getFilesAsArray($root.'/'.$directory, '.php'));
        }

        return $found;
    };

    $filter = new SebastianBergmann\CodeCoverage\Filter();
    $filter->includeFiles(array_values(array_diff(
        $collect($config->source->include->directory ?? array()),
        $collect($config->source->exclude->directory ?? array())
    )));

    $coverage = new SebastianBergmann\CodeCoverage\CodeCoverage(
        (new SebastianBergmann\CodeCoverage\Driver\Selector())->forLineCoverage($filter),
        $filter
    );
    // Only what this request executed. PHPUnit's own report already lists every file of
    // <source>, the unexecuted ones included; repeating that list in each of ~700 partial
    // reports multiplied their size a hundredfold without adding a single line.
    $coverage->excludeUncoveredFiles();
    $coverage->start($path);

    register_shutdown_function(static function () use ($coverage, $coverageDirectory): void {
        $coverage->stop();
        (new SebastianBergmann\CodeCoverage\Report\PHP())->process(
            $coverage,
            $coverageDirectory.'/request-'.bin2hex(random_bytes(8)).'.cov'
        );
    });
}

require __DIR__.'/../index.php';
