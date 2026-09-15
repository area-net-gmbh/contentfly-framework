<?php
/**
 * Moves the DBAL 2 statement idiom to DBAL 3 (007-005-0005, measured on UFP in 007-005-0004).
 *
 * A project that talks to `$app['database']` directly usually writes
 *
 *     $statement = $dbal->prepare($sql);
 *     $statement->bindValue('id', $id);
 *     $statement->execute();
 *     $rows = $statement->fetchAll();
 *
 * DBAL 3 removed `Statement::fetchAll()`/`fetch()`; `execute()` hands back a `Result`, which does the
 * fetching. UFP had 362 of these in 39 files — the largest block of phase 6. Rector's DBAL set does not
 * help: it renames `Statement::fetchAll()` to `fetchAllAssociative()`, which a Statement does not have
 * in DBAL 3 either.
 *
 *     php tools/migration/dbal3-statements.php <dir>... [--write]
 *
 * Without `--write` it only reports. What it rewrites, per `execute()` on a plain variable:
 *
 * - followed by `fetchAll()`/`fetch()` on that variable → `$xResult = $x->executeQuery(...)`, and the
 *   fetches become `$xResult->fetchAllAssociative()`/`fetchAssociative()`. DBAL 2 statements fetched
 *   associatively by default (`Connection::$defaultFetchMode = PDO::FETCH_ASSOC`), so the rows keep
 *   their shape. A `rowCount()` in between moves to the Result as well.
 * - followed only by `rowCount()` → `$xAffected = $x->executeStatement(...)`, and `$x->rowCount()`
 *   becomes `$xAffected`.
 * - followed by nothing → `$x->executeStatement(...)`.
 *
 * "Followed" means: in the same function body, before the variable is executed again or reassigned. A
 * closure inside the function is its own scope.
 *
 * What it leaves alone and reports: an `execute()` whose return value is used, a fetch with arguments
 * (a fetch mode), a fetch with no `execute()` before it in its scope, and a result variable name that
 * the scope already uses. Verify the result — lint, and run what the code computes; on UFP the scorecard
 * snapshots of old and migrated backend were byte-identical.
 */

namespace Contentfly\Tools\Migration\Dbal3Statements;

const FETCHES = array('fetchAll' => 'fetchAllAssociative', 'fetch' => 'fetchAssociative');

function main(array $argv): int
{
    $write = in_array('--write', $argv, true);
    $dirs  = array_values(array_filter(array_slice($argv, 1), static fn ($a) => $a !== '--write'));

    if (!$dirs) {
        fwrite(STDERR, "Usage: php tools/migration/dbal3-statements.php <dir>... [--write]\n");
        return 2;
    }

    $totals = array('files' => 0, 'executeQuery' => 0, 'executeStatement' => 0, 'rowCount' => 0);
    $manual = array();

    foreach ($dirs as $dir) {
        foreach (phpFiles($dir) as $file) {
            $source = (string) file_get_contents($file);
            $result = migrate($source);

            foreach ($result['manual'] as $line => $reason) {
                $manual[] = "$file:$line $reason";
            }

            if ($result['code'] === $source) {
                continue;
            }

            $totals['files']++;
            foreach (array('executeQuery', 'executeStatement', 'rowCount') as $key) {
                $totals[$key] += $result['counts'][$key];
            }

            if ($write) {
                file_put_contents($file, $result['code']);
            }
        }
    }

    printf("%s %d files: %d executeQuery, %d executeStatement, %d rowCount moved\n",
        $write ? 'Rewrote' : 'Would rewrite', $totals['files'], $totals['executeQuery'], $totals['executeStatement'], $totals['rowCount']);
    printf("%d places left for a person:\n%s", count($manual), $manual ? implode("\n", $manual) . "\n" : '');

    return $manual ? 1 : 0;
}

/**
 * @return array{code:string,counts:array{executeQuery:int,executeStatement:int,rowCount:int},manual:array<int,string>}
 */
