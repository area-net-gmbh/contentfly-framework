# Contentfly 2 — Codebase Structure

This document explains how the Contentfly 2 codebase is organised: which parts exist, how a
request travels through them, and where to look for a given concern. It describes structure
only. It contains no assessment of the code.

All paths are relative to the root of the delivered codebase.

---

## 1. What Contentfly is

Contentfly is a PHP backend for storing and managing structured data. It exposes that data
through a JSON HTTP API and a command-line console. **There is no user interface** — clients
(mobile apps, websites, other systems) talk to the API.

| Layer | Technology |
|---|---|
| Language | PHP ≥ 8.3 (see `lib/contentfly/composer.json`) |
| HTTP and console | Symfony 7.4 LTS components: HttpFoundation, HttpKernel, EventDispatcher, Routing, Console, Security-HTTP, RateLimiter, Cache. **Not** the Symfony full-stack framework — there are no bundles and no YAML configuration. |
| Container | Contentfly's own service container (`Areanet\PIM\Classes\Kernel\Container`) |
| Persistence | Doctrine ORM 3 on DBAL 3, MySQL 8, mapping via PHP attributes |
| Tokens | Opaque tokens (stored as SHA-256 hashes) and JWT (`firebase/php-jwt`, HS256) |

Required PHP extensions: `openssl`, `sodium`, `pdo_mysql`, `mbstring`; `gd` for image processing.
Optional: `ldap` (LDAP login provider), `apcu` or `memcached` (cache drivers).

---

## 2. What the delivered codebase contains

