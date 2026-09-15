<?php
/**
 * Inventory of an existing Contentfly project before migration (007-005-0001).
 *
 * Phase 1 of the migration guide asks for an inventory: custom types, plugins, login managers.
 * As a list of questions that answer depends on who reads the code and how carefully. This
 * script answers it from the code, the same way for every project, and read-only.
 *
 *     php tools/migration/inventory.php <backend-dir> [--json]
 *
 * <backend-dir> is the directory that contains `custom/` — for a project on the old layout the
 * one that also contains `lib/contentfly/` and `index.php`.
 *
 * ── Where the lists come from ─────────────────────────────────────────────────────────────
 *
 * They are copies, and each names its source. A copy can drift; InventoryToolTest checks the
 * two that matter against their sources (rector.php and ContainerKeysTest).
 *
 * ── What it does not do ───────────────────────────────────────────────────────────────────
 *
 * It changes nothing, runs no project code and connects to no database. Git is only read.
 *
 * ── Added after the first real migration (007-005-0005) ──────────────────────────────────
 *
 * The run on UFP found what the first version did not ask: entity traits Rector skips, an
 * annotation whose unbalanced bracket turns the rest of the docblock into text, configuration keys
 * that came from the project's own patches to its framework copy, and the DBAL 2 statement idiom
 * that made up most of phase 6.
 */

namespace Contentfly\Tools\Migration;

const REMOVED_ANNOTATIONS = array(          // rector.php, RemoveAnnotationRector
    'Rte', 'Textarea', 'Datetime', 'Time', 'Password', 'MatrixChooser', 'EntitySelector',
);

const REMOVED_FIELDS = array(               // rector.php, RemovedAttributeFieldsRector
    'Config'   => array('viewMode', 'showInList', 'listShorten', 'hide', 'label', 'tab', 'tabs', 'sort',
                        'isDatalist', 'isSidebar', 'lines', 'accept', 'readonly', 'filter'),
    'Checkbox' => array('horizontalAlignment', 'columns'),
    'Radio'    => array('horizontalAlignment', 'columns', 'select'),
);

const CONTAINER_KEYS = array(               // tests/Integration/ContainerKeysTest.php
    'guaranteed'    => array('is_installed', 'debug', 'database', 'mailer', 'routeManager', 'consoleManager',
                             'request_stack', 'dispatcher', 'auth.user', 'loginProviders', 'orm.em'),
    'after_install' => array('db', 'dbs'),
    'after_login'   => array('auth.token'),
    'internal'      => array('dbs.options', 'auth', 'console', 'helper', 'loginThrottle', 'tokenAuthenticator',
                             'userProvisioning', 'groupMapping', 'tokenHandler', 'thumbnailSettings',
                             'schema', 'typeManager', 'pluginManager', 'kernel', 'resolver', 'argument_resolver'),
);

/**
 * The seven code paths without a trigger in the framework (epic 007). Each pattern is what a
 * project would have to write to use the path.
 */
const UNTRIGGERED_PATHS = array(
    'i18n_universal'        => '/i18n_universal/',
    'I18nPermission'        => '/I18nPermission|getLang(?:Read|Write|Delete|Permission)|setLang(?:Read|Write|Delete|Permission)/',
    'encoded'               => '/\bencoded\s*[=:]\s*true/',
    'OneToOne cascade'      => '/OneToOne/',
    'MultijoinType acceptFrom' => '/acceptFrom/',
    'canExport'             => '/canExport|\bexport\s*[=:]/',
    'getExtended'           => '/getExtended|\bextended\s*[=:]/',
);

function main(array $argv): int
{
    $json = in_array('--json', $argv, true);
    $args = array_values(array_filter(array_slice($argv, 1), static fn ($a) => $a !== '--json'));

    if (count($args) !== 1 || !is_dir($args[0] . '/custom')) {
        fwrite(STDERR, "Usage: php tools/migration/inventory.php <backend-dir> [--json]\n"
            . "<backend-dir> must contain custom/.\n");
        return 2;
    }

    $report = inventory(rtrim(realpath($args[0]), '/'));

    echo $json ? json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
               : render($report);

    return 0;
}

