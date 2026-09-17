# Tests

> **This suite is the acceptance basis for the kernel swap.** The kernel swap counts as
> successful if the suite stays green **without any change to its substance**; adjusting a test
> is a change in behaviour and needs a justification. What exactly that means — and what the
> suite explicitly does **not** cover — is described in `an_project/docs/technical.md`, in the
> section on the test suite as the acceptance basis. Anyone changing a test here reads that first.

Two suites, deliberately kept separate:

| Suite | Directory | Requires |
|---|---|---|
| `unit` | `tests/Unit` | nothing — must be green on every checkout |
| `integration` | `tests/Integration` | the environment from `docker-compose.yml` and a completed installation |

The reason for the separation: if both lived together, the entire suite would come to a halt
as soon as no container is running. A suite that is frequently red for environmental reasons
stops being read.

**First: `composer install`.** PHPUnit lives in `require-dev`, and `vendor/` has not been in the
repo since story `006-003` — without this step there is no `./vendor/bin/phpunit`, and nothing
tells you why. The complete procedure is in `an_project/docs/runbook.md`.

```sh
composer install                              # FIRST — otherwise phpunit does not exist
./vendor/bin/phpunit                          # both suites
./vendor/bin/phpunit --testsuite unit         # without a database
./vendor/bin/phpunit --coverage-text          # requires Xdebug or PCOV
```

## Running the integration tests

They need a **running, installed** instance and are otherwise skipped cleanly:

```sh
# 0. Dependencies — without them there is neither phpunit nor an autoloader
composer install

# 1. Database and installation (see an_project/docs/runbook.md)
docker compose up -d
php bin/console.php appcms:install --db-host=127.0.0.1 --db-port=3307 \
    --db-name=contentfly --db-user=contentfly --db-pass=contentfly \
    --db-strategy=guid --admin-password='dev-only-secret'

# 2. Mail trap — so that no run sends a mail to the outside world
TRAP=/tmp/contentfly-mailtrap
mkdir -p "$TRAP"
printf '#!/bin/sh\ncat >> "$(dirname "$0")/outbox.log"\nexit 0\n' > "$TRAP/sendmail"
chmod +x "$TRAP/sendmail"

# 3. Test server — with router, mail trap and no error output in the response stream
#
#    SECURITY_JWT_SECRET and CONTENTFLY_EXAMPLE_PROVIDER go to the APPLICATION, not to the
#    suite: the first lets it issue JWTs (013-003), the second feeds the provider
#    template (013-004). Without them the login issues no JWT and the template lets
#    nobody in — both correct, but then the corresponding tests have nothing to measure.
#    APP_ALLOW_ORIGIN is the one CORS origin the server allows (000-000-0039); CorsApiTest
#    checks that it is named and a foreign one is not.
#    APP_FILE_MAX_UPLOAD_SIZE is 1 MiB, below PHP's default upload_max_filesize of 2M, so FileApiTest
#    reaches the application's limit and not the server's (000-000-0042).
APP_ENV=production APP_DEBUG=0 \
  SECURITY_JWT_SECRET=dev-only-jwt-secret-long-enough-32b \
  CONTENTFLY_EXAMPLE_PROVIDER='external-one:dev-only-provider-secret:CN=Editorial' \
  APP_ALLOW_ORIGIN=https://allowed.example \
  APP_FILE_MAX_UPLOAD_SIZE=1048576 \
  php -d display_errors=Off -d log_errors=On -d sendmail_path="$TRAP/sendmail" \
      -S 127.0.0.1:8145 tests/router.php &

# 4. Suite against this instance
#
#    The CONTENTFLY_TEST_* values for JWT, provider and origin must match those above: the
#    suite presents what the server expects.
CONTENTFLY_TEST_BASE_URL=http://127.0.0.1:8145 \
CONTENTFLY_TEST_ADMIN_PASS=dev-only-secret \
CONTENTFLY_TEST_MAIL_TRAP="$TRAP" \
CONTENTFLY_TEST_JWT_SECRET=dev-only-jwt-secret-long-enough-32b \
CONTENTFLY_TEST_PROVIDER='external-one:dev-only-provider-secret:CN=Editorial' \
CONTENTFLY_TEST_ALLOWED_ORIGIN=https://allowed.example \
  ./vendor/bin/phpunit

# 5. Restore the template — step 1 wrote credentials into it
git checkout HEAD -- custom/config.php
```