| Path | Purpose |
|---|---|
| `lib/contentfly/` | **The framework** — a Composer package (`areanet/contentfly`, namespace `Areanet\PIM\`) |
| `custom/` | **The project** — configuration, bootstrap, entities, controllers, commands. Ships as a working template. |
| `bin/` | Console entry point (`console.php`) and Doctrine CLI configuration (`cli-config.php`) |
| `data/` | Writable runtime directories (only empty placeholders are delivered) |
| `tests/` | PHPUnit test suite (unit and integration) |
| `index.php` | Web entry point |
| `.htaccess` | Apache rewrite rules: everything that is not an existing file or directory goes to `index.php`; the `Authorization` header is passed through |
| `composer.json`, `composer.lock` | The project manifest. It pulls in the framework package from `lib/contentfly` via a Composer path repository. |
| `phpunit.xml.dist` | Test suite configuration |
| `docker-compose.yml` | A MySQL 8 database for local development and tests |
| `LICENSE` | License |

`vendor/` is **not** delivered; it is created by `composer install` (section 15).

### Not included in this delivery

Development tooling (CI pipeline and scripts, static analysis and migration tool
configuration) and the internal project documentation are not part of this delivery. You will
notice this in two places:

1. **18 unit tests fail** because they read files that are not included. Section 14 lists them.
   They check the internal documentation, the CI scripts and the migration tool configuration,
   not the application.
2. **Code comments and a few messages refer to internal documents**, mostly under
   `an_project/docs/…`, and to internal ticket numbers of the form `NNN-NNN-NNNN`. The same
   folder name appears in two exception messages (`Classes/Kernel/Start.php`,
   `lib/contentfly/bootstrap.php`) and in the `extra.notes` of both `composer.json` files.

The code itself — identifiers, messages, comments and test names — is written in English
throughout. `tests/Unit/EnglishOnlyTest.php` keeps it that way.

---

## 3. Two layers: framework and project

```
┌───────────────────────────────────────────────────────────────────────┐
│ Project (root)                                                        │
│   index.php, bin/console.php     entry points                         │
│   custom/config.php              configuration                        │
│   custom/app.php                 routes, middleware, commands,        │
│                                  login providers of the project       │
│   custom/Entity, Controller,     project code (namespace Custom\)     │
│   Command, Classes, Traits                                            │
│   data/                          runtime files                        │
├───────────────────────────────────────────────────────────────────────┤
│ Framework package  lib/contentfly  (areanet/contentfly, Areanet\PIM\) │
│   bootstrap.php, bootstrap-web.php   service wiring, HTTP setup       │
│   Classes/Kernel                     container, HTTP kernel, start    │
│   Controller, Classes/Controller     built-in API                     │
│   Classes/Security                   authentication                   │
│   Entity                             built-in data model              │
│   Classes/Types                      field types                      │
│   Command                            built-in console commands        │
└───────────────────────────────────────────────────────────────────────┘
```

- **The framework never contains project code.** It knows the project only through fixed
  locations: `custom/config.php`, `custom/app.php`, `custom/version.php`, `custom/Entity/` and
  the four traits in `custom/Traits/`.
- **The entry points contain no path into the framework.** They load Composer's autoloader and
  name the project directory. `lib/contentfly` could equally be installed under
  `vendor/areanet/contentfly`.
- `lib/contentfly/Classes/Kernel/Paths.php` is the single registry for these locations:
  `project()`, `custom()`, `data()`, `plugins()`, `package()` (= `lib/contentfly`).

---

## 4. How a web request is processed

```
index.php
  └─ Start::web(projectDir)                     Classes/Kernel/Start.php
       ├─ checks: project dir exists, custom/config.php readable,
       │          no second vendor tree in custom/vendor
       ├─ defines CONTENTFLY_PROJECT_DIR
       └─ require lib/contentfly/bootstrap-web.php
            ├─ require lib/contentfly/bootstrap.php
            │    ├─ load custom/config.php  → configuration
            │    ├─ new Application()        → container + HttpKernel
            │    ├─ register services        (database and ORM only if installed)
            │    ├─ load custom/app.php      → project routes, hooks, commands, providers
            │    └─ bind project routes
            ├─ trusted proxies, security headers, optional HTTP basic auth
            ├─ error handler (JSON error responses)
            ├─ CORS headers (after hook), OPTIONS catch-all
            ├─ mount framework routes: /api /auth /file /system
            └─ $app->run()  → Symfony HttpKernel handles the request
```

Details worth knowing:

- **"Installed" is a state derived from configuration.** As long as `DB_HOST` in
  `custom/config.php` still holds the placeholder `$SET_DB_HOST`, `$app['is_installed']` is
  false. No database or ORM services are registered, and routed requests answer `503` with
  the message `Contentfly is not installed. Run the installation: …`.
- **`Application`** (`Classes/Kernel/Application.php`) extends the container and wires Symfony's
  `HttpKernel`: `RouterListener` for matching, a custom `ControllerResolver` for
  `"service:method"` controller strings, and `RouteSecurityListener`, which runs a route's
  authentication callbacks on `kernel.controller`.
- **Hooks:** `$app->before()` maps to `kernel.request` (main request only), `$app->after()` to
  `kernel.response`, `$app->error()` to `kernel.exception`. A `before` hook that returns a
  `Response` ends the request.
- **Controller providers** (`Classes/Controller/Provider/…`) build a route collection per mount
  point. Their shared base `BaseControllerProvider` also registers the request middleware:
  the not-installed check, JSON body decoding and the `pim.controller.before.*` /
  `pim.controller.after.*` events.
- **Errors** become JSON responses with `message`, `type` and `status`. With `APP_DEBUG` on, a
  stack trace is added; non-JSON requests get an HTML error page.

## 5. How a console command is processed

```
bin/console.php
  └─ Start::console(projectDir)         defines APPCMS_CONSOLE
       └─ require lib/contentfly/bootstrap.php     (not bootstrap-web.php)
  ├─ if installed: add Doctrine ORM/DBAL commands
  └─ $app['console']->run()
       └─ event console.init → framework commands + project commands are added
```

There are no HTTP headers, no framework HTTP routes and no forced-SSL redirect in console
mode, and the ORM query and metadata caches are disabled. `bin/cli-config.php` exists only for
Doctrine's own `vendor/bin/doctrine` tool.

---

## 6. The framework package `lib/contentfly/`

| Path | Contents |
|---|---|
| `bootstrap.php` | Loads configuration, creates the application, registers all services, loads `custom/app.php` |
| `bootstrap-web.php` | HTTP-only setup: proxies, headers, error handler, CORS, framework routes, `run()` |
| `version.php` | Framework version (`APP_VERSION`) |
| `config.sample.php` | Sample project configuration |
| `composer.json` | Package manifest with the runtime dependencies |
| `Controller/` | `ApiController`, `AuthController`, `FileController`, `SystemController` |
| `Command/` | Built-in console commands (section 13) |
| `Entity/` | Built-in data model (section 10) |
| `Migration/` | A Rector rule that helps existing projects convert entity annotations to attributes. It is never loaded at runtime. |
| `Classes/` | Everything else, see below |

### `lib/contentfly/Classes/`

| Directory / file | Purpose |
|---|---|
| `Kernel/` | Runtime core: `Start` (entry), `Paths`, `Container`, `Application`, `Console`, `Command` (base class with `application()`), `ControllerProviderInterface`, `ConsoleEvents` / `ConsoleInitEvent` |
| `Kernel/Routing/` | `RouteCollector` (builds a route collection), `RouteEntry` (one route), `RouteSecurityListener` (runs per-route auth callbacks), `ControllerResolver` |
| `Controller/` | `BaseController` (gives controllers `$app` and the entity manager) |
| `Controller/Provider/` | `BaseControllerProvider` (middleware and `authenticate()`), `Route` |
| `Controller/Provider/Base/` | Route definitions: `Api…`, `Auth…`, `File…`, `System…ControllerProvider`, plus `CustomControllerProvider` for project routes |
| `Api.php` | The generic data engine behind `/api/*`: read, list, tree, write, delete, query, sync and schema, including permission checks |
| `Security/` | Authentication, tokens, throttling, login providers, field encryption (section 8) |
| `Permission.php`, `I18nPermission.php` | Entity-level and per-language permission checks |
| `Auth.php` | Current-user accessor |
| `Config.php` | **All configuration keys and their defaults** |
| `Config/` | `Factory` (configuration per host name), `Adapter` (configuration of the current host) |
| `Manager/` | Registries: `RouteManager`, `ConsoleManager`, `TypeManager`, `PluginManager` |
| `Type.php`, `Type/`, `Types/` | Field type system (section 11) |
| `Annotations/` | PHP attributes that select or configure field types: `Config`, `Select`, `Checkbox`, `Radio`, `ManyToMany`, `Permissions`, `I18nPermissions`, `Virtualjoin` |
| `Metadata/` | `MetadataReader` — single point for reading entity attribute metadata |
| `ORM/` | `EntityManagerFactory` (attribute drivers, proxies, caches), `Id/UuidGenerator`, quote strategy, the DQL function `FIND_IN_SET`, spatial `Point` type |
| `Events/` | `LoadMetadata` — Doctrine listener that adds an index on `modified` |
| `File/` | Storage backend (`Backend/FileSystem` → `data/files/`) and image processors (`Processing/Image` with GD, `Processing/Standard` for everything else) |
| `Exceptions/` | Exception types (`ContentflyException`, not-found, duplicate, …) |
| `Command/` | `CustomCommand` — base class for project commands |
| `Helper.php` | Entity name helpers and the seeding of base data |
| `Mailer.php` | PHPMailer wrapper |
| `Messages.php` | Message and status constants |
| `Plugin.php`, `Event.php` | Plugin base class; event object for `pim.*` events |

---

## 7. HTTP API

All framework routes are declared in `lib/contentfly/Classes/Controller/Provider/Base/`.
"Auth" means the route carries an authentication callback (`->before($checkAuth)`).

### `/api` — generic data access (`ApiController`, engine in `Classes/Api.php`)

| Method | Path | Auth |
|---|---|---|
| POST | `/api/single`, `/api/list`, `/api/tree`, `/api/tree2`, `/api/translations` | yes |
| POST | `/api/all`, `/api/deleted` (synchronisation) | yes |
| POST | `/api/insert`, `/api/update`, `/api/replace`, `/api/multiupdate`, `/api/delete` | yes |
| POST | `/api/count` | yes |
| POST | `/api/query` — admins only (000-000-0097) | yes |
| GET | `/api/schema` | yes |
| GET | `/api/config` | no |

Requests name the target entity by its short name (for example `PIM\User` or `Core\Example`
for `custom/Entity/Core/Example.php`).

### `/auth` — sessions (`AuthController`)

| Method | Path | Auth |
|---|---|---|
| POST | `/auth/login` | no |
| POST | `/auth/refresh` | no — validates the refresh token itself |
| GET | `/auth/logout` | yes |

### `/file` — files (`FileController`)

| Method | Path | Auth |
|---|---|---|
| POST | `/file/upload`, `/file/overwrite` | yes |
| GET | `/file/get/{id}` and variants with `s-{size}`, `{size}`, `{variant}`, `{alias}` | no |

How a file is delivered depends on `APP_FILE_MODE`. The default `redirect` answers with a
redirect to `data/files/<id>/<name>`, which the web server then serves as a static file.

### `/system` — administration (`SystemController`)

| Method | Path | Auth |
|---|---|---|
| POST | `/system/do` | yes, and the user must be an administrator |

The operation is selected by the body parameter `method`: `flushSchemaCache`,
`updateDatabase`, `listTokens`, `generateToken`, `addToken`, `deleteToken`.

### Other routes

- A catch-all route answers `OPTIONS` on any path with `204` (CORS preflight). Because it
  matches every path, any other method on an unknown path answers `405` on an installed
  instance. Before installation, routed requests answer `503` instead (section 4).
- **Project routes** come from `custom/app.php`. The template registers one:
  `POST /api/v1/example/bootstrap` → `Custom\Controller\Core\ExampleController::bootstrapAction`,
  without authentication.

---

## 8. Authentication and authorization

All classes are in `lib/contentfly/Classes/Security/` unless noted.

### Where a token is read from

`TokenSources` checks, in this order, and the first non-empty value wins:

1. `Authorization: Bearer …`
2. header `appcms-token`
3. header `X-XSRF-TOKEN`
4. query parameter `_token`
5. body parameter `_token` (`BodyExtractor`)

### How a token is validated

`TokenAuthenticator` wraps Symfony's `AccessTokenAuthenticator` with:

- **`TokenHandler`**, which decides by the token's shape:
  - **JWT** (three segments with a JSON header): verified with `SECURITY_JWT_SECRET` and an
    optional previous key selected by `kid`. Issuer `contentfly`, subject = user alias. It is
    rejected if its `jti` is in the `RevokedToken` table. JWTs are issued by `JwtAccessToken`.
  - **Opaque token**: looked up by its SHA-256 hash in `pim_token`. Expiry follows
    `APP_TOKEN_TIMEOUT` or the group's `tokenTimeout`, and is extended on use.
- **`UserLoader`**: loads the user by alias and requires it to be active.

`BaseControllerProvider::authenticate()` runs this authenticator for protected routes and sets
`$app['auth.user']` and `$app['auth.token']`.

### Login, refresh, logout (`lib/contentfly/Controller/AuthController.php`)

- **Login** checks the password with `password_verify`, or delegates to a login provider when
  the request names one in `loginManager`. On success it creates a token. With
  `tokenType=jwt`, the response contains a short-lived JWT plus a refresh token.
- **Refresh** exchanges a valid refresh token for a new JWT and rotates the refresh token.
- **Logout** deletes the opaque token and puts the JWT's `jti` on the revocation list.
- **Throttling:** `LoginThrottle` (Symfony RateLimiter). It counts failed
  attempts per alias and per client IP, with increasing waiting times, and answers `429`
  with `Retry-After`.
- **Client IP behind proxies:** `TrustedProxies` applies
  `APP_TRUSTED_PROXIES` / `APP_TRUSTED_HEADERS`.

### Login providers (external identity systems)

- A provider implements `LoginProvider` with one method,
  `authenticate(Request): ?ExternalIdentity`. It verifies against the external system and never
  touches the database.
- Providers are registered **by name** in `custom/app.php` via
  `$app['loginProviders']->register('<name>', fn)` (`LoginProviderRegistry`). A name that is not
  registered does not exist.
- The framework creates or updates the user (`UserProvisioning`) and maps groups
  (`GroupMapping`, configured in `SECURITY_PROVIDER_GROUPS`).
- Shipped providers: `LdapProvider` (search, then bind) and `OidcProvider` (userinfo endpoint).
  **Neither is registered by default.** The template registers only `ExampleProvider`
  (`custom/Classes/Authentication/`), which rejects every login unless the environment
  variable `CONTENTFLY_EXAMPLE_PROVIDER` is set.
- `UserExistenceCheck` is an optional second interface with `knowsIdentifier()`. It is used by
  the command `appcms:provider:sync` to deactivate users that the external system no longer
  knows.

### Permissions

- `User.isAdmin` grants everything.
- Other users belong to a `Group`. The group has one `Permission` row per entity with the
  levels `readable`, `writable`, `deletable` and `export`, each with the values `0` none,
  `1` own, `2` all, `3` group.
- The checks live in `Classes/Permission.php` and `Classes/I18nPermission.php`. They are called
  from `Classes/Api.php`, `FileController` and the relation field types.

### Field encryption

Fields marked `#[PIM\Config(encoded: true)]` are encrypted by `FieldEncryption`
(XChaCha20-Poly1305) with `SECURITY_CIPHER_KEY`. Legacy AES-256-CBC values remain
readable; `appcms:security:reencrypt` converts them.

---

## 9. How a project extends the framework (`custom/`)

| File / directory | What the project does there |
|---|---|
| `custom/config.php` | Overrides configuration defaults, optionally per host name. It contains `$SET_*` placeholders that `appcms:install` replaces. |
| `custom/app.php` | Registers services, routes, middleware, console commands and login providers |
| `custom/version.php` | Project version |
| `custom/Entity/` | Project entities (namespace `Custom\Entity\`, discovered recursively) |
| `custom/Traits/User.php`, `Group.php`, `File.php`, `Folder.php` | Extra fields on the built-in entities. The framework entities `use` these traits. |
| `custom/Controller/` | Controllers for project routes |
| `custom/Command/` | Console commands; they must extend `CustomCommand` and are named `custom:<name>` |
| `custom/Classes/` | Other project classes (the template has a login provider and two response helpers) |
| `custom/Views/` | A leftover e-mail layout template. Nothing renders it, since the framework no longer ships a template engine. |

The patterns used in `custom/app.php`:

```php
$app['my.service'] = function ($app) { return new MyService($app['orm.em']); };  // lazy service

$app['routeManager']->mount('api/v1/example/', '\Custom\Controller\Core\ExampleController')
    ->post('/bootstrap', false, 'bootstrapAction');     // (route, requires auth?, action)

$app->before(function (Request $request) { /* … */ });  // middleware
$app->after(function (Request $request, Response $response) { /* … */ });

