<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * A rejected login takes as long whether the account exists or not (`015-000-0018`).
 *
 * THE FINDING. An unknown alias, a deactivated account and an account bound to a login provider
 * were turned away before any hash was touched. Only an existing, active, local account reached
 * `isPass()` and paid for Argon2id — deliberately some tens of milliseconds. So the clock answered
 * what `015-000-0008` had just stopped the error texts and the status codes from answering.
 *
 * ── Why this is a ratio with a wide margin, and not an equality ───────────────────────────
 *
 * The difference the finding is about is not subtle: without the fix an unknown alias is answered
 * in well under a millisecond, a wrong password in tens of them — two orders of magnitude. A
 * threshold anywhere in between separates them, and a wide one survives a loaded CI machine,
 * where an equality assertion would only measure the noise.
 *
 * ── Why the admin account is the reference ────────────────────────────────────────────────
 *
 * It is the one account the installation hashes with the current algorithm. `createTestUser()`
 * writes a SHA-256 hash on purpose — that path is cheap by nature, and comparing against it would
 * measure the wrong thing.
 *
 * ── Why so few samples ────────────────────────────────────────────────────────────────────
 *
 * The login throttle (`013-001-0003`) allows five failures per identifier per minute. Two per
 * case stays clear of that and of the per-IP limit the rest of the suite also draws on. A run
 * that does hit the throttle is skipped rather than failed: a 429 measures the throttle, not this.
 */
class LoginTimingApiTest extends IntegrationTestCase
{
    /** How much of the reference time the unknown alias must at least take. */
    private const AT_LEAST = 0.4;

    public function testAnUnknownAliasIsNotAnsweredFasterThanAWrongPassword(): void
    {
        $unknown = $this->fastestRejection(fn () => 'nobody-'.bin2hex(random_bytes(6)), 'any-password');
        $wrong   = $this->fastestRejection(fn () => 'admin', 'definitely-not-the-admin-password');

        $this->assertGreaterThan(
            $wrong * self::AT_LEAST,
            $unknown,
            sprintf(
                'An unknown alias must not be answered faster than a wrong password '
                .'(unknown %.1f ms, wrong password %.1f ms)',
                $unknown,
                $wrong
            )
        );
    }

    /**
     * The fastest of two rejected logins, in milliseconds.
     *
     * The fastest, not the average: every disturbance makes a sample slower, never faster, so the
     * minimum is the closest thing to the work the server actually did.
     *
     * @param callable():string $alias a fresh alias per attempt, so the identifier throttle does
     *                                 not see the same name twice where that matters
     */
    private function fastestRejection(callable $alias, string $password): float
    {
        $best = null;

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $start = hrtime(true);
            [$status] = $this->postJson('/auth/login', array('alias' => $alias(), 'pass' => $password));
            $elapsed = (hrtime(true) - $start) / 1_000_000;

            if ($status === 429) {
                $this->markTestSkipped('The login throttle answered — this run measures the throttle, not the login.');
            }

            $this->assertSame(401, $status, 'The login is rejected');

            $best = $best === null ? $elapsed : min($best, $elapsed);
        }

        return (float) $best;
    }
}
