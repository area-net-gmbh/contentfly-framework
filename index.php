<?php
/*
 * The web entry point (007-001-0003).
 *
 * It does two things and knows no path into the framework code: it loads the autoloader and
 * names the project directory. Whether Contentfly lives under `lib/` in the project or under
 * `vendor/areanet/contentfly/`, this file cannot tell.
 *
 * Until 007-001-0002 this said `require_once __DIR__.'/lib/contentfly/bootstrap-web.php';` — the
 * bootstrap then loaded the autoloader by itself. A package is loaded by the autoloader; it does
 * not load it.
 */
require_once __DIR__ . '/vendor/autoload.php';

\Areanet\PIM\Classes\Kernel\Start::web(__DIR__);