function migrate(string $source): array
{
    $tokens = \PhpToken::tokenize($source);
    $n      = count($tokens);
    $calls  = calls($tokens);
    $scopes = scopes($tokens);
    $edits  = array();
    $manual = array();
    $counts = array('executeQuery' => 0, 'executeStatement' => 0, 'rowCount' => 0);
    $taken  = array();

    foreach ($calls as $i => $call) {
        if ($call['method'] !== 'execute') {
            continue;
        }

        $scope = scopeOf($scopes, $i);

        if (!in_array($tokens[previous($tokens, $i)]->text ?? '', array(';', '{', '}'), true)) {
            $manual[$tokens[$i]->line] = "the return value of {$call['var']}->execute() is used";
            continue;
        }

        // The uses of the same variable in the same scope, up to its next execute() or assignment.
        $uses = array();
        for ($j = $i + 1; $j < $n && $j <= $scope[1]; $j++) {
            if (!$tokens[$j]->is(T_VARIABLE) || $tokens[$j]->text !== $call['var'] || scopeOf($scopes, $j) !== $scope) {
                continue;
            }
            if ((isset($calls[$j]) && $calls[$j]['method'] === 'execute') || ($tokens[next_($tokens, $j)]->text ?? '') === '=') {
                break;
            }
            if (isset($calls[$j])) {
                $uses[] = $j;
            }
        }

        $methods = array_unique(array_map(static fn ($u) => $calls[$u]['method'], $uses));

        foreach ($uses as $u) {
            if ($calls[$u]['method'] !== 'rowCount' && ($tokens[next_($tokens, $calls[$u]['paren'])]->text ?? '') !== ')') {
                $manual[$tokens[$u]->line] = "{$call['var']}->{$calls[$u]['method']}() has arguments (a fetch mode)";
                continue 2;
            }
        }

        $fetches = array_intersect($methods, array_keys(FETCHES));

        if ($fetches) {
            $result = resultName($call['var'], 'Result', $tokens, $scope, $taken);
            if ($result === null) {
                $manual[$tokens[$i]->line] = "{$call['var']}Result is already used in this scope";
                continue;
            }
            $edits[$i]             = $result . ' = ' . $call['var'];
            $edits[$call['name']]  = 'executeQuery';
            $counts['executeQuery']++;
            foreach ($uses as $u) {
                $edits[$u] = $result;
                if (isset(FETCHES[$calls[$u]['method']])) {
                    $edits[$calls[$u]['name']] = FETCHES[$calls[$u]['method']];
                } else {
                    $counts['rowCount']++;
                }
                $calls[$u]['handled'] = true;
            }
        } elseif ($methods === array('rowCount')) {
            $affected = resultName($call['var'], 'Affected', $tokens, $scope, $taken);
            if ($affected === null) {
                $manual[$tokens[$i]->line] = "{$call['var']}Affected is already used in this scope";
                continue;
            }
            $edits[$i]            = $affected . ' = ' . $call['var'];
            $edits[$call['name']] = 'executeStatement';
            $counts['executeStatement']++;
            foreach ($uses as $u) {
                // `$x->rowCount()` → `$xAffected`: the variable stays, operator, name and brackets go.
                $edits[$u] = $affected;
                foreach (array(next_($tokens, $u), $calls[$u]['name'], $calls[$u]['paren'], next_($tokens, $calls[$u]['paren'])) as $k) {
                    $edits[$k] = '';
                }
                $counts['rowCount']++;
                $calls[$u]['handled'] = true;
            }
        } else {
            $edits[$call['name']] = 'executeStatement';
            $counts['executeStatement']++;
        }
    }

    foreach ($calls as $i => $call) {
        if ($call['method'] !== 'execute' && empty($call['handled']) && !isset($manual[$tokens[$i]->line])) {
            $manual[$tokens[$i]->line] = "{$call['var']}->{$call['method']}() without an execute() before it in its scope";
        }
    }

    $code = '';
    foreach ($tokens as $i => $token) {
        $code .= array_key_exists($i, $edits) ? $edits[$i] : $token->text;
    }

    ksort($manual);

    return array('code' => $code, 'counts' => $counts, 'manual' => $manual);
}

/**
 * `$var->execute(`, `$var->fetchAll(`, `$var->fetch(`, `$var->rowCount(` on a plain variable — not on
 * `$this->x->…`, whose type nothing here can know.
 *
 * @return array<int,array{var:string,method:string,name:int,paren:int}>
 */