/** @return array<string,mixed> */
function inventory(string $dir): array
{
    $custom  = phpFiles($dir . '/custom');
    $plugins = is_dir($dir . '/plugins') ? phpFiles($dir . '/plugins') : array();
    $project = array_merge($custom, $plugins);

    return array(
        'directory'      => $dir,
        'framework_copy' => frameworkCopy($dir),
        'composer'       => composerRequire($dir),
        'entities'       => entities($dir, phpFiles($dir . '/custom/Entity')),
        'types'          => classesExtending($dir, $project, '/extends\s+\\\\?(?:Areanet\\\\PIM\\\\Classes\\\\)?Type\b/'),
        'login_managers' => classesExtending($dir, $project, '/extends\s+\\\\?(?:[\w\\\\]*\\\\)?LoginManager\b/'),
        'commands'       => classesExtending($dir, $project, '/extends\s+\\\\?(?:[\w\\\\]*\\\\)?(?:CustomCommand|Command)\b/'),
        'controller_providers' => classesExtending($dir, $project, '/extends\s+\\\\?(?:[\w\\\\]*\\\\)?BaseControllerProvider\b|implements\s+[\w\\\\]*ControllerProviderInterface/'),
        'controllers'    => classesExtending($dir, $project, '/extends\s+\\\\?(?:[\w\\\\]*\\\\)?BaseController\b/'),
        'plugins'        => is_dir($dir . '/plugins') ? array_values(array_diff(scandir($dir . '/plugins'), array('.', '..', '.gitkeep', '.DS_Store'))) : array(),
        'container_keys' => containerKeys($dir, $project),
        'silex_references' => grepFiles($dir, $project, '/\bSilex\\\\|\bPimple\\\\/'),
        'untriggered_paths' => untriggeredPaths($dir, $project),
        'entity_traits'  => entityTraits($dir, $project),
        'unbalanced_annotations' => unbalancedAnnotations($dir, $project),
        'config_keys'    => configKeys($dir),
        'dbal2_statements' => grepFiles($dir, $project, '/\$\w+->(?:fetchAll|fetch|fetchColumn)\(\s*\)/'),
        'request_get'    => grepFiles($dir, $project, '/\$request->get\(/'),
    );
}

/** @return list<string> */
function phpFiles(string $directory): array
{
    if (!is_dir($directory)) {
        return array();
    }

    $files = array();
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $entry) {
        $path = $entry->getPathname();
        // A project's own Composer tree is not project code.
        if ($entry->getExtension() === 'php' && !preg_match('#/vendor/#', $path)) {
            $files[] = $path;
        }
    }

    sort($files);

    return $files;
}

/**
 * The source without comments. What a project WRITES counts, not what a comment explains — the
 * template itself mentions `$app['request']` and `encoded` in comments only.
 */
function code(string $file): string
{
    $tokens = \PhpToken::tokenize((string) file_get_contents($file));

    return implode('', array_map(
        static fn ($t) => $t->is(array(T_COMMENT, T_DOC_COMMENT)) ? '' : $t->text,
        $tokens
    ));
}

function relative(string $dir, string $path): string
{
    return substr($path, strlen($dir) + 1);
}

/** @return array<string,mixed> */
function frameworkCopy(string $dir): array
{
    $copy = $dir . '/lib/contentfly';

    if (!is_dir($copy)) {
        return array('present' => false);
    }

    // A directory with no PHP left in it — `.DS_Store` after deleting the copy — is no copy (W-1).
    if (count(phpFiles($copy)) === 0) {
        return array('present' => false, 'leftover' => array_values(array_diff(scandir($copy), array('.', '..'))));
    }

    $version = null;
    if (is_file($copy . '/version.php') && preg_match("/APP_VERSION'?\s*[,=]\s*'([^']+)'/", (string) file_get_contents($copy . '/version.php'), $m)) {
        $version = $m[1];
    }

    $result = array('present' => true, 'version' => $version, 'files' => count(phpFiles($copy)));

    // Changes the project made to its copy after importing it, from the project's own history.
    $top = trim((string) shell_exec('git -C ' . escapeshellarg($dir) . ' rev-parse --show-toplevel 2>/dev/null'));
    if ($top !== '') {
        $path    = relative($top, realpath($copy));
        $commits = array_filter(explode("\n", trim((string) shell_exec(
            'git -C ' . escapeshellarg($top) . ' log --reverse --format=%h -- ' . escapeshellarg($path) . ' 2>/dev/null'
        ))));

        if ($commits) {
            $first   = reset($commits);
            $changed = array_filter(explode("\n", trim((string) shell_exec(
                'git -C ' . escapeshellarg($top) . ' diff --name-only ' . escapeshellarg($first) . ' HEAD -- ' . escapeshellarg($path) . ' 2>/dev/null'
            ))));

            $result['history'] = array(
                'import_commit'           => $first,
                'commits_after_import'    => count($commits) - 1,
                'files_changed_after_import' => array_values(array_map(static fn ($f) => substr($f, strlen($path) + 1), $changed)),
            );
        }
    }

    return $result;
}

