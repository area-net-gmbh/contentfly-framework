<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * The per-identifier throttle counts on the account (`015-000-0010`).
 *
 * THE FINDING. The bucket was `sha256(mb_strtolower(trim($alias)))`, while the account is looked
 * up with `findOneBy(['alias' => …])` under `utf8mb3_unicode_ci`. Measured against this database
 * on 2026-09-28, that collation treats all of these as the same account name:
 *
 *     Admin · a as U+00E0 · a as U+00E1 · a as U+00E4 · a full width as U+FF41 · a trailing space
 *
 * — and it equates U+00DF with `ss`. Each of them got a throttle bucket of its own, so the tiers
 * per identifier (5/min, 20/15min, 50/h) never filled up. Against a single account, the admin
 * included, only the IP axis was left, and the class comment names the identifier axis as the
 * defence against guessing from many addresses.
 *
 * WHAT IS MEASURED. Failed attempts are sent under changing variants of one alias, more than the
 * first tier allows. If the identity is the account, the throttle answers 429 after five; if it
 * is the typed string, every variant starts at zero and the throttle never speaks.
 *
 * The suite's server runs one IP, so the IP axis would eventually answer too. Its first tier is
 * far wider (see `TIERS_IP`), and each test stays below it — what answers here is the identifier
 * axis.
 */
class LoginThrottleIdentityApiTest extends IntegrationTestCase
{
    /** The first tier per identifier: five failed attempts a minute. */
    private const TIER_ONE = 5;

    /**
     * The variants of one alias, as the collation reads them.
     *
     * Built from code points so that the file carries no look-alike characters a reader would
     * have to trust their font about.
     */
    private static function variantsOf(string $alias): array
    {
        $first = mb_substr($alias, 0, 1);
        $rest  = mb_substr($alias, 1);

        return array(
            $alias,                                  // as stored
            mb_strtoupper($first).$rest,             // different case
            "\u{00E0}".$rest,                        // a with grave
            "\u{00E1}".$rest,                        // a with acute
            "\u{00E4}".$rest,                        // a with diaeresis
            "\u{FF41}".$rest,                        // full width a
            $alias.' ',                              // trailing space
        );
    }

    public function testChangingTheVariantDoesNotResetTheThrottle(): void
    {
        $alias = $this->accountStartingWithA();

        $blocked = null;
        foreach (self::variantsOf($alias) as $number => $variant) {
            [$status] = $this->postJson('/auth/login', array('alias' => $variant, 'pass' => 'not-the-password'));

            if ($status === 429) {
                $blocked = $number;
                break;
            }

            $this->assertSame(401, $status, 'Attempt '.$number.' with a variant of the alias');
        }

        $this->assertNotNull($blocked,
            'The throttle never answered, although every variant hits the same account — this is the finding');
        $this->assertSame(self::TIER_ONE, $blocked,
            'and it answered after exactly the attempts the first tier allows, as with an unchanged alias');
    }

    /**
     * The reference: the same count with the alias unchanged. Without it the test above could
     * pass for the wrong reason — a throttle that is simply too tight.
     */
    public function testTheUnchangedAliasIsBlockedAfterTheSameNumberOfAttempts(): void
    {
        $alias = $this->accountStartingWithA();

        $blocked = null;
        for ($number = 0; $number < 7; $number++) {
            [$status] = $this->postJson('/auth/login', array('alias' => $alias, 'pass' => 'not-the-password'));

            if ($status === 429) {
                $blocked = $number;
                break;
            }
        }

        $this->assertSame(self::TIER_ONE, $blocked);
    }

    /**
     * A name that matches no account keeps a bucket of its own, so an invented name cannot be
     * aimed at a real account's budget.
     */
    public function testAnInventedNameDoesNotConsumeARealAccountsBudget(): void
    {
        $alias = $this->accountStartingWithA();

        for ($number = 0; $number < self::TIER_ONE; $number++) {
            [$status] = $this->postJson('/auth/login', array(
                'alias' => 'no-such-account-'.bin2hex(random_bytes(4)), 'pass' => 'x',
            ));
            $this->assertSame(401, $status);
        }

        [$status] = $this->postJson('/auth/login', array('alias' => $alias, 'pass' => 'not-the-password'));

        $this->assertSame(401, $status,
            'The real account still has its own budget — the invented names did not spend it');
    }

    /** A correct login still clears the account's counter. */
    public function testASuccessfulLoginClearsTheAccountsCounter(): void
    {
        $alias = $this->accountStartingWithA();

        for ($number = 0; $number < self::TIER_ONE - 1; $number++) {
            $this->postJson('/auth/login', array('alias' => $alias, 'pass' => 'not-the-password'));
        }

        $this->assertSame(200, $this->postJson('/auth/login', array('alias' => $alias, 'pass' => self::TEST_PASSWORD))[0]);

        [$status] = $this->postJson('/auth/login', array('alias' => $alias, 'pass' => 'not-the-password'));

        $this->assertSame(401, $status, 'and the next failure starts from zero, not from the block');
    }

    // ── helpers ────────────────────────────────────────────────────────────────────────────

    private ?string $alias = null;

    /**
     * An account whose alias starts with `a`, so the variants above are variants of its first
     * character. `createTestUser()` names its accounts `testuser-…`; this one is renamed.
     */
    private function accountStartingWithA(): string
    {
        if ($this->alias === null) {
            [, $id]      = $this->createTestUser();
            $this->alias = 'athrottle-'.bin2hex(random_bytes(4));

            $this->pdo()->prepare('UPDATE pim_user SET alias = :alias WHERE id = :id')
                        ->execute(array('alias' => $this->alias, 'id' => $id));
        }

        return $this->alias;
    }
}