function calls(array $tokens): array
{
    $calls = array();

    foreach ($tokens as $i => $token) {
        if (!$token->is(T_VARIABLE) || $token->text === '$this') {
            continue;
        }
        $arrow = next_($tokens, $i);
        $name  = next_($tokens, $arrow);
        $paren = next_($tokens, $name);
        $prev  = previous($tokens, $i);

        if (($tokens[$arrow] ?? null)?->is(T_OBJECT_OPERATOR) && ($tokens[$name] ?? null)?->is(T_STRING)
            && ($tokens[$paren]->text ?? '') === '(' && !($tokens[$prev] ?? null)?->is(T_OBJECT_OPERATOR)
            && in_array($tokens[$name]->text, array('execute', 'fetchAll', 'fetch', 'rowCount'), true)) {
            $calls[$i] = array('var' => $token->text, 'method' => $tokens[$name]->text, 'name' => $name, 'paren' => $paren);
        }
    }

    return $calls;
}

/**
 * Token ranges of function and closure bodies, innermost last. Arrow functions have no body braces and
 * belong to their enclosing scope.
 *
 * @return list<array{0:int,1:int,2:int}>
 */
function scopes(array $tokens): array
{
    $scopes = array(array(0, count($tokens) - 1, 0));
    $n      = count($tokens);

    foreach ($tokens as $i => $token) {
        if (!$token->is(T_FUNCTION)) {
            continue;
        }

        $depth = 0;
        for ($j = $i + 1; $j < $n; $j++) {
            $text = $tokens[$j]->text;
            if ($text === '(') {
                $depth++;
            } elseif ($text === ')') {
                $depth--;
            } elseif ($depth === 0 && $text === ';') {
                continue 2;                       // abstract or interface method
            } elseif ($depth === 0 && $text === '{') {
                break;
            }
        }

        $open  = $j;
        $level = 0;
        for ($k = $open; $k < $n; $k++) {
            $text = $tokens[$k]->text;
            if ($text === '{' || $tokens[$k]->is(T_CURLY_OPEN) || $tokens[$k]->is(T_DOLLAR_OPEN_CURLY_BRACES)) {
                $level++;
            } elseif ($text === '}') {
                if (--$level === 0) {
                    break;
                }
            }
        }

        // [body open, body close, `function` keyword — where the parameters start]
        $scopes[] = array($open, $k, $i);
    }

    return $scopes;
}

/** @return array{0:int,1:int,2:int} the innermost scope containing the token */
function scopeOf(array $scopes, int $index): array
{
    $best = $scopes[0];

    foreach ($scopes as $scope) {
        if ($scope[0] <= $index && $index <= $scope[1] && $scope[0] >= $best[0]) {
            $best = $scope;
        }
    }

    return $best;
}

function resultName(string $variable, string $suffix, array $tokens, array $scope, array &$taken): ?string
{
    $name = $variable . $suffix;
    $key  = $scope[0] . ':' . $name;

    if (!isset($taken[$key])) {
        // From the `function` keyword on: a parameter of that name would be overwritten silently.
        for ($i = $scope[2]; $i <= $scope[1]; $i++) {
            if ($tokens[$i]->is(T_VARIABLE) && $tokens[$i]->text === $name) {
                return null;
            }
        }
        $taken[$key] = true;
    }

    return $name;
}

function next_(array $tokens, int $i): int
{
    for ($j = $i + 1, $n = count($tokens); $j < $n; $j++) {
        if (!$tokens[$j]->isIgnorable()) {
            return $j;
        }
    }

    return $n;
}

function previous(array $tokens, int $i): int
{
    for ($j = $i - 1; $j >= 0; $j--) {
        if (!$tokens[$j]->isIgnorable()) {
            return $j;
        }
    }

    return -1;
}

/** @return list<string> */
function phpFiles(string $path): array
{
    if (is_file($path)) {
        return array($path);
    }

    $files = array();
    foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $entry) {
        if ($entry->getExtension() === 'php' && !str_contains($entry->getPathname(), '/vendor/')) {
            $files[] = $entry->getPathname();
        }
    }

    sort($files);

    return $files;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(main($argv));
}
