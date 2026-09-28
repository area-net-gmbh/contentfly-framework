<?php
namespace Tests\Integration\Command;

use Tests\Integration\IntegrationTestCase;

/**
 * `appcms:setup` against the installed instance (`015-000-0002`).
 *
 * THE FINDING WAS THAT A SETUP RUN WAS A PASSWORD RESET. `Helper::install()` ran
 * `setPass('admin')` on every call — on an account it found just as on one it created. A second
 * `appcms:setup` on a live instance therefore put the admin password back to `admin`, and
 * `POST /auth/login {"alias":"admin","pass":"admin"}` answered with an admin token on the first
 * attempt, far below the login throttle.
 *
 * Both directions are measured here: the account that exists keeps its password, and the account
 * that is created gets a generated one that is named exactly once.
 *
 * Integration, not unit: what is being checked is the real command against the real schema, and
 * the proof is the login — not that a method was called.
 */
class SetupCommandTest extends IntegrationTestCase
{
    public function testSetupLeavesAnExistingAdminPasswordAlone(): void
    {
        $before = $this->adminHash();

        [$code, $output] = $this->runSetup();

        $this->assertSame(0, $code, $output);
        $this->assertSame($before, $this->adminHash(), 'The hash of the existing admin is untouched.');
        $this->assertStringContainsString('The existing admin account is unchanged.', $output);
        $this->assertStringNotContainsString('password=admin', $output,
            'And the command no longer announces a default password.');

        $this->assertSame(401, $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => 'admin'))[0],
            'admin/admin is not a way in.');
        $this->assertSame(200, $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => $this->pass()))[0],
            'The real password still is — and this resets the throttle counter of the line above.');
    }

    /**
     * The other direction: no admin, so the command creates one.
     *
     * The existing account is parked under a different alias for the duration and put back in
     * `finally` — the suite's other tests log in as `admin`.
     */
    public function testSetupCreatesAnAdminWithAGeneratedPasswordAndNamesItOnce(): void
    {
        $parked = 'admin-parked-'.bin2hex(random_bytes(4));
        $this->pdo()->prepare('UPDATE pim_user SET alias = :parked WHERE alias = :admin')
                    ->execute(array('parked' => $parked, 'admin' => 'admin'));

        try {
            [$code, $output] = $this->runSetup();

            $this->assertSame(0, $code, $output);
            $this->assertSame(1, preg_match('#Login: admin / ([0-9a-f]{24})#', $output, $found),
                "The generated password is named, and it is not `admin`:\n".$output);
            $this->assertStringContainsString('stored nowhere else', $output,
                'Said plainly, because there is no second chance to read it.');

            [$status, $body] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => $found[1]));

            $this->assertSame(200, $status, 'The generated password is the way in.');
            $this->assertTrue($body['data']['user']['isAdmin'], 'And it belongs to an admin.');

            $this->assertSame(401, $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => 'admin'))[0],
                'admin/admin is not — this is the finding.');
        } finally {
            $this->pdo()->exec("DELETE FROM pim_user WHERE alias = 'admin'");
            $this->pdo()->prepare('UPDATE pim_user SET alias = :admin WHERE alias = :parked')
                        ->execute(array('admin' => 'admin', 'parked' => $parked));
        }

        $this->assertSame(200, $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => $this->pass()))[0],
            'The parked account is back, and the suite can carry on.');
    }

    // ── helpers ────────────────────────────────────────────────────────────────────────────

    /** @return array{0:int,1:string} exit code and combined output */
    private function runSetup(): array
    {
        $output = array();
        exec(
            sprintf('%s %s appcms:setup 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg(self::console())),
            $output,
            $code
        );

        return array($code, implode("\n", $output));
    }

    private function adminHash(): string
    {
        return (string) $this->pdo()->query("SELECT pass FROM pim_user WHERE alias = 'admin'")->fetchColumn();
    }
}