Without step 2 and `CONTENTFLY_TEST_MAIL_TRAP`, `MailTrapTest` skips itself; in a pipeline the
run then turns red (see *The guard* further below). The detailed version of the mail trap is
in the section below.

**`tests/router.php` is not optional.** Without it, the built-in server sends *every* request
through `index.php` — including the one for a file that exists on disk. Apache does not do
that, and file delivery depends on exactly this.

**`APP_DEBUG=0`** prevents the debug exception handler from masking the application's
responses. The rest of it was fixed with `000-000-0006`: since then, a PHP error arrives as a
JSON response from the application and no longer as Symfony's "Whoops" page. `APP_DEBUG=0`
still stays set, because it is the production setting and the suite is meant to measure what
an installation delivers.

**`display_errors=Off` is not cosmetic.** PHP writes a deprecation directly into the response
stream. If that happens before the kernel sets the status code, the headers are already on
their way — and the response carries `200`, although the application means `405` or `500`. This
still applies unchanged to the Symfony kernel from epic `009`; the order output-before-headers
is a property of PHP, not of the framework. On the first
CI run, six tests that were green locally failed because of this; with `display_errors=Off`
all 232 pass. It is also the production setting: an instance that delivers deprecations
reveals file paths to every caller. That the framework does **not enforce** it with
`APP_DEBUG=0` is a separate finding — `000-000-0018`.

`log_errors=On` ensures that the deprecations do not disappear but end up in the server log.
That is the source the "0 deprecations" gate from `006-005` later reads from.

## The guard against silent skips

`tests/Integration/EnvironmentGuardTest.php` addresses the actual risk of a pipeline:
**a green suite that checked nothing.** If `CONTENTFLY_TEST_BASE_URL` is missing, all
integration tests skip themselves, PHPUnit reports `OK, but some tests were skipped` — and the
job turns green.

The guard therefore checks: if `CI` is set (GitHub Actions and most others do this on their
own), `CONTENTFLY_TEST_BASE_URL`, `CONTENTFLY_TEST_MAIL_TRAP` and
`CONTENTFLY_TEST_ADMIN_PASS` **must** be present. If one is missing, the run is red, and the
message names the variable and why it is not waved through.

Three further checks run **locally as well**, as soon as the variables are set: that something
actually responds at the base address, that the **test database** is reachable *and
installed*, and that the mail trap contains an executable catch script. A set variable says
nothing about whether anything is running behind it.

The database check was added later, after the case had actually occurred: with the container
stopped, the suite reported **91 errors** — nothing but `PDOException: Connection
refused` from individual tests, and not one of them said that the database was simply missing.

| `CI` | Variables | Result |
|---|---|---|
| not set | missing | green, integration tests skipped |
| not set | set | green, everything runs |
| `true` | missing | **red** (exit 1), message names the variable |
| `true` | set | green, everything runs |

It deliberately does **not** inherit from `IntegrationTestCase` — otherwise it would skip itself
under exactly the conditions it warns about. What it does not protect against: a single
`markTestSkipped()` that someone adds to a test. It checks the preconditions of an
integration run, not every conceivable skip.

## In the pipeline

`.github/workflows/pipeline.yml` runs exactly this procedure; the steps live in `tools/ci/` so that they can be
**replayed locally in Docker** — a pipeline definition whose steps can only be tried out in the
pipeline is useless when hunting down a bug.

The test run itself:

```sh
sh tools/ci/install-php-extensions.sh    # pdo_mysql, gd, ldap and unzip
sh tools/ci/install-composer.sh          # only needed where composer is still missing
composer install                          # since 006-003 the only source of the tree
sh tools/ci/prepare-test-environment.sh  # wait, install, mail trap, server
./vendor/bin/phpunit
sh tools/ci/deprecations-pruefen.sh      # reads the server log, NOT the output above
```

