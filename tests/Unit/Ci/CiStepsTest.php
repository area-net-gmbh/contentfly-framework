<?php
namespace Tests\Unit\Ci;

use PHPUnit\Framework\TestCase;

/**
 * The guard against a job that dies without saying why (000-000-0029).
 *
 * ## The occasion
 * During `013-005-0004` the PHP 8.4 run aborted with **exit 2 and zero lines of output**. The
 * cause was a real error and was stated verbatim in the Composer output — only the step pushed
 * it to `/dev/null`, and `set -eu` ended the script before anyone could read it. It only became
 * visible when the steps were run by hand and without redirection.
 *
 * ## What is checked here
 * Two things, and they belong together:
 *
 *  1. `tools/ci/schritt.sh` does what it promises — loud on failure, silent on success.
 *     That is the actual test: it runs a deliberately failing step and checks whether its
 *     message appears in the output.
 *  2. No step in `tools/ci/` stays silent by bypassing the function. Without this second half
 *     the first would be worthless the moment someone writes the next line with
 *     `> /dev/null` — and that is exactly how the finding came about.
 *
 * ## Why the exceptions live here and not as a comment in the script
 * So that they monitor themselves. An exception that no longer matches anything turns this run
 * **red** — the same rule the three gates from `006-005` depend on and that fired five times
 * in Epic `010`. Otherwise the list would, after the next conversion, be a permission into
 * the void that silently keeps taking effect.
 */
class CiStepsTest extends TestCase
{
    /**
     * What may be silent in `tools/ci/`, and why.
     *
     * The key is the file name, the value a list of [text fragment of the line, justification].
     * Every entry must still apply — see `testEveryExceptionIsStillNeeded()`.
     *
     * @var array<string,array<int,array{0:string,1:string}>>
     */
    private const EXCEPTIONS = array(
        'audit.sh' => array(
            array(
                'composer --version --no-ansi 2>/dev/null',
                'A query, not work: it determines WHETHER composer exists. Its failure is the '
                .'result and is handled three lines further down with its own message.',
            ),
        ),
        'prepare-test-environment.sh' => array(
            array(
                '\' 2>/dev/null; do',
                'Two wait loops ask every second whether the database or the test server already '
                .'responds. A failure is the normal case there and the termination condition — '
                .'once the deadline has passed the loop reports it, for the test server together '
                .'with the server log. It was the model for 000-000-0029.',
            ),
            array(
                '-S "$ADRESSE" tests/router.php >',
                'The test server keeps running in the background; its output belongs in a file '
                .'and not in the job log. The file is at the same time the source of the '
                .'deprecation gate from 006-005 — and the wait loop below prints it '
                .'if the server does not respond. `schritt` does not fit here: the function '
                .'waits for its command, but this command is precisely not supposed to end.',
            ),
        ),
        'paket-veroeffentlichen.sh' => array(
            array(
                'git cat-file -e "$SPLIT:composer.json" 2>/dev/null',
                'A query, not work: it determines WHETHER the split has a composer.json at its '
                .'root. The "not found" on stderr IS the answer being asked for, and the failure '
                .'is handled right below with its own message and a listing of what was found '
                .'instead. `schritt` would report the expected case as a broken step.',
            ),
            array(
                'git cat-file -e "$SPLIT:$verboten" 2>/dev/null',
                'The same query with the opposite expectation: here the file must NOT exist, so '
                .'the silent failure is the good case. Reporting it would make every correct run '
                .'print five errors.',
            ),
        ),
        'audit-ausnahmen-pruefen.sh' => array(
            array(
                'composer --version --no-ansi 2>/dev/null',
                'The same query, the same reason.',
            ),
            array(
                '2>"$FEHLERLOG"',
                'Captured, not discarded: if composer delivers no data, the check prints '
                .'the last lines of this file. Exactly that was `2>/dev/null` before.',
            ),
        ),
    );

    /**
     * The actual run-through: a step that fails on purpose.
     *
     * It writes to both channels and aborts with a code that is not 1 — otherwise the test
     * could not tell whether the code was passed through or made up.
     */
    public function testAFailedStepShowsItsOutput(): void
    {
        $result = $this->runStep(
            'schritt "Deliberate failure" sh -c \'echo HARMLESS-LINE; '
            .'echo THE-CAUSE-IS-HERE >&2; exit 3\''
        );

        $this->assertSame(3, $result['code'], 'The exit code of the step must be passed through.');

        $this->assertStringContainsString('THE-CAUSE-IS-HERE', $result['output'],
            'Exactly that is the finding: the message of the failed command must be visible.');
        $this->assertStringContainsString('HARMLESS-LINE', $result['output'],
            'stdout belongs to it too — many tools report their error there.');
        $this->assertStringContainsString('Deliberate failure', $result['output'],
            'Without the name of the step you know what went wrong, but not during what.');
        $this->assertStringContainsString('Exit-Code: 3', $result['output']);
    }

    /**
     * The other half of the promise: as long as everything goes well, it stays quiet.
     *
     * Without this test "print everything" would be a valid solution — and a pipeline that
     * prints every apt-get progress line is read by nobody. Then the full log hides the error
     * just as reliably as the empty one.
     */
    public function testASuccessfulStepStaysSilent(): void
    {
        $result = $this->runStep(
            'schritt "Quiet step" sh -c \'echo THIS-DOES-NOT-BELONG-IN-THE-JOB-LOG; exit 0\''
        );

        $this->assertSame(0, $result['code']);
        $this->assertStringNotContainsString('THIS-DOES-NOT-BELONG-IN-THE-JOB-LOG', $result['output'],
            'The redirection stays: on success the output does not belong in the job log.');
        $this->assertStringContainsString('Quiet step', $result['output'],
            'That the step ran belongs in it — otherwise the progress is not visible.');
    }

