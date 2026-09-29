<?php
namespace Tests\Integration\Api;

use Tests\Integration\ExtraServer;
use Tests\Integration\IntegrationTestCase;

/**
 * The configuration block is chosen at start, and a wrong name stops the start (`015-000-0017`).
 *
 * The decision itself is measured in `Tests\Unit\Config\ConfigBlockChoiceTest`, where every
 * combination of blocks can be built. What can only be measured here is that the decision is
 * really taken during the boot of a running instance, and that an instance whose deployment names
 * a block that does not exist does not come up serving the `default` one instead.
 *
 * ITS OWN SERVER, with `CONTENTFLY_CONFIG` set — the suite's server runs without it, which is the
 * ordinary case for the shipped template: one block, nothing to decide.
 */
class ConfigBlockApiTest extends IntegrationTestCase
{
    /**
     * A name that matches no block: every request fails, none is answered out of `default`.
     *
     * Before `015-000-0017` there was no such thing as a wrong name — an unknown one silently
     * became `default`, which is exactly what a caller used to be able to trigger with a `Host`
     * header.
     */
    public function testAnInstanceNamingAnUnknownBlockDoesNotServe(): void
    {
        $this->requireIntegrationServer();

        $server = ExtraServer::start(array('CONTENTFLY_CONFIG' => 'does-not-exist.example.invalid'));

        try {
            [$status, $body] = $this->onServer($server->url(), fn () => $this->get('/api/config'));

            $this->assertSame(500, $status, 'the start fails instead of serving the default block');
            $this->assertStringNotContainsString(
                '"version"',
                json_encode($body),
                'and no configuration is handed out'
            );
        } finally {
            $this->stopQuietly($server);
        }
    }

    /** Naming the block that does exist is the normal case and changes nothing. */
    public function testAnInstanceNamingTheDefaultBlockServesAsBefore(): void
    {
        $this->requireIntegrationServer();

        $server = ExtraServer::start(array('CONTENTFLY_CONFIG' => 'default'));

        try {
            [$status] = $this->onServer($server->url(), fn () => $this->get('/api/config'));

            $this->assertSame(200, $status);
        } finally {
            $server->stop();
        }
    }

    private function requireIntegrationServer(): void
    {
        if (self::$baseUrl === null) {
            $this->markTestSkipped('No integration server configured.');
        }
    }

    /**
     * Stops a server that was MEANT to fail.
     *
     * `ExtraServer::stop()` turns anything the server logged into a failure — which is right for
     * every other use and wrong for this one: the aborted start is the thing being measured, and
     * it is supposed to leave a trace in the log.
     */
    private function stopQuietly(ExtraServer $server): void
    {
        try {
            $server->stop();
        } catch (\RuntimeException $e) {
            if (!str_contains($e->getMessage(), 'CONTENTFLY_CONFIG')) {
                throw $e;
            }
        }
    }
}