**The last step is part of it, even if PHPUnit was red.** The workflow therefore runs PHPUnit with
`continue-on-error` and applies its result in a later step — otherwise the job would stop at the
first failure, and the deprecation gate would be blind precisely when the most has happened.

The two checks of the `check` stage need neither a database nor a test server:

```sh
sh tools/ci/audit.sh                     # composer audit --locked, blocking
sh tools/ci/audit-ausnahmen-pruefen.sh   # reports exceptions that no longer apply
./vendor/bin/phpstan analyse --memory-limit=512M   # non-blocking
```

What these gates check and what to do when they find something is described in
`an_project/docs/deployment.md` under *Die Gates*.

### Testing against an installation outside the repo

**Since `007-001-0005`.** If a project obtains the framework as a package, the application no
longer lives in the same tree as the suite. `CONTENTFLY_TEST_PROJECT_DIR` then says where it is:

```sh
CONTENTFLY_TEST_BASE_URL=http://127.0.0.1:8171 \
CONTENTFLY_TEST_PROJECT_DIR=/path/to/project \
./vendor/bin/phpunit
```

Two things depend on it that were previously silently the suite's own tree: the
`data/` directory, which is emptied between tests, and `bin/console.php`, which some tests
call. **One setting for both, not two** — two could drift apart, and then a run would test two
different installations without noticing.

**Without the variable, everything stays as before.** That is the normal case and the only one
the pipeline knows.

**If a step silently stalls, that is a bug in the script, not in the tool.** Since
`000-000-0029`, every step that redirects its output prints the last lines of its log on
failure; how that works and why is described in `tools/ci/schritt.sh` and in
`an_project/docs/deployment.md` in the section on failing steps.

## The mail trap

**No test run may send a mail** — and that must be proven, not assumed.
For this, the test server gets a catch script as its `sendmail_path`:

```sh
TRAP=/tmp/contentfly-mailtrap
mkdir -p "$TRAP"
cat > "$TRAP/sendmail" <<'SCRIPT'
#!/bin/sh
# Catches everything mail() wanted to deliver. Delivers NOTHING.
cat >> "$(dirname "$0")/outbox.log"
echo "--- END MAIL ---" >> "$(dirname "$0")/outbox.log"
exit 0
SCRIPT
chmod +x "$TRAP/sendmail"

# Test server with the redirect
APP_ENV=production APP_DEBUG=0 \
  php -d sendmail_path="$TRAP/sendmail" -S 127.0.0.1:8145 tests/router.php &

# Suite with the path to the trap
CONTENTFLY_TEST_BASE_URL=http://127.0.0.1:8145 \
CONTENTFLY_TEST_ADMIN_PASS=dev-only-secret \
CONTENTFLY_TEST_MAIL_TRAP="$TRAP" \
  ./vendor/bin/phpunit
```

`MailTrapTest` checks the safeguard itself, in both directions:

- **Does the script catch?** The test deliberately triggers it once via a separate PHP process
  and cuts the entry back out afterwards.
- **Does the server use it?** Via `/__test/sendmail-path` — a diagnostic path that
  `tests/router.php` answers and that exists in no installation. If the server runs without
  the redirect, the test fails and names the real MTA.

**Without `CONTENTFLY_TEST_MAIL_TRAP` the test is skipped, not waved through.** That is
intentional: as long as the proof is missing, the safeguard counts as unverified.

> **The original reason is gone, the safeguard stays.** `/api/mail` was the only endpoint with
> external effect and was removed with `000-000-0016` — since the jump to PHP 8 it had not been
> sending anything anyway. The trap never depended on it: `$app['mailer']` remains available to
> projects, and `custom/app.php` is the template into which they build it. Dismantling a
> safeguard together with its first reason would mean giving it up at the very moment nobody is
> looking anymore.

### 4. Restoring the template — a fixed step, not an optional extra

The installation from step 1 writes host, user and password into `custom/config.php` —
a file that **is versioned in the repo**, because it is the template. The test run is only
finished once it is a template again:

```sh
git checkout HEAD -- custom/config.php
```

