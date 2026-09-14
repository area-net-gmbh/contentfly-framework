<?php
/**
 * Project bootstrap — the one file in which a project registers its own behaviour.
 *
 * Loaded by lib/contentfly/bootstrap.php once the framework is up and before
 * $app['routeManager']->bindRoutes() binds the routes. Everything registered here
 * belongs to the project — the framework itself stays untouched.
 *
 * This file is a **template**: it shows the four patterns a project needs, as working
 * examples. How a real project builds on them — middleware order, trusted proxies — is
 * described in an_project/docs/technical.md.
 *
 * ── After the kernel switch (Epic 009) ──────────────────────────────────────────────────
 *
 * Since Epic 009 this file sits on a Symfony 7.4 kernel instead of Silex 2. **None of the four
 * patterns here are affected** — that is exactly why 009-001 put a dedicated interface
 * between framework and kernel before the kernel was swapped.
 *
 * What stays the same: `$app['key']` as the container, the lazy factory receiving `$app` as
 * its argument, `$app->before()` and `->after()` including the priority argument, the
 * `routeManager` with `mount()` and `isSecure`, the `consoleManager` with `CustomCommand`.
 *
 * What changes for a project is listed in an_project/docs/breaking-changes.md. In short: the
 * container is no longer Pimple (`protect()`, `share()`, `raw()` do not exist),
 * `$app['request']` and `$app['controllers_factory']` are gone, and a custom
 * controller provider now returns a RouteCollection.
 */

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/* -----------------------------------------------------------------------------------------
 * 1. Services — as a factory in the container, available via $app['key'] or $this->app['key']
 *    in a controller. The factory runs on first access, not during bootstrap;
 *    it receives $app as its argument to resolve dependencies.
 *
 *    This is Pimple's contract, and it still holds: the framework's container
 *    (Areanet\PIM\Classes\Kernel\Container) has reproduced it itself since 009-002-0002,
 *    because Symfony's DI container only accepts finished objects at runtime while this file
 *    registers factories.
 *
 *    Not everything belongs in the container: ApiResponseService and ApiDateTimeFormatter are
 *    stateless static helpers and are called directly. The container is for things that
 *    have dependencies or hold state.
 * --------------------------------------------------------------------------------------- */
//   $app['my.service'] = function ($app) {
//       return new \Custom\Classes\Service\Core\MyService($app['orm.em']);
//   };

/* -----------------------------------------------------------------------------------------
 * 2. Routes — through the RouteManager, not through $app->get()/post() directly. The manager
 *    collects the mounts; bindRoutes() binds them right after this file.
 *
 *    mount(<path>, <controller class>), then ->get()/->post()/->match() on it with
 *    (<route>, <isSecure>, <action>). isSecure=true requires a valid token.
 *
 *    isSecure is the per-route authentication decision. Under Silex it hung on a
 *    before() filter on the controller, now on a listener on kernel.controller
 *    (Kernel\Routing\RouteSecurityListener). The call here is unaffected.
 * --------------------------------------------------------------------------------------- */
$controllerProvider = $app['routeManager'];

$controllerProvider->mount('api/v1/example/', '\Custom\Controller\Core\ExampleController')
    ->post('/bootstrap', false, 'bootstrapAction');

/* -----------------------------------------------------------------------------------------
 * 3. Middleware — before hooks run ahead of the action, after hooks after it.
 *
 *    At equal priority, the order of registration is the order of execution;
 *    a second argument raises or lowers it ($app->before($fn, 128)). This is not a detail:
 *    security hooks that build on each other must run in the intended order.
 *    tests/Unit/Kernel/HookReihenfolgeTest.php proves it — it records what actually
 *    happens, not what one assumes.
 *
 *    A before hook that returns a Response aborts processing — that is exactly how
 *    a request is blocked.
 * --------------------------------------------------------------------------------------- */