/** @return array<string,string> */
function composerRequire(string $dir): array
{
    $file = $dir . '/composer.json';
    if (!is_file($file)) {
        return array();
    }

    $data = json_decode((string) file_get_contents($file), true);

    return is_array($data['require'] ?? null) ? $data['require'] : array();
}

/** @return array<string,mixed> */
function entities(string $dir, array $files): array
{
    $list = array();
    $totals = array('orm_annotations' => 0, 'orm_attributes' => 0, 'pim_annotations' => 0,
                    'removed_annotations' => 0, 'removed_fields' => 0);

    foreach ($files as $file) {
        $source = (string) file_get_contents($file);
        if (!preg_match('/^\s*(?:abstract\s+|final\s+)?class\s+(\w+)(?:\s+extends\s+([\w\\\\]+))?/m', $source, $m)) {
            continue;
        }

        $docblocks = implode("\n", array_map(static fn ($t) => $t->text, array_filter(
            \PhpToken::tokenize($source), static fn ($t) => $t->is(T_DOC_COMMENT)
        )));

        $removedAnnotations = array();
        foreach (REMOVED_ANNOTATIONS as $name) {
            $count = preg_match_all('/@PIM\\\\' . $name . '\b/', $docblocks);
            if ($count) {
                $removedAnnotations[$name] = $count;
            }
        }

        $removedFields = array();
        foreach (REMOVED_FIELDS as $annotation => $fields) {
            if (!preg_match_all('/@PIM\\\\' . $annotation . '\s*\(([^)]*)\)/s', $docblocks, $calls)) {
                continue;
            }
            foreach ($calls[1] as $arguments) {
                foreach ($fields as $field) {
                    if (preg_match('/(?<![\w])' . $field . '\s*=/', $arguments)) {
                        $key = $annotation . '.' . $field;
                        $removedFields[$key] = ($removedFields[$key] ?? 0) + 1;
                    }
                }
            }
        }

        $entry = array(
            'file'                => relative($dir, $file),
            'class'               => $m[1],
            'extends'             => $m[2] ?? null,
            'orm_annotations'     => preg_match_all('/@ORM\\\\\w+/', $docblocks),
            'orm_attributes'      => preg_match_all('/#\[ORM\\\\\w+/', $source),
            'pim_annotations'     => preg_match_all('/@PIM\\\\\w+/', $docblocks),
            'removed_annotations' => $removedAnnotations,
            'removed_fields'      => $removedFields,
        );

        $totals['orm_annotations']     += $entry['orm_annotations'];
        $totals['orm_attributes']      += $entry['orm_attributes'];
        $totals['pim_annotations']     += $entry['pim_annotations'];
        $totals['removed_annotations'] += array_sum($removedAnnotations);
        $totals['removed_fields']      += array_sum($removedFields);

        $list[] = $entry;
    }

    return array('count' => count($list), 'totals' => $totals, 'list' => $list);
}

/** @return list<array{file:string,class:string}> */
function classesExtending(string $dir, array $files, string $pattern): array
{
    $found = array();

    foreach ($files as $file) {
        $source = code($file);
        if (preg_match($pattern, $source) && preg_match('/^\s*(?:abstract\s+|final\s+)?class\s+(\w+)/m', $source, $m)) {
            $found[] = array('file' => relative($dir, $file), 'class' => $m[1]);
        }
    }

    return $found;
}

