<?php
namespace Tests\Integration\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Integration\IntegrationTestCase;

/**
 * Every failed login answers the same thing (`015-000-0008`).
 *
 * THE FINDING. `AuthController::loginAction()` builds its rejections through one closure, and the
 * closure took the message from its caller. The callers passed four different ones:
 *
 *  - `Invalid user name.` — the account does not exist,
 *  - `The user is deactivated.` — it exists and is switched off,
 *  - `The user can only be authenticated through their login provider.` — it exists and is
 *    external,
 *  - `Invalid user name and/or password.` — it exists, is active, is local, and the password is
 *    wrong.
 *
 * The comment above the closure claimed the wording was already uniform. It was not, and the
 * difference is a list of user names for the asking — the target list for password spraying. The
 * throttle limits the rate, not the fact.
 *
 * WHAT IS COMPARED IS THE WHOLE ANSWER, not only the text: status, code and detail. A body that
 * differs anywhere is an oracle, and a test that only looked at `detail` would miss the next one.
 *
 * The runtime difference is a second channel with its own task, `015-000-0018`.
 */
class LoginOracleApiTest extends IntegrationTestCase
{
    /**
     * The four cases of the task, each returning a login attempt as [alias, password].
     *
     * @return array<string, array{0: string}>
     */
    public static function rejectedCases(): array
    {
        return array(
            'the account does not exist' => array('unknown'),
            'the account is deactivated' => array('deactivated'),
            'the account is external'    => array('provider'),
            'the password is wrong'      => array('wrongPassword'),
            'the provider is unknown'    => array('unknownProvider'),
        );
    }

    #[DataProvider('rejectedCases')]
    public function testEveryRejectionAnswersTheSame(string $case): void
    {
        $reference = $this->attempt('wrongPassword');
        $measured  = $this->attempt($case);

        $this->assertSame($reference['status'], $measured['status'], 'Same status');
        $this->assertSame($reference['code'], $measured['code'], 'same code');
        $this->assertSame($reference['detail'], $measured['detail'], 'and same detail');
    }

    /**
     * And nothing else in the envelope differs either — `context` carried the value of an
     * exception in other places, and a value there would name what was looked up.
     */
    #[DataProvider('rejectedCases')]
    public function testNothingElseInTheEnvelopeDiffers(string $case): void
    {
        $reference = $this->attempt('wrongPassword');
        $measured  = $this->attempt($case);

        $this->assertSame($reference['error'], $measured['error'],
            'The whole error object is identical, context included');
    }

    /** The answer names no account, in no form. */
    public function testTheAnswerNamesNoAccount(): void
    {
        $alias = $this->deactivatedUser();

        $body = $this->attempt('deactivated')['body'];

        $this->assertStringNotContainsString($alias, $body, 'The alias is not echoed back');
        $this->assertStringNotContainsString('deactivated', strtolower($body), 'and neither is the reason');
        $this->assertStringNotContainsString('provider', strtolower($body));
    }

    // ── helpers ────────────────────────────────────────────────────────────────────────────

    /**
     * One rejected login of the given kind.
     *
     * @return array{status:int, code:?string, detail:?string, error:array, body:string}
     */
    private function attempt(string $case): array
    {
        [$alias, $password, $provider] = match ($case) {
            'unknown'         => array('does-not-exist-'.bin2hex(random_bytes(4)), 'whatever', null),
            'deactivated'     => array($this->deactivatedUser(), self::TEST_PASSWORD, null),
            'provider'        => array($this->providerUser(), self::TEST_PASSWORD, null),
            'wrongPassword'   => array($this->localUser(), 'not-the-password', null),
            'unknownProvider' => array('anyone', 'whatever', 'no-such-provider-'.bin2hex(random_bytes(4))),
        };

        $payload = array('alias' => $alias, 'pass' => $password);
        if ($provider !== null) {
            $payload['loginManager'] = $provider;
        }

        [$status, $body] = $this->postJson('/auth/login', $payload);

        $error = $this->assertErrorEnvelope($body);

        return array(
            'status' => $status,
            'code'   => $error['code'] ?? null,
            'detail' => $error['detail'] ?? null,
            'error'  => $error,
            'body'   => json_encode($body) ?: '',
        );
    }

    private ?string $deactivated = null;
    private ?string $provider    = null;
    private ?string $local       = null;

    /** An account that exists and is switched off. */
    private function deactivatedUser(): string
    {
        if ($this->deactivated === null) {
            [, $id] = $this->createTestUser();
            $this->pdo()->prepare('UPDATE pim_user SET isActive = 0 WHERE id = :id')->execute(array('id' => $id));
            $this->deactivated = $this->alias($id);
        }

        return $this->deactivated;
    }

    /** An account that exists and may only come in through its login provider. */
    private function providerUser(): string
    {
        if ($this->provider === null) {
            [, $id] = $this->createTestUser();
            $this->pdo()->prepare("UPDATE pim_user SET loginManager = 'external-one' WHERE id = :id")
                        ->execute(array('id' => $id));
            $this->provider = $this->alias($id);
        }

        return $this->provider;
    }

    /** An ordinary account — the reference case. */
    private function localUser(): string
    {
        if ($this->local === null) {
            [, $id]      = $this->createTestUser();
            $this->local = $this->alias($id);
        }

        return $this->local;
    }

    private function alias(string $id): string
    {
        return (string) $this->pdo()->query('SELECT alias FROM pim_user WHERE id = '.$this->pdo()->quote($id))->fetchColumn();
    }
}