$app->before(function (Request $request) use ($app) {
    // Example: provide a marker for all subsequent hooks and actions.
    // A `return new JsonResponse(...)` at this point would abort the request.
    $app['request.startedAt'] = microtime(true);
});

$app->after(function (Request $request, Response $response) {
    // Example: response headers that should apply to every response. CORS and the
    // base headers are already set by lib/contentfly/bootstrap-web.php — this is for
    // whatever the project needs on top.
    $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
});

/* -----------------------------------------------------------------------------------------
 * 4. Console commands — through the ConsoleManager, then callable via bin/console.php:
 *
 *        $app['consoleManager']->addCommand(new \Custom\Command\MyCommand());
 *
 *    The manager only accepts commands that extend
 *    Areanet\PIM\Classes\Command\CustomCommand — this base class automatically sets the
 *    name to `custom:<name>`, so project commands never collide with those of the
 *    framework.
 *
 *    custom/Command/ExampleCommand.php shows it. Since 009-004-0001 it extends CustomCommand
 *    and is registered below; before that it extended Symfony\…\Command, did not fit
 *    this path and sat around unused.
 *
 *    As a result the command is called `custom:example:command:run` — CustomCommand prepends
 *    the prefix, and that is the guarantee behind it: a project command can never override
 *    one of the framework's.
 * --------------------------------------------------------------------------------------- */

$app['consoleManager']->addCommand(new \Custom\Command\ExampleCommand());

/* -----------------------------------------------------------------------------------------
 * 5. Login providers — authentication against an external system (LDAP, SAML, OIDC, whatever).
 *
 *    A project registers its providers here under a **name**. The login selects by
 *    this name (parameter `loginManager`), not by a class name: which class the
 *    application instantiates is a decision for the operator, not for the
 *    caller. A name that nobody registers here does not exist.
 *
 *    Until `013-004-0001` the class name came in as a request parameter and was resolved to
 *    `Custom\Classes\<Name>`. The prefix and an `instanceof` check limited
 *    the damage — but the choice lay with the caller.
 *
 *    The provider implements `Areanet\PIM\Classes\Security\LoginProvider` and has exactly one
 *    duty: verify against the external system. It does **not** touch the database — finding or
 *    creating users, assigning groups and issuing the token is done by the framework.
 *
 *    The registration is lazy: the closure only runs when someone logs in via this name,
 *    not on every request.
 *
 *    **As long as nothing is registered here, there is no way around the password check.**
 *    Logging in through an external system is a decision someone has to make.
 *
 *    `BeispielProvider` is registered below and really runs — but it lets nobody
 *    in unless `CONTENTFLY_BEISPIEL_PROVIDER` is set. A template that could accidentally
 *    leave an installation open would be worse than none at all.
 * --------------------------------------------------------------------------------------- */
$app['loginProviders']->register('beispiel', function () {
    return new \Custom\Classes\Anmeldung\BeispielProvider();
});

//   Since `013-005` the framework ships two ready-made providers. Neither is
//   registered — that remains the project's decision:
//
//   Active Directory / LDAP. Configured via the SECURITY_LDAP_* fields; the flow is
//   search, then bind. Requires the PHP extension `ldap`.
//
//   $app['loginProviders']->register('ldap', function () {
//       return \Areanet\PIM\Classes\Security\LdapProvider::fromConfig();
//   });
//
//   OIDC. Verified against the provider's userinfo endpoint; configured via the
//   SECURITY_OIDC_* fields. The client obtains its access token from the identity provider and
//   sends it as `accessToken` (or `pass`) to /auth/login.
//
//   $app['loginProviders']->register('oidc', function () {
//       return \Areanet\PIM\Classes\Security\OidcProvider::fromConfig();
//   });
//
//   A custom provider, where neither of the two fits:
//
//   $app['loginProviders']->register('my-sso', function () use ($app) {
//       return new \Custom\Classes\Anmeldung\MySsoProvider($app);
//   });