/** @return array<string,mixed> */
function containerKeys(string $dir, array $files): array
{
    $usage = array();

    foreach ($files as $file) {
        if (preg_match_all('/\$(?:this->)?app\[\s*[\'"]([^\'"]+)[\'"]\s*\]/', code($file), $m)) {
            foreach ($m[1] as $key) {
                $usage[$key] = ($usage[$key] ?? 0) + 1;
            }
        }
    }

    ksort($usage);

    $classified = array();
    foreach ($usage as $key => $count) {
        $class = 'unknown';
        foreach (CONTAINER_KEYS as $name => $keys) {
            if (in_array($key, $keys, true)) {
                $class = $name;
                break;
            }
        }
        $classified[$class][$key] = $count;
    }

    return $classified;
}

/** @return array<string,int> */
function grepFiles(string $dir, array $files, string $pattern): array
{
    $hits = array();

    foreach ($files as $file) {
        $count = preg_match_all($pattern, code($file));
        if ($count) {
            $hits[relative($dir, $file)] = $count;
        }
    }

    return $hits;
}

/** @return array<string,array<string,int>> */
function untriggeredPaths(string $dir, array $files): array
{
    $result = array();

    foreach (UNTRIGGERED_PATHS as $name => $pattern) {
        $result[$name] = grepFiles($dir, $files, $pattern);
    }

    return $result;
}

/**
 * Traits that carry Doctrine mapping (W-2).
 *
 * Rector over `Entity/` does not reach a trait in `Traits/`, and a trait without
 * `use Doctrine\ORM\Mapping as ORM;` is skipped silently even when it is in the path. ORM 3 then
 * does not see its columns, and the schema update plans to drop them. The same missing import turns an
 * attribute `#[ORM\Column]` into a class that does not exist — PHP does not complain, Doctrine ignores it.
 *
 * @return list<array{file:string,trait:string,orm_annotations:int,orm_attributes:int,missing_imports:list<string>}>
 */
function entityTraits(string $dir, array $files): array
{
    $found = array();

    foreach ($files as $file) {
        $source = (string) file_get_contents($file);
        if (!preg_match('/^\s*trait\s+(\w+)/m', code($file), $m)) {
            continue;
        }

        $docblocks   = docblocks($source);
        $annotations = preg_match_all('/@ORM\\\\\w+/', $docblocks);
        $attributes  = preg_match_all('/#\[ORM\\\\\w+/', $source);

        if (!$annotations && !$attributes) {
            continue;
        }

        $missing = array();
        if (!preg_match('/^\s*use\s+\\\\?Doctrine\\\\ORM\\\\Mapping\s+as\s+ORM\s*;/m', $source)) {
            $missing[] = 'Doctrine\ORM\Mapping as ORM';
        }
        if (preg_match('/@PIM\\\\|#\[PIM\\\\/', $source) && !preg_match('/^\s*use\s+\\\\?Areanet\\\\PIM\\\\Classes\\\\Annotations\s+as\s+PIM\s*;/m', $source)) {
            $missing[] = 'Areanet\PIM\Classes\Annotations as PIM';
        }

        $found[] = array('file' => relative($dir, $file), 'trait' => $m[1], 'orm_annotations' => $annotations,
                         'orm_attributes' => $attributes, 'missing_imports' => $missing);
    }

    return $found;
}

/**
 * Docblocks whose `@ORM`/`@PIM` annotations do not close their brackets (W-2).
 *
 * `@ORM\Column(type="string", nullable=true))` made Rector read everything after it as text, and the
 * next `@PIM\Config` became a comment. For `label` that cost nothing; for `excludeFromSync` a setting
 * would have been lost without a message.
 *
 * @return list<string> file:line of the docblock
 */
function unbalancedAnnotations(string $dir, array $files): array
{
    $found = array();

    foreach ($files as $file) {
        foreach (\PhpToken::tokenize((string) file_get_contents($file)) as $token) {
            if (!$token->is(T_DOC_COMMENT) || !preg_match('/@(?:ORM|PIM)\\\\/', $token->text)) {
                continue;
            }

            $withoutStrings = preg_replace('/"(?:[^"\\\\]|\\\\.)*"/s', '""', $token->text);
            if (substr_count($withoutStrings, '(') !== substr_count($withoutStrings, ')')) {
                $found[] = relative($dir, $file) . ':' . $token->line;
            }
        }
    }

    return $found;
}

