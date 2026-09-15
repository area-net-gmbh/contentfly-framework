<?php
/**
 * Records what a Contentfly backend answers, and compares two recordings (007-005-0002).
 *
 * Phase 8 of the migration guide asks whether the clients notice the migration. Without a record of
 * what the OLD backend answered there is nothing to compare against — an existing project rarely has
 * tests that would say it. This script takes that record before anything is changed and replays the
 * same calls against the migrated backend afterwards.
 *
 *     php tools/migration/record-api.php record  <scenario.json> <out-dir> [--vars=<vars.json>] [--base-url=<url>]
 *     php tools/migration/record-api.php compare <recording-a> <recording-b>
 *
 * ── The scenario ──────────────────────────────────────────────────────────────────────────
 *
 *     {
 *       "baseUrl": "http://localhost:55111",
 *       "tokenHeader": "APPCMS-Token",
 *       "volatile": ["token", "created", "modified"],
 *       "sessions": {
 *         "admin": { "login": { "path": "/auth/login", "json": { "alias": "{{admin.alias}}", "pass": "{{admin.pass}}" } },
 *                    "tokenPath": "token" },
 *         "sso":   { "token": "{{ssoToken}}" }
 *       },
 *       "requests": [
 *         { "name": "config", "method": "GET", "path": "/api/config" },
 *         { "name": "list-as-admin", "path": "/api/list", "session": "admin", "json": { "entity": "PIM\\Tag" } }
 *       ]
 *     }
 *
 * A request may add its own `volatile` keys — a login that creates a user each time, say, answers
 * with a new id every run.
 *
 * `{{a.b}}` is replaced from the vars file — passwords and tokens stay out of the scenario, which can
 * then be kept with the project. A session logs in once, lazily, on its first request.
 *
 * ── What is compared ──────────────────────────────────────────────────────────────────────
 *
 * Status code, content type and body. Values under a key listed in `volatile` (at any depth) and
 * every 128-hex-character token are replaced by a marker before anything is written — they change
 * between two runs against the same data, so comparing them would only produce noise.
 *
 * Read-only: the script only sends what the scenario lists. Whether a request writes is the
 * scenario author's decision.
 */

namespace Contentfly\Tools\Migration\RecordApi;

const MARKER_VOLATILE = '<volatile>';
const MARKER_TOKEN    = '<token>';

function main(array $argv): int
{
    $command = $argv[1] ?? '';
    $options = array();
    $args    = array();

    foreach (array_slice($argv, 2) as $arg) {
        if (preg_match('/^--([a-z-]+)=(.*)$/', $arg, $m)) {
            $options[$m[1]] = $m[2];
        } else {
            $args[] = $arg;
        }
    }

    if ($command === 'record' && count($args) === 2) {
        $scenario = json_decode((string) file_get_contents($args[0]), true);
        $vars     = isset($options['vars']) ? json_decode((string) file_get_contents($options['vars']), true) : array();

        if (!is_array($scenario) || !is_array($vars)) {
            fwrite(STDERR, "Scenario or vars file is not valid JSON.\n");
            return 2;
        }

        if (isset($options['base-url'])) {
            $scenario['baseUrl'] = $options['base-url'];
        }

        $summary = record($scenario, $vars, $args[1]);
        foreach ($summary as $name => $status) {
            printf("%-60s %s\n", $name, $status);
        }

        return 0;
    }

    if ($command === 'compare' && count($args) === 2) {
        $result = compareRecordings($args[0], $args[1]);
        echo renderComparison($result);

        return $result['differing'] || $result['onlyA'] || $result['onlyB'] ? 1 : 0;
    }

    fwrite(STDERR, "Usage:\n"
        . "  php tools/migration/record-api.php record  <scenario.json> <out-dir> [--vars=<vars.json>] [--base-url=<url>]\n"
        . "  php tools/migration/record-api.php compare <recording-a> <recording-b>\n");

    return 2;
}

/**
 * Runs every request of the scenario and writes one JSON file per request plus `index.json`.
 *
 * @return array<string,string> request name => status summary
 */
