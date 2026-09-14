<?php
namespace Custom\Classes\Authentication;

use Areanet\PIM\Classes\Security\LoginProvider;
use Areanet\PIM\Classes\Security\UserExistenceCheck;
use Areanet\PIM\Classes\Security\ExternalIdentity;
use Symfony\Component\HttpFoundation\Request;

/**
 * The template for a login provider — and it really runs (`013-004-0004`).
 *
 * A provider has **one** duty: verify against the external system. Everything else is done by
 * the framework — finding or creating users, setting groups and the admin flag, issuing the
 * token, logging out. It does **not** touch the database; that is the difference from the old
 * `LoginManager`, which had to return a finished `User` entity and thereby pushed provisioning
 * into the project.
 *
 * ── What a real project changes here ──────────────────────────────────────────────────
 *
 * `authenticate()`. In a real project this is where the call to LDAP, SAML, OIDC or whatever
 * the external system is goes. It returns a `ExternalIdentity` carrying the identifier **in the
 * external system** — not the Contentfly alias — and whatever groups the external system reports.
 *
 * ── What it should not touch ──────────────────────────────────────────────────────────
 *
 * The return type. `null` means rejected, and it is the only way to reject: an
 * exception leads to the same result, but is swallowed by the framework so that its message
 * does not reach the caller. An LDAP error including the server name in the response is exactly
 * what happened until `013-004-0001`.
 *
 * And the mapping to Contentfly groups: that belongs in `SECURITY_PROVIDER_GROUPS`, not
 * here. A provider reports what the external system reports.
 *
 * ── Why this template lets nobody in ──────────────────────────────────────────────────
 *
 * It checks against a list from the environment variable `CONTENTFLY_EXAMPLE_PROVIDER`. If that
 * is not set, the list is empty and **every** login is rejected. A template that accidentally
 * leaves an installation open would be worse than none at all.
 *
 * The format is `identifier:secret:group|group`, several separated by commas. It is a
 * placeholder for an external system, not a recommendation: secrets in an environment variable
 * are fine for a test and not for production.
 */
final class ExampleProvider implements LoginProvider, UserExistenceCheck
{
    public const ENVIRONMENT_VARIABLE = 'CONTENTFLY_EXAMPLE_PROVIDER';

    public function authenticate(Request $request): ?ExternalIdentity
    {
        $data       = $request->request->all();
        $identifier = $data['alias'] ?? null;
        $presented  = $data['pass'] ?? null;

        if (!is_string($identifier) || !is_string($presented) || $identifier === '') {
            return null;
        }

        foreach ($this->knownEntries() as $entry) {
            if ($entry['identifier'] !== $identifier) {
                continue;
            }

            /*
             * `hash_equals()` and not `===`: a comparison that stops at the first differing
             * character reveals through its running time how much was right. For a
             * secret of this kind, that is the whole check.
             */
            if (!hash_equals($entry['secret'], $presented)) {
                return null;
            }

            return new ExternalIdentity($entry['identifier'], $entry['groups']);
        }

        return null;
    }

    /**
     * Does the "external system" still know this identifier? (`013-005-0002`)
     *
     * The template implements `UserExistenceCheck` because it **can**: its list lives in
     * the environment, and looking it up needs no secret. A real provider cannot always
     * do that — an OIDC provider, for instance, verifies a token the client brings along and has
     * no means without it. Then this interface is left out, and `appcms:provider:sync`
     * visibly skips the provider.
     *
     * **Without a configured list there is no answer, not "knows nobody".** The
     * difference is decisive: if a missing configuration were read as `false`, the first
     * sync after a forgotten environment entry would lock out every user.
     */
    public function knowsIdentifier(string $identifier): ?bool
    {
        $raw = $_ENV[self::ENVIRONMENT_VARIABLE] ?? getenv(self::ENVIRONMENT_VARIABLE) ?: '';

        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        foreach ($this->knownEntries() as $entry) {
            if ($entry['identifier'] === $identifier) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{identifier: string, secret: string, groups: list<string>}>
     */
    private function knownEntries(): array
    {
        $raw = $_ENV[self::ENVIRONMENT_VARIABLE] ?? getenv(self::ENVIRONMENT_VARIABLE) ?: '';

        if (!is_string($raw) || trim($raw) === '') {
            return array();
        }

        $list = array();

        foreach (explode(',', $raw) as $line) {
            $parts = explode(':', trim($line));

            if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
                continue;
            }

            $list[] = array(
                'identifier' => $parts[0],
                'secret'     => $parts[1],
                'groups'     => isset($parts[2]) && $parts[2] !== ''
                    ? explode('|', $parts[2])
                    : array(),
            );
        }

        return $list;
    }
}