    /**
     * No new silent step that bypasses `schritt`.
     *
     * Joined lines are checked: a line with `\` at the end belongs to the next one. Otherwise
     * the continuation of a `schritt` call would count as a command of its own — and that is
     * exactly how the `--quiet` in `install-composer.sh` is written.
     */
    public function testNoStepStaysSilentBypassingTheFunction(): void
    {
        $suspicious = array();

        foreach ($this->ciScripts() as $file => $path) {
            if ($file === 'schritt.sh') {
                // The function itself redirects — that is its job.
                continue;
            }

            foreach ($this->logicalLines($path) as $number => $line) {
                if (!$this->isSilent($line) || $this->isComment($line)) {
                    continue;
                }

                if (strpos(ltrim($line), 'schritt ') === 0) {
                    continue;
                }

                if ($this->isExcepted($file, $line)) {
                    continue;
                }

                $suspicious[] = sprintf('%s:%d — %s', $file, $number, trim($line));
            }
        }

        $this->assertSame(array(), $suspicious, implode("\n", array_merge(
            array(
                'These steps suppress their output without running through tools/ci/schritt.sh.',
                'On failure the job dies silently again — the finding from 000-000-0029:',
                '',
            ),
            $suspicious,
            array(
                '',
                'Either wrap the call in `schritt "What it does" <command>`, or — if the',
                'silence is correct — add an exception with a justification to EXCEPTIONS.',
            )
        )));
    }

    /**
     * And the exception list monitors itself.
     *
     * If an entry can no longer be found in its file, it is obsolete — and an obsolete entry is
     * not a harmless leftover, but a permission that takes effect unasked the next time.
     */
    public function testEveryExceptionIsStillNeeded(): void
    {
        $scripts = $this->ciScripts();
        $dead    = array();

        foreach (self::EXCEPTIONS as $file => $entries) {
            if (!isset($scripts[$file])) {
                $dead[] = sprintf('%s — the file no longer exists.', $file);
                continue;
            }

            $content = (string) file_get_contents($scripts[$file]);

            foreach ($entries as $entry) {
                if (strpos($content, $entry[0]) === false) {
                    $dead[] = sprintf('%s — "%s" is no longer there.', $file, $entry[0]);
                }
            }
        }

        $this->assertSame(array(), $dead, implode("\n", array_merge(
            array('These exceptions no longer match anything and should be removed from EXCEPTIONS:', ''),
            $dead
        )));
    }

    /**
     * Runs a command against the sourced function and returns code and output.
     *
     * `set -eu` is intentional: it is the environment in which the finding came about. stderr
     * is redirected to stdout, because the message lands there and the test reads both
     * together — like a person looking into the job log.
     *
     * @return array{code:int,output:string}
     */
    private function runStep(string $call): array
    {
        $root = dirname(__DIR__, 3);

        $script = sprintf(
            "set -eu\n. %s/tools/ci/schritt.sh\n%s\n",
            escapeshellarg($root),
            $call
        );

        $output = array();
        $code   = 0;
        exec('sh -c ' . escapeshellarg($script) . ' 2>&1', $output, $code);

        return array('code' => $code, 'output' => implode("\n", $output));
    }

    /**
     * @return array<string,string> File name → full path
     */
    private function ciScripts(): array
    {
        $scripts = array();

        foreach ((array) glob(dirname(__DIR__, 3) . '/tools/ci/*.sh') as $path) {
            $scripts[basename((string) $path)] = (string) $path;
        }

        $this->assertNotEmpty($scripts, 'There is no script in tools/ci/ — then this test checks nothing.');

        return $scripts;
    }

    /**
     * Lines of a file, with continuations joined.
     *
     * @return array<int,string> Line number of the start → joined line
     */
    private function logicalLines(string $path): array
    {
        $raw     = explode("\n", (string) file_get_contents($path));
        $lines   = array();
        $buffer  = '';
        $start   = 0;

        foreach ($raw as $i => $line) {
            if ($buffer === '') {
                $start = $i + 1;
            }

            $buffer .= ($buffer === '' ? '' : ' ') . rtrim($line);

            if (substr(rtrim($line), -1) === '\\') {
                $buffer = rtrim($buffer, '\\');
                continue;
            }

            $lines[$start] = $buffer;
            $buffer        = '';
        }

        return $lines;
    }

    /**
     * Is this line silent?
     *
     * The fourth and fifth rules are the most important ones, and they were only added when
     * re-measuring: the step that cost 013-005-0004 did NOT write to /dev/null, but to a log
     * file — `composer install … > /tmp/i.log 2>&1`. A detector that only knows /dev/null
     * would have let exactly this case through.
     */
    private function isSilent(string $line): bool
    {
        foreach (array('/dev/null', '--quiet', '--silent') as $pattern) {
            if (strpos($line, $pattern) !== false) {
                return true;
            }
        }

        // `-qq` only as a standalone argument, so that no word containing this sequence matches.
        if (preg_match('/(^|\s)-qq(\s|$)/', $line) === 1) {
            return true;
        }

        // stderr into the same redirection as stdout …
        if (strpos($line, '2>&1') !== false) {
            return true;
        }

        // … or stderr alone into a file. `>&2` is the opposite and does not fall under this.
        return preg_match('/2>\s*["\'$\/]/', $line) === 1;
    }

    private function isComment(string $line): bool
    {
        return strpos(ltrim($line), '#') === 0;
    }

    private function isExcepted(string $file, string $line): bool
    {
        foreach (self::EXCEPTIONS[$file] ?? array() as $entry) {
            if (strpos($line, $entry[0]) !== false) {
                return true;
            }
        }

        return false;
    }
}
