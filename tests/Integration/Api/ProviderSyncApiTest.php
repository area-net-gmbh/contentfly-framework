<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * What happens to a user who has disappeared (`013-005-0002`).
 *
 * Whoever disappears from the directory can no longer get in — that follows by itself.
 * Their account remains, however, and with it a refresh token that keeps fetching fresh
 * access JWTs until its time limit. A user removed by the HR department therefore keeps
 * working until a time limit expires that nobody chose for this.
 *
 * Measured via the `ExampleProvider`: It reads its list from the environment, and the
 * console call receives a different list than the test server — so a user disappears
 * from the sync's point of view without a directory having to run anywhere.
 */
class ProviderSyncApiTest extends IntegrationTestCase
{
    private function entry(): array
    {
        $raw = getenv('CONTENTFLY_TEST_PROVIDER') ?: '';

        if ($raw === '') {
            $this->markTestSkipped('CONTENTFLY_TEST_PROVIDER not set — see tests/README.md.');
        }

        $parts = explode(':', explode(',', $raw)[0]);

        return array('identifier' => $parts[0], 'secret' => $parts[1] ?? '');
    }

    /** Logs in through the provider and cleans up the created row after the test. */
    private function loginThroughProvider(): array
    {
        $entry = $this->entry();

        [$status, $body] = $this->postJson('/auth/login', array(
            'alias'        => $entry['identifier'],
            'pass'         => $entry['secret'],
            'loginManager' => 'example',
            'tokenType'    => 'jwt',
        ));

        $row = $this->pdo()->prepare('SELECT id FROM pim_user WHERE loginManager = :lm AND externalId = :ext');
        $row->execute(array('lm' => 'example', 'ext' => $entry['identifier']));

        if ($id = $row->fetchColumn()) {
            $this->deleteAfterTest('pim_user', (string) $id);
        }

        if ($status !== 200) {
            $this->fail('Login through the provider failed: '.json_encode($body));
        }

        return $body['data']; // 011-001-0004: the session is the payload of the login
    }

    /**
     * Runs the sync with a self-chosen provider list.
     *
     * **The difference that matters:** A list that does not contain the user
     * means "removed from the directory". An **empty** list means "no answer" — the
     * provider cannot say anything, and then nobody is touched.
     */
    private function sync(string $list, bool $dryRun = false): string
    {
        $output = array();

        exec(sprintf(
            'CONTENTFLY_EXAMPLE_PROVIDER=%s %s %s appcms:provider:sync %s 2>&1',
            escapeshellarg($list),
            escapeshellarg(PHP_BINARY),
            escapeshellarg(self::console()),
            $dryRun ? '--dry-run' : ''
        ), $output);

        return implode("\n", $output);
    }

    private function isActive(string $identifier): bool
    {
        $row = $this->pdo()->prepare('SELECT isActive FROM pim_user WHERE loginManager = :lm AND externalId = :ext');
        $row->execute(array('lm' => 'example', 'ext' => $identifier));

        return (string) $row->fetchColumn() === '1';
    }

    // ── The core ───────────────────────────────────────────────────────────────────────

    /**
     * **The proof this is all about.** A refresh token that is still valid is of no use anymore
     * as soon as the external system no longer knows the user.
     */
    public function testWhoeverDisappearsFromTheExternalSystemLosesAccess(): void
    {
        $entry = $this->entry();
        $login = $this->loginThroughProvider();

        // Before: The refresh token redeems.
        [$before] = $this->postJson('/auth/refresh', array('refreshToken' => $login['refreshToken']));
        $this->assertSame(200, $before);

        [, $second] = $this->postJson('/auth/refresh', array('refreshToken' => $login['refreshToken']));

        // A list WITHOUT this user — they have been removed from the directory.
        $output = $this->sync('someone-else:whatever');

        $this->assertFalse($this->isActive($entry['identifier']), 'Locked — '.$output);

        [$after] = $this->postJson('/auth/refresh', array('refreshToken' => $second['data']['refreshToken'] ?? 'x'));
        $this->assertSame(401, $after, 'The refresh token no longer redeems');
    }

    /**
     * **An outage must not look like a deleted user.**
     *
     * This is the error that locks out an entire workforce: If "external system does not
     * respond" is read as "user no longer exists", a network error locks everyone out.
     * That is why `knowsIdentifier()` has three answers instead of two, and `null` touches nobody.
     *
     * For the `ExampleProvider`, an **empty** list is exactly this case — it means "no
     * answer" and not "knows nobody". If a missing configuration were read as `false`,
     * the first sync after a forgotten environment entry would lock everyone out.
     */
    public function testWithoutAnAnswerNobodyIsLocked(): void
    {
        $entry = $this->entry();
        $this->loginThroughProvider();

        $output = $this->sync('');

        $this->assertTrue($this->isActive($entry['identifier']), 'Untouched — '.$output);
        $this->assertStringContainsString('could not give an answer', $output);
    }

    public function testWhoeverIsStillInTheExternalSystemStaysActive(): void
    {
        $entry = $this->entry();
        $this->loginThroughProvider();

        $output = $this->sync(getenv('CONTENTFLY_TEST_PROVIDER'));

        $this->assertTrue($this->isActive($entry['identifier']), 'Untouched — '.$output);
    }

    /**
     * `--dry-run` counts and does not lock. A sync that cannot be previewed beforehand is
     * not run.
     */
    public function testDryRunCountsAndDoesNotLock(): void
    {
        $entry = $this->entry();
        $this->loginThroughProvider();

        $output = $this->sync('someone-else:whatever', true);

        $this->assertStringContainsString('--dry-run', $output);
        $this->assertTrue($this->isActive($entry['identifier']), 'Still active — '.$output);
    }

    /**
     * A user with a local password is none of any external system's business.
     */
    public function testAUserWithoutAProviderIsNotTouched(): void
    {
        $this->sync('someone-else:whatever');

        $row = $this->pdo()->query("SELECT isActive FROM pim_user WHERE alias = 'admin'");

        $this->assertSame('1', (string) $row->fetchColumn(), 'admin has no loginManager');
    }
}