$app['consoleManager']->addCommand(new \Custom\Command\ExampleCommand());
```

**Container rules** (`Classes/Kernel/Container.php`):
- A closure is a lazy factory. It runs on first access, and the result is then fixed.
- An unknown key throws an exception.

Keys a project can rely on:
- **Always:** `is_installed`, `debug`, `database`, `mailer`, `routeManager`, `consoleManager`,
  `request_stack`, `dispatcher`, `auth.user`, `orm.em` (null until installed), `loginProviders`.
- **Only when installed:** `db`, `dbs`.
- **After login:** `auth.token`.

All other keys are internal wiring.

---

## 10. Data model (`lib/contentfly/Entity/`)

| Entity | Table | Role |
|---|---|---|
| `Base`, `BaseSortable`, `BaseI18n`, `BaseI18nSortable`, `BaseUID` | — | Mapped superclasses. `Base` provides `id`, `created`, `modified`, owner and access fields. |
| `BaseTree`, `BaseI18nTree` | `pim_tree`, `pim_i18n_tree` | Tree base classes (joined inheritance) |
| `User` | `pim_user` | Accounts: alias, password hash, `isAdmin`, `isActive`, group, login provider |
| `Group` | `pim_group` | User groups, token timeout, language permissions |
| `Permission` | `pim_permission` | Per-entity rights of a group |
| `Token` | `pim_token` | Opaque and refresh tokens (hash only) |
| `RevokedToken` | `pim_revoked_token` | Revoked JWT ids |
| `File`, `Folder`, `Tag` | `pim_file`, `pim_folder`, `pim_tag` | File metadata and organisation |
| `ThumbnailSetting` | `pim_thumbnail_setting` | Image sizes |
| `Log` | `pim_log` | Change log (insert, update, delete) |
| `Option`, `OptionGroup` | `pim_option`, `pim_optiongroup` | Value lists for checkbox and radio fields |
| `Serializable` | — | Serialisation base |

Entity mapping uses Doctrine PHP attributes. `ORM/EntityManagerFactory` registers two attribute
drivers, `Areanet\PIM\Entity` → `lib/contentfly/Entity` and `Custom\Entity` → `custom/Entity`.
Plugins can add their own.

---

## 11. Field types (`Classes/Types/`)

Every entity property is handled by a **field type**. The type builds the property's entry in
`/api/schema`, converts values between database and API, validates writes and, for relations,
checks permissions.

- There are 21 built-in types: `string`, `textarea`, `integer`, `decimal`, `float`, `boolean`,
  `datetime`, `time`, `json`, `select`, `checkbox`, `radio`, `join`, `onejoin`,
  `joinbidirectional`, `multijoin`, `file`, `multifile`, `permissions`, `i18npermissions` and
  `virtualjoin`.
- A type is chosen from the Doctrine column or relation plus the attributes in
  `Classes/Annotations/`. `TypeManager` holds all registered types; for each property the
  matching type with the highest priority wins.
- The list of active types is `APP_SYSTEM_TYPES` in `Classes/Config.php`.

---

## 12. Configuration

- **Defaults:** every key and its default value is a public property of
  `lib/contentfly/Classes/Config.php`.
- **Project values:** `custom/config.php` creates a `Config`, sets values and registers it.
- **Per host:** `new Config('<hostname>', $default)` inherits from the default configuration
  and is selected by `SERVER_NAME`.
- **Secrets from the environment:** `custom/config.php` reads secrets from environment
  variables, optionally loaded from a `.env` file one level above the project directory.

| Group | Keys |
|---|---|
| Database | `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_GUID_STRATEGY` |
| Runtime | `APP_DEBUG`, `APP_TIMEZONE`, `WEB_ROOT`, `APP_CACHE_DRIVER`, `APP_ENABLE_SCHEMA_CACHE`, `APP_FILE_MODE` |
| HTTP | `APP_FORCE_SSL`, `APP_CS_POLICY`, `APP_ALLOW_*`, `APP_MAX_AGE`, `APP_HTTP_AUTH_USER` / `_PASS`, `APP_TRUSTED_PROXIES`, `APP_TRUSTED_HEADERS` |
| Tokens | `APP_TOKEN_TIMEOUT`, `APP_CHECK_TOKEN_TIMEOUT`, `SECURITY_JWT_SECRET`, `SECURITY_JWT_TTL`, `SECURITY_JWT_KEY_ID`, `SECURITY_JWT_SECRET_PREVIOUS`, `SECURITY_JWT_KEY_ID_PREVIOUS` |
| Encryption | `SECURITY_CIPHER_KEY` |
| Login providers | `SECURITY_PROVIDER_GROUPS`, `SECURITY_LDAP_*`, `SECURITY_OIDC_*` |
| Files | `FILE_PROCESSORS`, `FILE_IMAGE_MAX_PIXELS` |

---

## 13. Runtime directories, plugins, console commands

### `data/`

| Directory | Used for |
|---|---|
| `data/cache/` | Doctrine proxies (`doctrine/`), ORM query and metadata caches, login throttle state (`login-throttle/`), optional schema cache file |
| `data/files/` | Uploaded files and generated image variants, one directory per file id |
| `data/import/`, `data/temp/` | Placeholders; not used by the current code |

### `plugins/`

The Composer autoloader maps `Plugins\` → `plugins/`. The directory is not part of the
delivery. Plugins are never loaded automatically: a project calls
`$app['pluginManager']->register('<Name>')`, which loads `Plugins\<Name>\<Name>Plugin`, a class
extending `Classes/Plugin.php`. The template registers none.

### Console commands (`php bin/console.php list`)

| Command | Purpose |
|---|---|
| `appcms:install` | Writes database credentials into `custom/config.php`, creates the schema, seeds base data |
| `appcms:setup` | Seeds base data: creates the user `admin`, or resets an existing one, with password `admin` and administrator rights; creates the thumbnail sizes |
| `appcms:token:cleanup` | Removes expired tokens and obsolete revocation entries |
| `appcms:security:reencrypt` | Re-encrypts legacy encrypted field values |
| `appcms:provider:sync` | Deactivates provider users unknown to their external system |
| `orm:*`, `dbal:*` | Doctrine schema, cache and query commands, available once installed |
| `custom:example:command:run` | Template command of the project; does nothing |

---

## 14. Tests (`tests/`)

| Path | Purpose |
|---|---|
| `tests/Unit/` | Suite `unit`, 255 tests. It needs no database and covers kernel, container, routing, security classes, managers and entities. |
| `tests/Integration/` | Suite `integration`. It drives a running, installed instance over HTTP and the console, and reads the database directly. |
| `tests/Integration/IntegrationTestCase.php` | Base class: HTTP client, login helpers, database access, cleanup |
| `tests/Fixtures/` | Input and expected output for the migration rule tests |
| `tests/bootstrap.php` | Test bootstrap (autoloader, paths; does not start the application) |
| `tests/router.php` | Router for PHP's built-in web server, so that files on disk are served like under Apache |
| `tests/README.md` | Detailed instructions |

Integration tests **skip themselves** unless `CONTENTFLY_TEST_BASE_URL` is set. The other
variables:

| Variable | Meaning |
|---|---|
| `CONTENTFLY_TEST_ADMIN_PASS` | Admin password of the test instance |
| `CONTENTFLY_TEST_MAIL_TRAP` | Directory of a fake `sendmail`, so tests never send real mail |
| `CONTENTFLY_TEST_PROVIDER` | The value given to the server as `CONTENTFLY_EXAMPLE_PROVIDER` |
| `CONTENTFLY_TEST_DB_HOST` / `_PORT` / `_NAME` / `_USER` / `_PASSWORD` | Test database (defaults match `docker-compose.yml`) |
| `CONTENTFLY_TEST_PROJECT_DIR` | Installation directory, if it is not this checkout |

With `CI` set, `EnvironmentGuardTest` fails the run when the required variables are missing,
instead of letting every integration test skip.

### Tests that fail in this delivery

These tests read files that are not included (section 2). Measured on a copy with exactly the
delivered contents: **18 of 255 unit tests fail, all of them in these three classes.**

| Test class | Reads |
|---|---|
| `Tests\Unit\Ci\CiStepsTest` (4 tests) | CI shell scripts |
| `Tests\Unit\Migration\MigrationGuideTest` (3 tests) | Internal migration documentation |
| `Tests\Unit\Migration\RectorRuleTest` (11 of 19 tests) | The Rector configuration file (10 tests) and internal documentation (1 test) |

In the integration suite, `Tests\Integration\ContainerKeysTest::testTheListsMatchTheDevGuide`
reads internal documentation as well.

**The unit suite leaves two things behind in the project root.** Without its configuration
file, Rector creates a default `rector.php` when `RectorRuleTest` runs, and `PluginManagerTest`
leaves an empty `plugins/` directory. Neither is part of the delivery; delete both to return to
the delivered state.

---

## 15. Running it locally

```sh
# 1. Dependencies (creates vendor/)
composer install