/**
 * The keys `custom/config.php` sets, each placed against the framework (W-2, L-6).
 *
 * - `framework`: declared by Contentfly 2 (`Classes\Config` of the framework this tool ships with).
 * - `removed`:   declared by the project's old copy as imported, but not by Contentfly 2.
 * - `patch`:     declared by the old copy only since the project changed it — a project patch whose
 *                 behaviour went away with `lib/`. Without git history both land in `old_copy_only`.
 * - `project`:   declared nowhere — the project's own key, allowed since 000-000-0040.
 *
 * Comparing the configuration with the guide alone misses the `patch` keys; UFP had four.
 *
 * @return array<string,list<string>>
 */
function configKeys(string $dir): array
{
    $file = $dir . '/custom/config.php';
    if (!is_file($file)) {
        return array();
    }

    preg_match_all('/\$\w+->([A-Z][A-Z0-9_]*)\s*=(?!=)/', code($file), $m);
    $keys = array_values(array_unique($m[1]));
    sort($keys);

    $new     = declaredConfigKeys((string) @file_get_contents(dirname(__DIR__, 2) . '/lib/contentfly/Classes/Config.php'));
    $oldPath = $dir . '/lib/contentfly/Classes/Config.php';
    $old     = is_file($oldPath) ? declaredConfigKeys((string) file_get_contents($oldPath)) : array();
    $import  = null;

    $history = frameworkCopy($dir)['history'] ?? null;
    if ($history !== null) {
        $top  = trim((string) shell_exec('git -C ' . escapeshellarg($dir) . ' rev-parse --show-toplevel 2>/dev/null'));
        $path = relative($top, realpath($oldPath) ?: $oldPath);
        $import = declaredConfigKeys((string) shell_exec(
            'git -C ' . escapeshellarg($top) . ' show ' . escapeshellarg($history['import_commit'] . ':' . $path) . ' 2>/dev/null'
        ));
    }

    $result = array('framework' => array(), 'removed' => array(), 'patch' => array(), 'old_copy_only' => array(), 'project' => array());
    foreach ($keys as $key) {
        if (in_array($key, $new, true)) {
            $result['framework'][] = $key;
        } elseif (in_array($key, $old, true)) {
            $class = $import === null ? 'old_copy_only' : (in_array($key, $import, true) ? 'removed' : 'patch');
            $result[$class][] = $key;
        } else {
            $result['project'][] = $key;
        }
    }

    return array_filter($result);
}

/** @return list<string> */
function declaredConfigKeys(string $source): array
{
    preg_match_all('/^\s*(?:public|var)\s+\$([A-Z][A-Z0-9_]*)\b/m', $source, $m);

    return $m[1];
}

function docblocks(string $source): string
{
    return implode("\n", array_map(static fn ($t) => $t->text, array_filter(
        \PhpToken::tokenize($source), static fn ($t) => $t->is(T_DOC_COMMENT)
    )));
}

