<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards that the code base stays English (014-001-0005).
 *
 * The old Contentfly was English throughout. During the work on version 2, German class names,
 * methods, messages and comments crept in, producing a mix of English and German between existing
 * and new files. Epic 014 removes it area by area. A one-off search only protects the moment; this
 * test keeps the state.
 *
 * ── What is checked ───────────────────────────────────────────────────────────────────
 *
 * Every line of every file under `PATHS` — identifiers, strings and comments alike. A line is
 * reported when it contains
 *
 * - an umlaut or `ß`,
 * - one of `WORDS` as a whole word (German words that are not also English words — `die`, `den`,
 *   `dies`, `hat` or `will` are left out for that reason), or
 * - one of `STEMS` anywhere, case-insensitively (German word parts that show up inside compound
 *   identifiers such as `Anmeldebremse`), or
 * - one of `VERB_PREFIXES` followed by an upper-case letter (camelCase identifiers such as
 *   `istRefreshToken()`, which the word rule cannot see because there is no word boundary).
 *
 * ── The path list grows with the epic ─────────────────────────────────────────────────
 *
 * Each story of epic 014 adds the paths it has translated. Story 014-006 ends with the whole tree.
 * A path that does not exist makes the test fail — a guard over a path that is gone sees nothing.
 *
 * This file itself is never scanned: it has to contain the German word lists it looks for.
 *
 * ── Exceptions check themselves ───────────────────────────────────────────────────────
 *
 * `EXCEPTIONS` names German text that is allowed to stay for now, each with a reason — mostly names
 * that a later story renames together with their class. An exception that no longer matches
 * anything makes the test fail, so the list shrinks as the renames land instead of rotting.
 */
class EnglishOnlyTest extends TestCase
{
    /** @var array<int,string> Paths relative to the project root; directories are scanned recursively. */
    private const PATHS = array(
        'index.php',
        'bin',
        'phpunit.xml.dist',
        'lib/contentfly/bootstrap.php',
        'lib/contentfly/bootstrap-web.php',
        'lib/contentfly/Classes/Kernel',
        'lib/contentfly/Classes/Metadata',
        'lib/contentfly/Classes/Security',
        'lib/contentfly/Controller/AuthController.php',
        'tests/bootstrap.php',
    );

    /** File extensions that are scanned inside directories. */
    private const EXTENSIONS = array('php', 'xml', 'dist', 'md', 'json', 'sh', 'yml', 'twig');

    /** German words that are not also English words, matched as whole words. */
    private const WORDS = array(
        'aber', 'auch', 'auf', 'aus', 'bei', 'beim', 'bis', 'bitte', 'dann', 'das', 'dass', 'dem',
        'der', 'des', 'diese', 'dieser', 'dieses', 'doch', 'durch', 'ein', 'eine',
        'einem', 'einen', 'einer', 'eines', 'fuer', 'gegen', 'genau', 'gibt', 'haben',
        'hier', 'ist', 'jetzt', 'kann', 'kein', 'keine', 'keinen', 'mit', 'muss', 'nach', 'nicht',
        'noch', 'nur', 'oder', 'ohne', 'schon', 'sich', 'sie', 'sind', 'soll', 'ueber', 'und',
        'unter', 'vom', 'von', 'vor', 'waere', 'warum', 'weil', 'wenn', 'werden', 'wie', 'wird',
        'wurde', 'zum', 'zur', 'zwischen', 'siehe', 'steht', 'liegt', 'Datei', 'Meldung', 'Zeile',
    );

    /** German word parts that appear inside compound identifiers, matched case-insensitively. */
    private const STEMS = array(
        'anmeld', 'anbieter', 'benutzer', 'bereitstell', 'bremse', 'eintrag', 'erzeug', 'fehler',
        'fremd', 'gruppe', 'kennung', 'lader', 'pfad', 'pruef', 'quelle', 'reihenfolge',
        'sammlung', 'schluessel', 'treiber', 'ueberschneid', 'umgebung', 'verschluessel',
        'vertraut', 'verzeichnis', 'vorlage', 'waechter', 'zugang', 'abgleich', 'einstieg',
        'anwendung', 'absicherung', 'keinesilex', 'paketmanifest', 'letzt', 'kette',
        'hashen', 'zweck', 'klartext', 'brauchtneu', 'passwort', 'sperren', 'faellt',
    );

    /** German verbs that start a camelCase identifier such as `istRefreshToken()`. */
    private const VERB_PREFIXES = array('ist', 'sind', 'wird', 'gibt', 'kann', 'darf', 'muss', 'soll', 'braucht');

    /**
     * German text allowed to stay for now: [path, exact text, reason].
     *
     * The text is removed from the file's lines before scanning. Every entry must still occur in
     * its file — see testEveryExceptionIsStillNeeded().
     *
     * @var array<int,array{0:string,1:string,2:string}>
     */
    private const EXCEPTIONS = array(
        array('lib/contentfly/bootstrap.php', 'ProviderAbgleichCommand', 'renamed in 014-003'),
        array('lib/contentfly/Classes/Security/UserExistenceCheck.php', 'appcms:provider:abgleich', 'renamed in 014-003'),
        array('lib/contentfly/Classes/Security/FieldEncryption.php', 'testEineManipulationFaelltAuf', 'renamed in 014-005'),
        array('lib/contentfly/bootstrap.php', 'EntityManagerFactory::erzeugen', 'renamed in 014-003'),
        array('lib/contentfly/bootstrap.php', 'AutoloaderUeberschneidungTest', 'renamed in 014-005'),
        array('lib/contentfly/bootstrap.php', 'KeineSilexTypenTest', 'renamed in 014-005'),
        array('lib/contentfly/bootstrap-web.php', 'FehlerantwortApiTest', 'renamed in 014-005'),
        array('lib/contentfly/Classes/Kernel/Application.php', 'FehlerantwortApiTest', 'renamed in 014-005'),
    );