function record(array $scenario, array $vars, string $outDir): array
{
    if (!is_dir($outDir) && !mkdir($outDir, 0777, true) && !is_dir($outDir)) {
        throw new \RuntimeException("Cannot create $outDir");
    }

    $baseUrl     = rtrim((string) ($scenario['baseUrl'] ?? ''), '/');
    $tokenHeader = (string) ($scenario['tokenHeader'] ?? 'APPCMS-Token');
    $volatile    = array_map('strtolower', (array) ($scenario['volatile'] ?? array()));
    $sessions    = substitute((array) ($scenario['sessions'] ?? array()), $vars);
    $tokens      = array();
    $summary     = array();

    foreach ((array) ($scenario['requests'] ?? array()) as $request) {
        $name    = (string) $request['name'];
        $session = $request['session'] ?? null;
        $headers = array();

        if ($session !== null) {
            if (!array_key_exists($session, $tokens)) {
                $tokens[$session] = sessionToken((array) ($sessions[$session] ?? array()), $baseUrl);
            }

            if ($tokens[$session] === null) {
                $summary[$name] = 'skipped: no token for session ' . $session;
                continue;
            }

            $headers[] = $tokenHeader . ': ' . $tokens[$session];
        }

        $response = send(
            strtoupper((string) ($request['method'] ?? 'POST')),
            $baseUrl . substitute((string) $request['path'], $vars),
            isset($request['json']) ? substitute($request['json'], $vars) : null,
            $headers
        );

        $entry = array(
            'name'        => $name,
            'method'      => strtoupper((string) ($request['method'] ?? 'POST')),
            'path'        => (string) $request['path'],
            'session'     => $session,
            'status'      => $response['status'],
            'contentType' => $response['contentType'],
        );

        $requestVolatile = array_merge($volatile, array_map('strtolower', (array) ($request['volatile'] ?? array())));

        $decoded = json_decode($response['body'], true);
        if ($response['body'] !== '' && json_last_error() === JSON_ERROR_NONE) {
            $entry['body'] = normalize($decoded, $requestVolatile);
        } else {
            $entry['bodyText'] = normalize(mb_substr($response['body'], 0, 2000), $requestVolatile);
        }

        file_put_contents(
            $outDir . '/' . fileName($name),
            json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
        );

        $summary[$name] = (string) $response['status'];
    }

    file_put_contents(
        $outDir . '/index.json',
        json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
    );

    return $summary;
}

/** Logs in for a session, or takes a token given directly. Null when no token could be obtained. */
function sessionToken(array $session, string $baseUrl): ?string
{
    if (isset($session['token']) && is_string($session['token']) && $session['token'] !== '') {
        return $session['token'];
    }

    if (!isset($session['login'])) {
        return null;
    }

    $login    = $session['login'];
    $response = send(strtoupper((string) ($login['method'] ?? 'POST')), $baseUrl . $login['path'], $login['json'] ?? null, array());
    $body     = json_decode($response['body'], true);

    $value = $body;
    foreach (explode('.', (string) ($session['tokenPath'] ?? 'token')) as $key) {
        $value = is_array($value) && array_key_exists($key, $value) ? $value[$key] : null;
    }

    return is_string($value) && $value !== '' ? $value : null;
}

/** @return array{status:int,contentType:string,body:string} */
function send(string $method, string $url, mixed $json, array $headers): array
{
    $ch = curl_init($url);
    $options = array(
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => $headers,
    );

    if ($json !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($json);
        $options[CURLOPT_HTTPHEADER] = array_merge($headers, array('Content-Type: application/json'));
    }

    curl_setopt_array($ch, $options);
    $body        = (string) curl_exec($ch);
    $status      = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    return array('status' => $status, 'contentType' => preg_replace('/;.*$/', '', $contentType), 'body' => $body);
}

/**
 * Replaces `{{a.b}}` in every string of $value from $vars. An unknown placeholder stays as it is — a
 * login with a literal `{{admin.pass}}` fails visibly instead of sending an empty password.
 */
function substitute(mixed $value, array $vars): mixed
{
    if (is_array($value)) {
        return array_map(static fn ($item) => substitute($item, $vars), $value);
    }

    if (!is_string($value)) {
        return $value;
    }

    return preg_replace_callback('/\{\{([\w.-]+)\}\}/', static function (array $m) use ($vars) {
        $current = $vars;
        foreach (explode('.', $m[1]) as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return $m[0];
            }
            $current = $current[$key];
        }

        return is_scalar($current) ? (string) $current : $m[0];
    }, $value);
}