function render(array $r): string
{
    $out = array('Contentfly migration inventory', str_repeat('=', 30), 'Directory: ' . $r['directory'], '');

    $copy = $r['framework_copy'];
    $out[] = '## Framework copy (lib/contentfly)';
    if (!$copy['present']) {
        $out[] = isset($copy['leftover'])
            ? 'none — lib/contentfly exists but holds no PHP files (' . (implode(', ', $copy['leftover']) ?: 'empty') . ')'
            : 'none — the framework is not copied into this project';
    } else {
        $out[] = sprintf('version %s, %d PHP files', $copy['version'] ?? 'unknown', $copy['files']);
        if (isset($copy['history'])) {
            $h = $copy['history'];
            $out[] = sprintf('imported in %s, changed by the project in %d commits, %d files:',
                $h['import_commit'], $h['commits_after_import'], count($h['files_changed_after_import']));
            foreach ($h['files_changed_after_import'] as $f) {
                $out[] = '  - ' . $f;
            }
        }
    }

    $out[] = '';
    $out[] = '## composer.json require';
    foreach ($r['composer'] as $package => $constraint) {
        $out[] = "  $package: $constraint";
    }

    $e = $r['entities'];
    $out[] = '';
    $out[] = sprintf('## Entities: %d', $e['count']);
    $out[] = sprintf('  @ORM annotations %d · #[ORM attributes %d · @PIM annotations %d · removed @PIM annotations %d · removed @PIM fields %d',
        $e['totals']['orm_annotations'], $e['totals']['orm_attributes'], $e['totals']['pim_annotations'],
        $e['totals']['removed_annotations'], $e['totals']['removed_fields']);
    foreach ($e['list'] as $entity) {
        $out[] = sprintf('  - %s (%s) extends %s', $entity['class'], $entity['file'], $entity['extends'] ?? '—');
    }

    foreach (array('types' => 'Custom types', 'login_managers' => 'Login managers', 'commands' => 'Commands',
                   'controller_providers' => 'Controller providers', 'controllers' => 'Controllers') as $key => $title) {
        $out[] = '';
        $out[] = sprintf('## %s: %d', $title, count($r[$key]));
        foreach ($r[$key] as $class) {
            $out[] = sprintf('  - %s (%s)', $class['class'], $class['file']);
        }
    }

    $out[] = '';
    $out[] = sprintf('## Plugins: %d', count($r['plugins']));
    foreach ($r['plugins'] as $plugin) {
        $out[] = '  - ' . $plugin;
    }

    $out[] = '';
    $out[] = '## Container keys ($app[...])';
    foreach ($r['container_keys'] as $class => $keys) {
        $out[] = sprintf('  %s: %s', $class, implode(', ', array_map(static fn ($k, $c) => "$k ($c)", array_keys($keys), $keys)));
    }

    $out[] = '';
    $out[] = sprintf('## Silex/Pimple references: %d files', count($r['silex_references']));
    foreach ($r['silex_references'] as $file => $count) {
        $out[] = "  - $file ($count)";
    }

    $out[] = '';
    $out[] = '## Code paths without a trigger in the framework (epic 007)';
    foreach ($r['untriggered_paths'] as $name => $hits) {
        $out[] = sprintf('  %s: %s', $name, $hits ? implode(', ', array_map(static fn ($f, $c) => "$f ($c)", array_keys($hits), $hits)) : 'not used');
    }

    $out[] = '';
    $out[] = sprintf('## Traits with Doctrine mapping: %d', count($r['entity_traits']));
    foreach ($r['entity_traits'] as $t) {
        $out[] = sprintf('  - %s (%s): @ORM annotations %d, #[ORM attributes %d%s', $t['trait'], $t['file'],
            $t['orm_annotations'], $t['orm_attributes'],
            $t['missing_imports'] ? ' — MISSING IMPORT: ' . implode(', ', $t['missing_imports']) . ' (Rector skips it, Doctrine ignores it)' : '');
    }

    $out[] = '';
    $out[] = sprintf('## Annotations with unbalanced brackets: %d', count($r['unbalanced_annotations']));
    foreach ($r['unbalanced_annotations'] as $where) {
        $out[] = '  - ' . $where . ' (fix before Rector: the rest of the docblock would be read as text)';
    }

    $out[] = '';
    $out[] = '## Configuration keys set in custom/config.php';
    $labels = array('framework' => 'declared by Contentfly 2', 'removed' => 'removed from the framework',
                    'patch' => 'PROJECT PATCH to the old copy — its behaviour is gone with lib/',
                    'old_copy_only' => 'only in the old copy (removed or project patch; no git history)',
                    'project' => 'project keys');
    foreach ($r['config_keys'] as $class => $keys) {
        $out[] = sprintf('  %s (%d): %s', $labels[$class], count($keys), implode(', ', $keys));
    }

    foreach (array('dbal2_statements' => 'DBAL 2 statement fetches (phase 6, see tools/migration/dbal3-statements.php)',
                   'request_get' => 'Request::get() calls (deprecated in Symfony 7.4)') as $key => $title) {
        $out[] = '';
        $out[] = sprintf('## %s: %d in %d files', $title, array_sum($r[$key]), count($r[$key]));
    }

    return implode("\n", $out) . "\n";
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(main($argv));
}
