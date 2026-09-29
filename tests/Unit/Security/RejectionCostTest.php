<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * A rejected login costs what an accepted one costs (`015-000-0018`).
 *
 * THE FINDING. `/auth/login` turned away an unknown alias, a deactivated account and an account
 * bound to a login provider BEFORE any hash was checked. Only an existing, active, local account
 * reached `isPass()` and paid for Argon2id — deliberately some tens of milliseconds. The response
 * time therefore answered the very question `015-000-0008` had just stopped the error texts and
 * the status codes from answering: does this account exist?
 *
 * WHAT IS MEASURED HERE is the piece that can be measured without a clock: that the dummy hash
 * really carries the CURRENT algorithm and cost, so that the equalisation cannot quietly go stale.
 * That it is actually spent on every rejecting path is measured end to end in
 * `Tests\Integration\Api\LoginTimingApiTest`.
 */
class RejectionCostTest extends TestCase
{
    /**
     * The algorithm the entity would hash a new password with.
     *
     * Deliberately written out a second time instead of reaching into the private method: this
     * test exists to pin the relationship between the two, and a test that asked the code under
     * test what it expects would pin nothing.
     */
    private function currentAlgorithm(): string|int
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }

    /**
     * The point of the second acceptance criterion: the dummy must not age.
     *
     * A hash pasted into the source as a constant keeps the cost parameters of the day it was
     * pasted. The moment the algorithm or PHP's defaults move, it becomes cheaper than a real
     * verification — and the equalisation is gone without anything failing.
     */
    public function testTheDummyHashUsesTheCurrentAlgorithmAndCost(): void
    {
        $this->assertFalse(
            password_needs_rehash(User::rejectionHash(), $this->currentAlgorithm()),
            'The dummy hash must follow the algorithm and options new passwords are hashed with'
        );
    }

    public function testTheDummyHashIsARealHashOfTheExpectedAlgorithm(): void
    {
        $info     = password_get_info(User::rejectionHash());
        $expected = password_get_info(password_hash('probe', $this->currentAlgorithm()));

        $this->assertSame($expected['algoName'], $info['algoName']);
    }

    /**
     * It is a dummy: nothing a caller can send ever matches it.
     *
     * That holds because the hash is made from random bytes, not from a word in the source. A
     * fixed input would be a real password for this hash — harmless while the result is discarded,
     * but a property nobody should have to keep checking.
     */
    public function testNothingMatchesTheDummyHash(): void
    {
        foreach (array('', 'admin', 'contentfly', 'password', '*') as $candidate) {
            $this->assertFalse(
                password_verify($candidate, User::rejectionHash()),
                sprintf('"%s" must not match the dummy hash', $candidate)
            );
        }
    }

    /** Stable within the process — otherwise every call would pay for hashing, not verifying. */
    public function testTheDummyHashIsGeneratedOnlyOnce(): void
    {
        $this->assertSame(User::rejectionHash(), User::rejectionHash());
    }

    /**
     * And the equalisation really spends a verification.
     *
     * A lower bound with a very wide margin, not an equality: an Argon2id verification costs tens
     * of milliseconds, a method that did nothing costs microseconds. Anything in between would
     * mean the call stopped verifying.
     */
    public function testTheEqualisationSpendsAVerification(): void
    {
        User::rejectionHash(); // pay for generating it outside the measurement

        $start = hrtime(true);
        User::equaliseRejectionCost('whatever the caller sent');
        $elapsed = (hrtime(true) - $start) / 1_000_000;

        $this->assertGreaterThan(
            1.0,
            $elapsed,
            sprintf('A real verification takes far longer than a millisecond; this took %.3f ms', $elapsed)
        );
    }

    /** Whatever the caller sends, including what is not a string at all. */
    public function testItSurvivesEveryKindOfInput(): void
    {
        foreach (array(null, '', 'x', 42, 1.5, true) as $input) {
            User::equaliseRejectionCost($input);
        }

        $this->addToAssertionCount(1);
    }
}
