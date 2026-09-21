<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The guard against an API documentation that runs away from the API (000-000-0053).
 *
 * ## The occasion
 * The generated documentation comes from the `@api` blocks in `lib/contentfly/Controller/`. Until
 * `000-000-0053` every response example in them showed the shape from before Epic `011` —
 * `message`, `lastModified`, `dataCount`, `devmode` on the top level, one of them `"version":
 * "1.4.0"`, and one wrote `"data:"` with the colon inside the quotes: not JSON, and never had
 * been. The token headers they named (`APPMS-TOKEN`, `X-Token`) are answered with `401`.
 *
 * Nobody noticed, because nothing read them. A documentation that is wrong is worse than none:
 * it is believed.
 *
 * ## What is checked here
 * Every example tagged `{json}` is parsed — a machine reads it, not a person. Response examples
 * additionally have to be the envelope from `011-001`: `data`, `errors`, `meta` in that order, and
 * an error entry with its four fixed keys. That is the same reader as `EnvelopeApiTest`, pointed at
 * the documentation instead of the server.
 *
 * ## Why an untyped example fails too
 * An example without `{json}` would escape the parser, and the first one to be written that way
 * would be the one that is wrong. So response examples must carry the type.
 */
class ApiDocExamplesTest extends TestCase
{
    private const CONTROLLERS = __DIR__.'/../../lib/contentfly/Controller';

    /** The headers a block may name — the ones the server actually reads (`TokenSources`). */
    private const KNOWN_HEADERS = array('Authorization', 'appcms-token', 'Content-Type=application/json');

    /**
     * All `@api` doc blocks, keyed by "File.php: /route".
     *
     * @return array<string,string>
     */
    private static function blocks(): array
    {
        $blocks = array();

        foreach (glob(self::CONTROLLERS.'/*.php') as $file) {
            preg_match_all('#/\*\*.*?\*/#s', (string) file_get_contents($file), $matches);

            foreach ($matches[0] as $block) {
                if (preg_match('#@api \{(\w+)\} (\S+)#', $block, $route)) {
                    $blocks[basename($file).': '.strtoupper($route[1]).' '.$route[2]] = $block;
                }
            }
        }

        return $blocks;
    }

    /**
     * The examples of one block: tag, type, title and content with the comment frame removed.
     *
     * @return list<array{tag:string,type:?string,title:string,content:string}>
     */
    private static function examples(string $block): array
    {
        $lines    = preg_split('/\R/', $block);
        $examples = array();
        $current  = null;

        foreach ($lines as $line) {
            $text = preg_replace('#^\s*(/\*\*|\*/|\*)\s?#', '', $line);

            if (str_starts_with($text, '@api')) {
                if ($current !== null) {
                    $examples[] = $current;
                    $current    = null;
                }
                if (preg_match('#^@api(\w*Example)\s+(?:\{(\w+)\}\s*)?(.*)$#', $text, $m)) {
                    $current = array('tag' => $m[1], 'type' => $m[2] ?: null, 'title' => trim($m[3]), 'content' => '');
                }
                continue;
            }

            if ($current !== null) {
                $current['content'] .= $text."\n";
            }
        }

        if ($current !== null) {
            $examples[] = $current;
        }

        return $examples;
    }

    public function testTheControllersAreFound(): void
    {
        // Without this, a moved directory would turn every test below into a green loop over nothing.
        $this->assertGreaterThanOrEqual(20, count(self::blocks()), 'The @api blocks are found');
    }

    public function testEveryJsonExampleIsValidJson(): void
    {
        $checked = 0;

        foreach (self::blocks() as $name => $block) {
            foreach (self::examples($block) as $example) {
                if ($example['type'] !== 'json') {
                    continue;
                }

                $content = $example['content'];
                if ($example['tag'] !== 'ParamExample') {
                    $content = preg_replace('#^\s*HTTP/1\.1 \d{3}[^\n]*\n#', '', $content);
                }

                json_decode($content, true);
                $this->assertSame(JSON_ERROR_NONE, json_last_error(),
                    sprintf('%s, "%s": %s', $name, $example['title'], json_last_error_msg()));
                $checked++;
            }
        }

        $this->assertGreaterThan(30, $checked, 'Request and response examples were actually read');
    }

    public function testEveryResponseExampleIsTheEnvelope(): void
    {
        $successes = 0;
        $errors    = 0;

        foreach (self::blocks() as $name => $block) {
            foreach (self::examples($block) as $example) {
                if (!in_array($example['tag'], array('SuccessExample', 'ErrorExample'), true)) {
                    continue;
                }

                $label = sprintf('%s, "%s"', $name, $example['title']);
                $this->assertSame('json', $example['type'], $label.': a response example is typed {json}');

                $this->assertMatchesRegularExpression('#^\s*HTTP/1\.1 (\d{3}) #', $example['content'],
                    $label.': the status code stands in the status line, not in the body');
                preg_match('#^\s*HTTP/1\.1 (\d{3}) [^\n]*\n(.*)$#s', $example['content'], $parts);
                $status = (int) $parts[1];
                $body   = json_decode($parts[2], true);

                $this->assertSame(array('data', 'errors', 'meta'), array_keys($body), $label);
                $this->assertSame(array('ts', 'version', 'projectVersion', 'hash'),
                    array_slice(array_keys($body['meta']), 0, 4), $label);
                $this->assertMatchesRegularExpression('#^[2-9]\.#', $body['meta']['version'],
                    $label.': no 1.x version');

                if ($example['tag'] === 'SuccessExample') {
                    $this->assertLessThan(300, $status, $label);
                    $this->assertNull($body['errors'], $label);
                    $successes++;
                    continue;
                }

                $this->assertGreaterThanOrEqual(400, $status, $label);
                $this->assertNull($body['data'], $label);
                $this->assertNotEmpty($body['errors'], $label);
                foreach ($body['errors'] as $entry) {
                    $this->assertSame(array('code', 'detail', 'type', 'context'), array_keys($entry), $label);
                }
                $errors++;
            }
        }

        $this->assertGreaterThan(15, $successes, 'Success examples were actually read');
        $this->assertGreaterThan(0, $errors, 'At least one example shows the error form');
    }

    public function testNoBlockDescribesContentfly1(): void
    {
        foreach (self::blocks() as $name => $block) {
            $this->assertMatchesRegularExpression('#@apiVersion [2-9]\.\d+\.\d+#', $block,
                $name.': the version history of 1.x is not part of this documentation');
        }
    }

    public function testEveryHeaderIsOneTheServerReads(): void
    {
        foreach (self::blocks() as $name => $block) {
            preg_match_all('#@apiHeader \{\w+\} (\S+)#', $block, $headers);

            foreach ($headers[1] as $header) {
                $this->assertContains($header, self::KNOWN_HEADERS, $name.': unknown header');
            }
        }
    }
}
