<?php
namespace Tests\Unit\Migration;

use PHPUnit\Framework\TestCase;

use function Contentfly\Tools\Migration\RecordApi\compareRecordings;
use function Contentfly\Tools\Migration\RecordApi\diff;
use function Contentfly\Tools\Migration\RecordApi\normalize;
use function Contentfly\Tools\Migration\RecordApi\record;
use function Contentfly\Tools\Migration\RecordApi\substitute;

require_once CONTENTFLY_PROJECT_DIR . '/tools/migration/record-api.php';

/**
 * The recording tool for existing projects (007-005-0002).
 *
 * A recording is only a yardstick if two runs against the same backend match. The tests check the
 * three parts that decide that — masking, placeholders, comparison — and one real run against a
 * small server started here, so the HTTP side is not assumed either.
 */
class RecordApiToolTest extends TestCase
{
    private string $scratch = '';

    /** @var resource|null */
    private $server = null;

    protected function setUp(): void
    {
        $this->scratch = sys_get_temp_dir() . '/contentfly-record-' . bin2hex(random_bytes(6));
        mkdir($this->scratch, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->scratch, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->scratch);
    }

    public function testVolatileValuesAndTokensAreMaskedAtAnyDepth(): void
    {
        $token = str_repeat('ab', 64);

        $this->assertSame(
            array('message' => 'ok', 'token' => '<volatile>', 'user' => array('Created' => '<volatile>', 'alias' => 'admin'),
                  'note' => 'use <token> next time'),
            normalize(array('message' => 'ok', 'token' => $token, 'user' => array('Created' => '2026-09-15', 'alias' => 'admin'),
                            'note' => "use $token next time"), array('token', 'created'))
        );
    }

    public function testPlaceholdersComeFromTheVarsAndUnknownOnesStayVisible(): void
    {
        $vars = array('users' => array('admin' => array('pass' => 's3cret')));

        $this->assertSame(
            array('alias' => 'admin', 'pass' => 's3cret', 'other' => '{{users.editor.pass}}', 'count' => 5),
            substitute(array('alias' => 'admin', 'pass' => '{{users.admin.pass}}', 'other' => '{{users.editor.pass}}', 'count' => 5), $vars),
            'An unknown placeholder fails visibly instead of sending an empty password'
        );
    }

    public function testTheDiffNamesThePathOfEveryDifference(): void
    {
        $this->assertSame(array(), diff(array('a' => array(1, 2)), array('a' => array(1, 2)), 'body'));
        $this->assertSame(
            array('body.message: "Access denied" -> "Invalid token."', 'body.0: only in A', 'body.status: only in B'),
            diff(array('message' => 'Access denied', 0 => 401), array('message' => 'Invalid token.', 'status' => 401), 'body')
        );
    }

    /**
     * One real run: a session logs in, its token is sent in the configured header, a volatile key is
     * masked, and two recordings of an unchanged server compare as identical.
     */
    public function testARecordingOfAnUnchangedServerComparesAsIdentical(): void
    {
        $baseUrl  = $this->startServer();
        $scenario = array(
            'baseUrl'     => $baseUrl,
            'tokenHeader' => 'X-Probe-Token',
            'volatile'    => array('now'),
            'sessions'    => array('admin' => array('login' => array('path' => '/login', 'json' => array('pass' => '{{pass}}')), 'tokenPath' => 'token')),
            'requests'    => array(
                array('name' => 'public', 'method' => 'GET', 'path' => '/public'),
                array('name' => 'private as admin', 'method' => 'POST', 'path' => '/private', 'session' => 'admin', 'json' => array('q' => 1)),
                array('name' => 'private without token', 'method' => 'POST', 'path' => '/private', 'json' => array('q' => 1)),
            ),
        );

        $summaryA = record($scenario, array('pass' => 'right'), $this->scratch . '/a');
        record($scenario, array('pass' => 'right'), $this->scratch . '/b');

        $this->assertSame(array('public' => '200', 'private as admin' => '200', 'private without token' => '401'), $summaryA);

        $private = json_decode((string) file_get_contents($this->scratch . '/a/private-as-admin.json'), true);
        $this->assertSame(array('seen' => 'token-of-admin', 'now' => '<volatile>'), $private['body'],
            'The session token arrived in the configured header; the changing value is masked');

        $result = compareRecordings($this->scratch . '/a', $this->scratch . '/b');
        $this->assertCount(3, $result['identical']);
        $this->assertSame(array(), $result['differing']);
    }

    public function testAChangedAnswerIsReportedWithItsPath(): void
    {
        foreach (array('a' => 'Access denied', 'b' => 'Invalid token.') as $dir => $message) {
            mkdir($this->scratch . '/' . $dir);
            file_put_contents($this->scratch . "/$dir/expired.json", json_encode(array(
                'name' => 'expired', 'status' => 401, 'contentType' => 'application/json', 'body' => array('message' => $message),
            )));
        }

        $result = compareRecordings($this->scratch . '/a', $this->scratch . '/b');

        $this->assertSame(array('expired' => array('body.message: "Access denied" -> "Invalid token."')), $result['differing']);
    }

    private function startServer(): string
    {
        file_put_contents($this->scratch . '/router.php', <<<'PHP'
<?php
header('Content-Type: application/json');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$body = json_decode((string) file_get_contents('php://input'), true) ?: array();
if ($path === '/login') {
    echo json_encode(($body['pass'] ?? '') === 'right' ? array('token' => 'token-of-admin') : array('message' => 'no'));
} elseif ($path === '/public') {
    echo json_encode(array('ok' => true));
} elseif ($path === '/private') {
    $token = $_SERVER['HTTP_X_PROBE_TOKEN'] ?? null;
    if ($token === null) { http_response_code(401); echo json_encode(array('message' => 'denied')); return; }
    echo json_encode(array('seen' => $token, 'now' => microtime(true)));
}
PHP);

        $socket  = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        $this->server = proc_open(
            array(PHP_BINARY, '-S', $address, $this->scratch . '/router.php'),
            array(0 => array('pipe', 'r'), 1 => array('file', '/dev/null', 'w'), 2 => array('file', '/dev/null', 'w')),
            $pipes
        );

        for ($i = 0; $i < 100; $i++) {
            $connection = @fsockopen('127.0.0.1', (int) substr($address, strrpos($address, ':') + 1));
            if ($connection) {
                fclose($connection);
                return 'http://' . $address;
            }
            usleep(50000);
        }

        $this->fail('The probe server did not start');
    }
}