**The `HEAD` matters.** If the file is already staged, `git checkout -- <path>` restores it
from the *index* and writes the installed version into the working tree again — it
looks like a restore and is not one.

So that nobody has to remember this, there are two safety nets: the `pre-commit` hook from
`tools/hooks/` (activate once with `git config core.hooksPath tools/hooks`) and the pipeline job
`check:template-config`. Both call `tools/check-template-config.sh` and report the same thing.
The hook catches earlier, the job always catches. Setup: `an_project/docs/runbook.md`.

## Creating a new integration test file

Inherit from `Tests\Integration\IntegrationTestCase` — not from PHPUnit's `TestCase`. The base
class provides what every file would otherwise have to rebuild itself:

| Method | Purpose |
|---|---|
| `login()` | logs in again, returns a fresh token |
| `token()` | returns a token and keeps it for the test class |
| `postJson($path, $data, $token = null)` | → `[status, body as array, headers]` |
| `get($path, $token = null)` | → `[status, body as string, headers]` |
| `header($headers, $name)` | reads a single header, e.g. `Location` |
| `pdo()` | connection to the test database |
| `deleteAfterTest($table, $id)` | registers a row that `tearDown()` removes |
| `deleteDirectoryAfterTest($path)` | registers a directory that `tearDown()` removes along with its contents |
| `createTestUser($permissions, $group)` | creates a group, a non-admin and permissions → `[token, user id, group id]` |

Skipping without `CONTENTFLY_TEST_BASE_URL` is also handled by the base class — no separate
`setUp()` is needed for that. Anyone who writes one calls `parent::setUp()`.

```php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

class ExampleApiTest extends IntegrationTestCase
{
    public function testSomething(): void
    {
        [$status, $body] = $this->postJson('/api/single', array(...), $this->token());
        $this->assertSame(200, $status);
    }
}
```

### Tests with permissions

`createTestUser()` creates, in a single call, a group, a non-admin in it and their
entity permissions, and logs them in:

```php
use Areanet\PIM\Entity\Permission;

[$token] = $this->createTestUser(array(
    'PIM\\Tag' => array('readable' => Permission::ALL, 'writable' => Permission::OWN),
));
```

Missing keys are `Permission::NONE`. The second parameter can be used to set
group fields (`apiQueryEnabled`, `languages`).

> **The levels must be passed as constants, not as numbers.** They are not
> ordered ascendingly: `NONE` is 0, `OWN` is 1, `ALL` is 2 and `GROUP` is 3. Anyone who reads
> them as a ranking is mistaken.

**Test data is created via `pdo()`, not via the write endpoints.** A read test whose
precondition goes through a path it does not itself check loses its significance —
and story `008-001` is explicitly not supposed to depend on `008-002`. The credentials come from
`CONTENTFLY_TEST_DB_*`; the default values match the `docker-compose.yml`.

> **The base class is loaded quite normally via the autoloader.** `composer.json` maps `Tests\` to
> `tests/`, in `autoload-dev` — so test code does not end up in the deployment artifact. A separate
> `require_once` in `tests/bootstrap.php` has not been needed since `006-004-0004`; before that,
> the mapping lived in the **project** manifest and pointed to `Custom\Tests\`, a namespace that
> no test file ever used.

## What `tests/bootstrap.php` does — and what it does not

It does **not** load `lib/contentfly/bootstrap.php`. That file builds the complete application,
requires a configured database and starts a session — for a test that checks a
single class, that is neither necessary nor desirable. The kernel switch from epic `009`
did not change this; only the application is now called
`Areanet\PIM\Classes\Kernel\Application` instead of `Silex\Application`.

Instead: both autoloaders, `ROOT_DIR`, the version files and the constants that
entity classes already read in their annotations while loading (`APPCMS_ID_TYPE` and related ones).
Without them, even reading the metadata fails.

A test that needs the full application builds it itself — and therefore belongs in
`tests/Integration`.

## Time zone

`phpunit.xml.dist` pins `date.timezone` to UTC: the same zone in which the API delivers its
timestamps. The CLI switch does **not** override this — PHPUnit applies the
configuration block afterwards, and PHP ignores `TZ` as long as `date.timezone` is set.
Both fail silently.
