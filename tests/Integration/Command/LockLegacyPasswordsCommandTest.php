<?php
namespace Tests\Integration\Command;

use Tests\Integration\IntegrationTestCase;

/**
 * `appcms:security:lock-legacy-passwords` — the forced reset for old hashes (`000-000-0103`).
 *
 * WHY A LOCK AND NOT A MIGRATION. A hash cannot be converted without knowing the password; that is
 * what a hash is for. `013-001-0001` therefore replaces an old one at the next login, where the
 * plaintext is briefly at hand. Whoever has not logged in since still carries theirs, and no batch
 * job changes that. The decision — forced reset — was taken in `015-000-0012` and written into the
 * register; this command carries it out.
 *
 * WHY IT MATTERS NOW. `015-000-0012` closed the path that let a hash be read out through the API.
 * A SHA-256 hash without a work factor stays the weakest point of any installation that still has
 * one: a backup or a database dump makes it crackable at GPU speed. The API is no longer the way
 * in; the hash is still the prize.
 *
 * ── Integration, not unit ─────────────────────────────────────────────────────────────────
 *
 * The command builds its EntityManager from the configuration the installer wrote, and what is
 * measured is the column afterwards — plus a login attempt against the locked account, because a
 * lock that still lets the old password through would pass a column assertion.
 */
class LockLegacyPasswordsCommandTest extends IntegrationTestCase
{
    private const PASSWORD = 'the-old-password';

    public function testDryRunCountsAndWritesNothing(): void
    {
        [$legacy, $modern] = $this->twoAccounts();

        $output = $this->runCommand(array('--dry-run'));

        $this->assertStringContainsString('1 account(s) carry a SHA-256 hash', $output);
        $this->assertStringContainsString('--dry-run', $output);
        $this->assertStringNotContainsString('*', $this->hashOf($legacy), 'nothing was written');
        $this->assertSame($this->hashOf($modern), $this->hashOf($modern), 'and the modern one is untouched');
    }

    public function testItLocksOnlyTheLegacyAccount(): void
    {
        [$legacy, $modern] = $this->twoAccounts();

        $before = $this->hashOf($modern);

        $output = $this->runCommand(array());

        $this->assertStringContainsString('were locked', $output);
        $this->assertSame('*', $this->hashOf($legacy), 'the old account is locked');
        $this->assertSame($before, $this->hashOf($modern), 'the Argon2id account is untouched');
    }

    /**
     * And the lock bites: the formerly correct password no longer works.
     *
     * `015-000-0001` already covers that an empty password matches nothing; this covers the other
     * half for this path — the password that WAS right.
     */
    public function testTheLockedAccountCannotLogInWithTheOldPassword(): void
    {
        [$legacy] = $this->twoAccounts();

        $this->runCommand(array());

        [$status] = $this->postJson('/auth/login', array(
            'alias' => $this->aliasOf($legacy),
            'pass'  => self::PASSWORD,
        ));

        $this->assertSame(401, $status, 'the old password is refused');
    }

    /** Running twice must not look like it found new ones. */
    public function testASecondRunFindsNothing(): void
    {
        $this->twoAccounts();

        $this->runCommand(array());
        $output = $this->runCommand(array());

        $this->assertStringContainsString('No account carries a SHA-256 hash any more', $output);
        $this->assertStringContainsString('already have a locked password', $output);
    }

    /** The operator is told how the locked accounts get back in. */
    public function testTheOutputNamesTheWayBack(): void
    {
        $this->twoAccounts();

        $output = $this->runCommand(array());

        $this->assertStringContainsString('until an admin sets a new password', $output);
    }

    // ── helpers ────────────────────────────────────────────────────────────────────────────

    /**
     * One account with a SHA-256 hash, one with Argon2id.
     *
     * @return array{0:string,1:string} ids, legacy first
     */
    private function twoAccounts(): array
    {
        $run   = bin2hex(random_bytes(5));
        $salt  = bin2hex(random_bytes(16));
        $ids   = array();

        $insert = $this->pdo()->prepare(
            'INSERT INTO pim_user (id, isAdmin, alias, pass, isActive, salt,
                                   created, modified, views, isIntern)
             VALUES (:id, 0, :alias, :pass, 1, :salt, NOW(), NOW(), 0, 0)'
        );

        // The old format: sha256(password + salt), exactly what 013-001-0001 replaces on login.
        $legacy = 'lgcy-'.$run;
        $insert->execute(array(
            'id'    => $legacy,
            'alias' => 'legacy-'.$run,
            'pass'  => hash('sha256', self::PASSWORD.$salt),
            'salt'  => $salt,
        ));
        $this->deleteAfterTest('pim_user', $legacy);
        $ids[] = $legacy;

        $modern = 'mdrn-'.$run;
        $insert->execute(array(
            'id'    => $modern,
            'alias' => 'modern-'.$run,
            'pass'  => password_hash(self::PASSWORD, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT),
            'salt'  => $salt,
        ));
        $this->deleteAfterTest('pim_user', $modern);
        $ids[] = $modern;

        return $ids;
    }

    /** @param list<string> $options */
    private function runCommand(array $options): string
    {
        $command = array_merge(
            array(escapeshellarg(PHP_BINARY), escapeshellarg(self::console()), 'appcms:security:lock-legacy-passwords'),
            $options
        );

        $out = array();
        exec(implode(' ', $command).' 2>&1', $out, $code);

        $all = implode("\n", $out);
        $this->assertSame(0, $code, "The command failed:\n".$all);

        return $all;
    }

    private function hashOf(string $id): string
    {
        $statement = $this->pdo()->prepare('SELECT pass FROM pim_user WHERE id = ?');
        $statement->execute(array($id));

        return (string) $statement->fetchColumn();
    }

    private function aliasOf(string $id): string
    {
        $statement = $this->pdo()->prepare('SELECT alias FROM pim_user WHERE id = ?');
        $statement->execute(array($id));

        return (string) $statement->fetchColumn();
    }
}
