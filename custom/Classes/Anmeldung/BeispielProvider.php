<?php
namespace Custom\Classes\Anmeldung;

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
 * It checks against a list from the environment variable `CONTENTFLY_BEISPIEL_PROVIDER`. If that
 * is not set, the list is empty and **every** login is rejected. A template that accidentally
 * leaves an installation open would be worse than none at all.
 *
 * The format is `identifier:secret:group|group`, several separated by commas. It is a
 * placeholder for an external system, not a recommendation: secrets in an environment variable
 * are fine for a test and not for production.
 */
final class BeispielProvider implements LoginProvider, UserExistenceCheck
{
    public const UMGEBUNGSVARIABLE = 'CONTENTFLY_BEISPIEL_PROVIDER';

    public function authenticate(Request $request): ?ExternalIdentity
    {
        $daten    = $request->request->all();
        $kennung  = $daten['alias'] ?? null;
        $vorgezeigt = $daten['pass'] ?? null;

        if (!is_string($kennung) || !is_string($vorgezeigt) || $kennung === '') {
            return null;
        }

        foreach ($this->bekannte() as $eintrag) {
            if ($eintrag['kennung'] !== $kennung) {
                continue;
            }

            /*
             * `hash_equals()` and not `===`: a comparison that stops at the first differing
             * character reveals through its running time how much was right. For a
             * secret of this kind, that is the whole check.
             */
            if (!hash_equals($eintrag['geheimnis'], $vorgezeigt)) {
                return null;
            }

            return new ExternalIdentity($eintrag['kennung'], $eintrag['gruppen']);
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
    public function knowsIdentifier(string $kennung): ?bool
    {
        $roh = $_ENV[self::UMGEBUNGSVARIABLE] ?? getenv(self::UMGEBUNGSVARIABLE) ?: '';

        if (!is_string($roh) || trim($roh) === '') {
            return null;
        }

        foreach ($this->bekannte() as $eintrag) {
            if ($eintrag['kennung'] === $kennung) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{kennung: string, geheimnis: string, gruppen: list<string>}>
     */
    private function bekannte(): array
    {
        $roh = $_ENV[self::UMGEBUNGSVARIABLE] ?? getenv(self::UMGEBUNGSVARIABLE) ?: '';

        if (!is_string($roh) || trim($roh) === '') {
            return array();
        }

        $liste = array();

        foreach (explode(',', $roh) as $zeile) {
            $teile = explode(':', trim($zeile));

            if (count($teile) < 2 || $teile[0] === '' || $teile[1] === '') {
                continue;
            }

            $liste[] = array(
                'kennung'   => $teile[0],
                'geheimnis' => $teile[1],
                'gruppen'   => isset($teile[2]) && $teile[2] !== ''
                    ? explode('|', $teile[2])
                    : array(),
            );
        }

        return $liste;
    }
}