# 2. Database (MySQL 8 on port 3307)
docker compose up -d

# 3. Install: writes custom/config.php, creates the schema, creates user "admin"
php bin/console.php appcms:install \
    --db-host=127.0.0.1 --db-port=3307 --db-name=contentfly \
    --db-user=contentfly --db-pass=contentfly \
    --db-strategy=guid --admin-password='<choose one>'

# 4. Web server (PHP built-in server; Apache uses .htaccess instead)
APP_ENV=production APP_DEBUG=0 SECURITY_JWT_SECRET='<at least 32 bytes>' \
  php -d display_errors=Off -S 127.0.0.1:8145 tests/router.php

# 5. Log in
curl -s -X POST http://127.0.0.1:8145/auth/login \
     -H 'Content-Type: application/json' \
     -d '{"alias":"admin","pass":"<password>"}'
```

- **`--db-port=3307` is required.** The installer defaults to `3306`, but the database
  container publishes `3307`.
- **`APP_DEBUG`:** in `custom/config.php` it defaults to *on* for the environments `dev`,
  `development`, `test` and `local`. `APP_ENV` itself defaults to `dev`. Set `APP_ENV` and
  `APP_DEBUG` explicitly for anything other than local development.

**Tests:**

```sh
./vendor/bin/phpunit --testsuite unit          # no database needed

CONTENTFLY_TEST_BASE_URL=http://127.0.0.1:8145 \
CONTENTFLY_TEST_ADMIN_PASS='<password>' \
  ./vendor/bin/phpunit --testsuite integration # needs the running instance from above
```

The full server setup used by the suite (mail trap, provider variable) is described in
`tests/README.md`.