/**
 * Masks what changes between two runs against the same data.
 *
 * @param list<string> $volatile lower-cased key names
 */
function normalize(mixed $value, array $volatile): mixed
{
    if (is_array($value)) {
        $result = array();
        foreach ($value as $key => $item) {
            $result[$key] = is_string($key) && in_array(strtolower($key), $volatile, true)
                ? MARKER_VOLATILE
                : normalize($item, $volatile);
        }

        return $result;
    }

    if (is_string($value)) {
        return preg_replace('/\b[0-9a-f]{128}\b/', MARKER_TOKEN, $value);
    }

    return $value;
}

function fileName(string $name): string
{
    return preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) . '.json';
}

/**
 * @return array{identical:list<string>,differing:array<string,list<string>>,onlyA:list<string>,onlyB:list<string>}
 */
function compareRecordings(string $dirA, string $dirB): array
{
    $load = static function (string $dir): array {
        $entries = array();
        foreach (glob(rtrim($dir, '/') . '/*.json') ?: array() as $file) {
            if (basename($file) === 'index.json') {
                continue;
            }
            $entry = json_decode((string) file_get_contents($file), true);
            if (is_array($entry) && isset($entry['name'])) {
                $entries[$entry['name']] = $entry;
            }
        }
        ksort($entries);

        return $entries;
    };

    $a = $load($dirA);
    $b = $load($dirB);

    $result = array('identical' => array(), 'differing' => array(), 'onlyA' => array(), 'onlyB' => array());

    foreach ($a as $name => $entryA) {
        if (!isset($b[$name])) {
            $result['onlyA'][] = $name;
            continue;
        }

        $differences = array();
        foreach (array('status', 'contentType') as $field) {
            if (($entryA[$field] ?? null) !== ($b[$name][$field] ?? null)) {
                $differences[] = sprintf('%s: %s -> %s', $field, json_encode($entryA[$field] ?? null), json_encode($b[$name][$field] ?? null));
            }
        }
        $differences = array_merge($differences, diff(
            $entryA['body'] ?? ($entryA['bodyText'] ?? null),
            $b[$name]['body'] ?? ($b[$name]['bodyText'] ?? null),
            'body'
        ));

        if ($differences) {
            $result['differing'][$name] = $differences;
        } else {
            $result['identical'][] = $name;
        }
    }

    $result['onlyB'] = array_values(array_diff(array_keys($b), array_keys($a)));

    return $result;
}

/**
 * The paths at which two decoded bodies differ.
 *
 * @return list<string>
 */
function diff(mixed $a, mixed $b, string $path): array
{
    if (is_array($a) && is_array($b)) {
        $differences = array();
        foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $key) {
            $child = $path . '.' . $key;
            if (!array_key_exists($key, $a)) {
                $differences[] = "$child: only in B";
            } elseif (!array_key_exists($key, $b)) {
                $differences[] = "$child: only in A";
            } else {
                $differences = array_merge($differences, diff($a[$key], $b[$key], $child));
            }
        }

        return $differences;
    }

    return $a === $b ? array() : array(sprintf('%s: %s -> %s', $path, short($a), short($b)));
}

function short(mixed $value): string
{
    $json = (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return mb_strlen($json) > 120 ? mb_substr($json, 0, 117) . '...' : $json;
}

function renderComparison(array $result): string
{
    $out = array(sprintf(
        'identical %d · differing %d · only in A %d · only in B %d',
        count($result['identical']), count($result['differing']), count($result['onlyA']), count($result['onlyB'])
    ));

    foreach ($result['differing'] as $name => $differences) {
        $out[] = '';
        $out[] = "## $name";
        foreach (array_slice($differences, 0, 25) as $difference) {
            $out[] = '  ' . $difference;
        }
        if (count($differences) > 25) {
            $out[] = sprintf('  … %d more', count($differences) - 25);
        }
    }

    foreach (array('onlyA' => 'Only in A', 'onlyB' => 'Only in B') as $key => $title) {
        if ($result[$key]) {
            $out[] = '';
            $out[] = "## $title";
            foreach ($result[$key] as $name) {
                $out[] = '  ' . $name;
            }
        }
    }

    return implode("\n", $out) . "\n";
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(main($argv));
}
