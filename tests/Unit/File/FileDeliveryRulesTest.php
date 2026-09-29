<?php
namespace Tests\Unit\File;

use PHPUnit\Framework\TestCase;

/**
 * The rules Apache applies to `data/files/` (`015-000-0020`).
 *
 * ── Why this is a source test, and said plainly rather than dressed up ───────────────────
 *
 * The second delivery path of an uploaded file does not go through the application at all: with
 * `APP_FILE_MODE = redirect`, the default, `/file/get` answers a 301 into `data/files/` and Apache
 * serves the bytes from disk. No PHP runs, so no header the application sets applies — and the
 * Content-Security-Policy from `bootstrap-web.php` does not either.
 *
 * That path can only be secured with an `.htaccess`, and it cannot be measured by this suite: the
 * suite runs against PHP's built-in server, which ignores `.htaccess` entirely. Standing up Apache
 * for one test would be a second environment to keep alive for one assertion.
 *
 * So this pins the file's content. It is weaker than a measurement and it is the strongest thing
 * available here. What it does catch is the realistic failure: somebody deletes or empties the
 * file, or narrows the pattern, without noticing what hangs on it.
 *
 * What is measured for real — the headers the application sets in readfile mode — lives in
 * `Tests\Integration\Api\FileDeliveryApiTest`.
 */
class FileDeliveryRulesTest extends TestCase
{
    private function htaccess(): string
    {
        $path = CONTENTFLY_PROJECT_DIR.'/data/files/.htaccess';

        $this->assertFileExists($path, 'data/files/.htaccess is what secures the Apache delivery path');

        return (string) file_get_contents($path);
    }

    public function testNosniffAppliesToEverythingInTheDirectory(): void
    {
        $rules = $this->htaccess();

        $this->assertMatchesRegularExpression(
            '/Header\s+always\s+set\s+X-Content-Type-Options\s+"?nosniff/i',
            $rules,
            'Without nosniff a browser may read a .txt beginning with <html> as a document'
        );
    }

    public function testMarkupIsPushedIntoADownload(): void
    {
        $rules = $this->htaccess();

        $this->assertMatchesRegularExpression(
            '/Header\s+always\s+set\s+Content-Disposition\s+"?attachment/i',
            $rules
        );
    }

    /**
     * Every type a browser parses as a document has to be in the pattern.
     *
     * SVG is the one usually forgotten: it is an image by name and markup by nature, and it
     * carries script.
     */
    public function testEveryDocumentTypeIsCovered(): void
    {
        $rules = $this->htaccess();

        if (!preg_match('/<FilesMatch\s+"([^"]+)">/i', $rules, $match)) {
            $this->fail('No FilesMatch block — nothing selects the markup types');
        }

        $pattern = '/'.$match[1].'/';

        foreach (array('a.html', 'a.htm', 'a.xhtml', 'a.xht', 'a.shtml', 'a.svg', 'a.svgz', 'a.xml', 'a.xsl') as $name) {
            $this->assertMatchesRegularExpression($pattern, $name, $name.' must be covered');
        }
    }

    /** And the third acceptance criterion: images and PDFs are NOT in it. */
    public function testImagesAndPdfsAreNotPushedIntoADownload(): void
    {
        $rules = $this->htaccess();

        preg_match('/<FilesMatch\s+"([^"]+)">/i', $rules, $match);
        $pattern = '/'.$match[1].'/';

        foreach (array('a.jpg', 'a.jpeg', 'a.png', 'a.gif', 'a.webp', 'a.pdf') as $name) {
            $this->assertDoesNotMatchRegularExpression(
                $pattern,
                $name,
                $name.' stays inline — showing a stored image in a tab is the ordinary use of a file store'
            );
        }
    }

    /**
     * A second layer for a server without `mod_headers`.
     *
     * Both `Header` directives sit inside `<IfModule mod_headers.c>`, so without the module they
     * are silently skipped — and silence is the worst outcome for a safety rule. `ForceType`
     * needs no module.
     */
    public function testThereIsAFallbackWithoutModHeaders(): void
    {
        $this->assertMatchesRegularExpression(
            '/ForceType\s+application\/octet-stream/i',
            $this->htaccess()
        );
    }
}