    public function testTheCheckedPathsContainNoGerman(): void
    {
        $findings = array();

        foreach ($this->files() as $relative => $absolute) {
            foreach ($this->linesWithoutExceptions($relative, $absolute) as $number => $line) {
                $hit = $this->germanIn($line);

                if ($hit !== null) {
                    $findings[] = sprintf('%s:%d  [%s]  %s', $relative, $number + 1, $hit, trim($line));
                }
            }
        }

        $this->assertSame(array(), $findings, implode("\n", array_merge(
            array('German text found in paths that epic 014 has already translated:', ''),
            $findings,
            array('', 'Translate it. If it is a name renamed by a later story, add it to EXCEPTIONS with that story.')
        )));
    }

    public function testEveryCheckedPathExists(): void
    {
        $missing = array();

        foreach (self::PATHS as $path) {
            if (!file_exists($this->root() . '/' . $path)) {
                $missing[] = $path;
            }
        }

        $this->assertSame(array(), $missing, "These paths in PATHS do not exist — a guard over them sees nothing:\n" . implode("\n", $missing));
    }

    public function testEveryExceptionIsStillNeeded(): void
    {
        $stale = array();

        foreach (self::EXCEPTIONS as [$path, $text, $reason]) {
            $file = $this->root() . '/' . $path;

            if (!is_file($file) || !str_contains((string) file_get_contents($file), $text)) {
                $stale[] = sprintf('%s — "%s" (%s)', $path, $text, $reason);
                continue;
            }

            if ($this->germanIn($text) === null) {
                $stale[] = sprintf('%s — "%s" is not detected as German, so the exception is not needed', $path, $text);
            }
        }

        $this->assertSame(array(), $stale, "These exceptions no longer match anything and must be removed from EXCEPTIONS:\n" . implode("\n", $stale));
    }

    /**
     * The detector itself, checked against known German and known English.
     *
     * A guard that detects nothing is green forever. These cases prove that each of the three
     * rules fires, and that ordinary English text passes.
     */
    public function testTheDetectorRecognisesGermanAndLetsEnglishPass(): void
    {
        $this->assertNotNull($this->germanIn('// Die Datei fehlt'), 'a German comment');
        $this->assertNotNull($this->germanIn('throw new \RuntimeException("Zugriff verweigert, bitte anmelden");'), 'a German message');
        $this->assertNotNull($this->germanIn('$app[\'loginbremse\'] = new Anmeldebremse();'), 'a German compound identifier');
        $this->assertNotNull($this->germanIn('// Größe'), 'an umlaut');
        $this->assertNotNull($this->germanIn('if ($row->istRefreshToken()) {'), 'a German verb prefix in camelCase');
        $this->assertNotNull($this->germanIn('$token->getKlartext();'), 'a German word inside a getter');

        $this->assertNull($this->germanIn('// The container does not know "%s".'), 'an English message');
        $this->assertNull($this->germanIn('public function routes(): RouteCollection'), 'an English identifier');
        $this->assertNull($this->germanIn('if ($user->isActive() && $this->hasGroup()) {'), 'English verb prefixes');
        $this->assertNull($this->germanIn(' * See an_project/docs/breaking-changes.md (007-001-0002).'), 'references to docs and work items');
    }

    /** Returns the rule that matched, or null. */
    private function germanIn(string $line): ?string
    {
        if (preg_match('/[äöüÄÖÜß]/u', $line) === 1) {
            return 'umlaut';
        }

        if (preg_match('/\b(' . implode('|', self::WORDS) . ')\b/', $line, $match) === 1) {
            return 'word "' . $match[1] . '"';
        }

        if (preg_match('/\b(' . implode('|', self::VERB_PREFIXES) . ')[A-Z]\w*/', $line, $match) === 1) {
            return 'verb prefix "' . $match[0] . '"';
        }

        $lower = strtolower($line);

        foreach (self::STEMS as $stem) {
            if (str_contains($lower, $stem)) {
                return 'stem "' . $stem . '"';
            }
        }

        return null;
    }

    /** @return array<int,string> */
    private function linesWithoutExceptions(string $relative, string $absolute): array
    {
        $content = (string) file_get_contents($absolute);

        foreach (self::EXCEPTIONS as [$path, $text]) {
            if ($path === $relative) {
                $content = str_replace($text, '', $content);
            }
        }

        return explode("\n", $content);
    }

    /** @return array<string,string> relative path → absolute path */
    private function files(): array
    {
        $files = array();

        foreach (self::PATHS as $path) {
            $absolute = $this->root() . '/' . $path;

            if (is_file($absolute)) {
                $files[$path] = $absolute;
                continue;
            }

            if (!is_dir($absolute)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $entry) {
                if ($entry instanceof \SplFileInfo
                    && in_array($entry->getExtension(), self::EXTENSIONS, true)
                    && $entry->getPathname() !== __FILE__) {
                    $files[substr($entry->getPathname(), strlen($this->root()) + 1)] = $entry->getPathname();
                }
            }
        }

        $this->assertNotEmpty($files, 'PATHS resolves to no file at all — then this test checks nothing.');

        return $files;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
