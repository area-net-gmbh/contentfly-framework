<?php
use Custom\Classes\AppLoginManager;
use Custom\Classes\Service\Bootstrap\SecretsCheck;

// SECURITY — Boot-time secrets validation (TOP-1).
// In production/staging: refuses to boot if any required secret is missing or
// matches the committed dev fallback. In dev (default): logs warnings so the
// local docker stack keeps working without manual env setup.
$secretsWarnings = (new SecretsCheck())->check()->getWarnings();
foreach ($secretsWarnings as $w) {
    error_log($w);
}

// Sentry error monitoring
if (!empty($_ENV['SENTRY_DSN'])) {
    \Sentry\init([
        'dsn'         => $_ENV['SENTRY_DSN'],
        'environment' => $_ENV['APP_ENV'] ?? 'production',
        'release'     => $_ENV['APP_VERSION'] ?? '1.0.0',
    ]);
}

// Default so FeatureGateMiddleware can safely read auth.jwt even if no token is present
$app['auth.jwt'] = null;

// PERFORMANCE / CONCURRENCY — release the PHP session write-lock for JWT API calls.
//
// The framework starts the native PHP session on EVERY request (Auth::init() in
// lib/contentfly/bootstrap.php, which runs immediately BEFORE this file is
// required), and PHP holds an exclusive flock on the session file until the
// script ends. All browser tabs share one PHPSESSID, so concurrent /api/v1/
// calls serialize behind that lock — the first user-detail request after login
// hangs (pending, then a delayed empty 200) behind the page-load burst.
//
// The custom API authenticates exclusively via the JWT Bearer header
// (AuthMiddleware) and never reads $app['session']; only the legacy /api PIM-CMS
// admin still relies on it. So for /api/v1/ requests we close the session
// IMMEDIATELY here — at require time, right after Auth::init() opened it and
// BEFORE this file registers its many providers/routes, bindRoutes() runs and
// the kernel dispatches. Releasing here instead of in a before-middleware frees
// the lock roughly one full bootstrap earlier, which is what actually lets
// concurrent API requests run in parallel (measured: shared-session concurrency
// rose from ~2.8x toward the ~3.7x ceiling of unshared sessions).
//
// Guarded so it is a no-op for the legacy session-based /api PIM-CMS, for web
// requests, and for CLI (console) where there is no REQUEST_URI / session.
$usabiqApiPath = isset($_SERVER['REQUEST_URI'])
    ? (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '')
    : '';
if (str_starts_with($usabiqApiPath, '/api/v1/')
    && isset($app['session'])
    && $app['session']->isStarted()
) {
    $app['session']->save(); // -> session_write_close(): releases the file lock
}

// SECURITY — TOP-2.3: trust ONLY the known reverse-proxy range so getClientIp()
// resolves the real client from X-Forwarded-For (login rate-limit + audit IPs).
// Static, process-wide setter — applies to every Request this process handles,
// including the login path. We trust only X-Forwarded-For (not Host/Proto), so
// host/scheme behaviour (CORS, tenant subdomain resolution) is unchanged.
\Symfony\Component\HttpFoundation\Request::setTrustedProxies(
    \Custom\Classes\Helper\TrustedProxyConfig::resolve(),
    \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_FOR
);

// SECURITY (M1): make the framework master-password backdoor inert regardless of
// env/config. Adapter::getConfig() returns the shared singleton Config for the
// active host (fixed at bootstrap before this file), which the lib AuthController
// reads too. Defense-in-depth behind the before-hook below.
\Areanet\PIM\Classes\Config\Adapter::getConfig()->APP_MASTER_PASSWORD = null;

$app->register(new \Custom\Provider\LicenseServiceProvider());

$app['survey.notification'] = function($app) {
    return new \Custom\Classes\Service\Survey\SurveyNotificationService($app);
};

$app['survey.registry'] = function($app) {
    $registry = new \Custom\Classes\Service\Survey\SurveyTypeRegistry($app['license.checker']);
    $registry->register(new \Custom\Classes\Service\Survey\Jtbd\JtbdOdiTypeHandler($app));
    $registry->register(new \Custom\Classes\Service\Survey\Jtbd\JtbdStoryTypeHandler($app));
    $registry->register(new \Custom\Classes\Service\Survey\Jtbd\JtbdSwitchTypeHandler($app));
    $registry->register(new \Custom\Classes\Service\Survey\Jtbd\JtbdNeedsTypeHandler($app));
    $registry->register(new \Custom\Classes\Service\Survey\Questionnaire\QuestionnaireTypeHandler($app));
    $registry->register(new \Custom\Classes\Service\Survey\UeqPlus\UeqPlusTypeHandler($app));
    $registry->register(new \Custom\Classes\Service\Survey\Sus\SusTypeHandler($app));
    return $registry;
};

$app['stripe.checkout'] = function($app) {
    return new \Custom\Classes\Service\Stripe\StripeCheckoutService($app['orm.em']);
};

$app['stripe.webhook'] = function($app) {
    return new \Custom\Classes\Service\Stripe\StripeWebhookService($app['orm.em']);
};

$app['template.service'] = function($app) {
    return new \Custom\Classes\Service\Survey\TemplateService($app['orm.em'], $app);
};

$app['audit.log'] = function($app) {
    return new \Custom\Classes\Service\Core\AuditLogService($app);
};

$app['rate.limiter'] = function($app) {
    return new \Custom\Classes\Service\Core\RateLimiterService($app);
};

$app['ai.service'] = function($app) {
    return new \Custom\Classes\Service\AI\AIAssistService($app);
};

$app['ai.prompts'] = function($app) {
    $registry = new \Custom\Classes\Service\AI\AIPromptRegistry();
    $registry->register(new \Custom\Classes\Service\AI\Prompts\OdiEditorPrompt());
    $registry->register(new \Custom\Classes\Service\AI\Prompts\StoryEditorPrompt());
    $registry->register(new \Custom\Classes\Service\AI\Prompts\SwitchEditorPrompt());
    $registry->register(new \Custom\Classes\Service\AI\Prompts\QuestionnaireEditorPrompt());
    $registry->register(new \Custom\Classes\Service\AI\Prompts\ForceInterpretationPrompt());
    $registry->register(new \Custom\Classes\Service\AI\Prompts\StoryThemeExtractorPrompt());
    $registry->register(new \Custom\Classes\Service\AI\Prompts\SurveyDescriptionPrompt());
    $registry->register(new \Custom\Classes\Service\AI\Prompts\WelcomeTextPrompt());
    $registry->register(new \Custom\Classes\Service\AI\Prompts\TitleSuggestionsPrompt());
    $registry->register(new \Custom\Classes\Service\AI\Prompts\PrivacyNoticePrompt());
    $registry->register(new \Custom\Classes\Service\AI\Prompts\StatementImprovementPrompt());
    return $registry;
};

$app['ai.provider.factory'] = function($app) {
    return new \Custom\Classes\Service\AI\AIProviderFactory($app);
};

$app['insight.generation'] = function($app) {
    return new \Custom\Classes\Service\Insight\InsightGenerationService($app);
};

$app['insight.pii_safe_builder'] = function($app) {
    return new \Custom\Classes\Service\Insight\PiiSafeContextBuilder($app);
};

$app['insight.clustering'] = function($app) {
    return new \Custom\Classes\Service\Insight\InsightClusteringService($app);
};

$app['insight.jtbd'] = function($app) {
    return new \Custom\Classes\Service\Insight\JtbdInsightService($app);
};

$app['insight.segment'] = function($app) {
    return new \Custom\Classes\Service\Insight\SegmentInsightService($app);
};

$app['insight.recommendations'] = function($app) {
    return new \Custom\Classes\Service\Insight\RecommendationService($app);
};

$app['insight.recommendation_feedback'] = function($app) {
    return new \Custom\Classes\Service\Insight\RecommendationFeedbackService($app);
};

/**
 * DECISION ENGINE — Spec 24 (scoring + prioritisation + executive summary)
 */
$app['decision.adapters'] = function($app) {
    return [
        new \Custom\Classes\Service\Decision\Adapter\ClusterItemAdapter(),
        new \Custom\Classes\Service\Decision\Adapter\JtbdItemAdapter(),
        new \Custom\Classes\Service\Decision\Adapter\SegmentItemAdapter(),
        new \Custom\Classes\Service\Decision\Adapter\RecommendationItemAdapter(),
    ];
};

$app['decision.dimensions'] = function($app) {
    return [
        new \Custom\Classes\Service\Decision\Dimension\ImpactDimension(),
        new \Custom\Classes\Service\Decision\Dimension\FrequencyDimension(),
        new \Custom\Classes\Service\Decision\Dimension\SeverityDimension(),
        new \Custom\Classes\Service\Decision\Dimension\ConfidenceDimension(),
        new \Custom\Classes\Service\Decision\Dimension\SegmentRelevanceDimension(),
    ];
};

$app['decision.classifiers'] = function($app) {
    return [
        new \Custom\Classes\Service\Decision\Classifier\ProblemClassifier(),
        new \Custom\Classes\Service\Decision\Classifier\OpportunityClassifier(),
        new \Custom\Classes\Service\Decision\Classifier\QuickWinClassifier(),
        new \Custom\Classes\Service\Decision\Classifier\RiskClassifier(),
        new \Custom\Classes\Service\Decision\Classifier\SegmentIssueClassifier(),
        new \Custom\Classes\Service\Decision\Classifier\ResearchNeedClassifier(),
    ];
};

$app['decision.scoring'] = function($app) {
    return new \Custom\Classes\Service\Decision\InsightScoringService($app);
};

$app['decision.priority'] = function($app) {
    return new \Custom\Classes\Service\Decision\PriorityEngine($app);
};

$app['decision.summary.generator'] = function($app) {
    return new \Custom\Classes\Service\Decision\Generator\TemplateExecutiveSummaryGenerator();
};

$app['decision.summary'] = function($app) {
    return new \Custom\Classes\Service\Decision\DecisionSummaryService($app);
};

$app['rag.indexer'] = function($app) {
    return new \Custom\Classes\Service\Rag\RagIndexer($app);
};

$app['rag.search'] = function($app) {
    return new \Custom\Classes\Service\Rag\VectorSearchService($app);
};

$app['rag.session'] = function($app) {
    return new \Custom\Classes\Service\Rag\RagSessionService($app);
};

/**
 * PUBLIC API (spec 096) — key generation/management + request-time key auth.
 */
$app['public.api.key_service'] = function($app) {
    return new \Custom\Classes\Service\Public\ApiKeyService($app);
};

$app['public.api.resolver'] = function($app) {
    return new \Custom\Classes\Service\Public\ApiKeyResolver($app);
};

/**
 * OUTBOUND WEBHOOKS (spec 096 Phase 3) — emit() enqueues deliveries in-request
 * (fail-open); the `usabiq:webhooks:dispatch` cron signs + sends them. The
 * SSRF guard validates every target URL before any HTTP request is made.
 */
$app['webhook.url_guard'] = function($app) {
    return new \Custom\Classes\Service\Public\WebhookUrlGuard();
};

$app['webhook.dispatcher'] = function($app) {
    return new \Custom\Classes\Service\Public\WebhookDispatcher($app);
};

/**
 * SSO (spec 062 §D.6.9 / task 125) — the single write path for SsoConnection.
 * Registered so the SAML core (task 126) and the session bridge (task 127)
 * resolve connections through the same validated service instead of hitting the
 * repository directly, and so `sso.crypto` stays the ONLY reader of the SP
 * private key.
 */
$app['sso.config'] = function($app) {
    return new \Custom\Classes\Service\Sso\SSOConfigService($app);
};

$app['sso.crypto'] = function($app) {
    return new \Custom\Classes\Service\Sso\SsoConnectionCrypto($app);
};

// Task 126 — SAML core. `sso.acs` validates an assertion and returns a
// SamlValidationResult; it never issues a session (that is task 127, which
// consumes this result rather than re-parsing the XML).
$app['sso.acs'] = function($app) {
    return new \Custom\Classes\Service\Sso\SamlAcsService($app);
};

$app['sso.metadata'] = function($app) {
    return new \Custom\Classes\Service\Sso\SamlMetadataService($app);
};

// Task 127 — turns a validated assertion into a session. Registered so task 128
// (lifecycle hooks) and any future OIDC path resolve the same bridge rather than
// re-deriving subject → account → session.
$app['sso.session_bridge'] = function($app) {
    return new \Custom\Classes\Service\Sso\SSOSessionBridge($app);
};

$app['sso.landing'] = function($app) {
    return new \Custom\Classes\Service\Sso\SsoLandingUrlResolver($app);
};

// Task 128 — lifecycle hooks (ADR §D5/§D6). `sso.identity_topup` extends an
// existing subject link to a membership created by an invitation; the session
// bridge deliberately never does this at login time. `sso.owner_cleanup` deletes
// the connections of a deleted agency/tenant, which `ownerId` cannot express as
// a foreign key.
$app['sso.identity_topup'] = function($app) {
    return new \Custom\Classes\Service\Sso\SsoIdentityTopUpService($app);
};

$app['sso.owner_cleanup'] = function($app) {
    return new \Custom\Classes\Service\Sso\SsoOwnerCleanupService($app);
};

// Service used by StateGateMiddleware to detect tenants with unaccepted legal renewals (TOP-3)
$app['legal.renewal.checker'] = function($app) {
    return new \Custom\Classes\Service\Legal\LegalRenewalChecker($app);
};

// Central chokepoint for automated lifecycle / activity mail (spec 079).
$app['lifecycle.mail.dispatcher'] = function($app) {
    return new \Custom\Classes\Service\Notification\LifecycleMailDispatcher($app);
};

// Rule registry — the `usabiq:lifecycle:run` cron iterates these. Register
// further rules (onboarding, re-engagement, milestones, recap, …) here as the
// mail catalogue ships. Spec 079 §6.2/§6.3.
$app['lifecycle.mail.engine'] = function($app) {
    $engine = new \Custom\Classes\Service\Notification\LifecycleMailEngine($app);
    // Trial drip 7d → 3d → 1d (spec 080 §3.1); distinct dedup ledger keys per stage.
    $engine->register(new \Custom\Classes\Service\Notification\Rule\TrialEnding7dRule($app));
    $engine->register(new \Custom\Classes\Service\Notification\Rule\TrialEndingReminderRule($app));
    $engine->register(new \Custom\Classes\Service\Notification\Rule\TrialEnding1dRule($app));
    // …and the one that closes the drip: the trial actually lapsed and the
    // tenant is now on Free (task 138). Fires off the downgrade instant, not
    // the plan window — TrialExpiryChecker may transition days later.
    $engine->register(new \Custom\Classes\Service\Notification\Rule\TrialEndedRule($app));
    $engine->register(new \Custom\Classes\Service\Notification\Rule\QuotaAlertRule($app));
    // Campaign-Waves Anti-Churn nudges (B2B-Roadmap 2.6) — both MARKETING-class,
    // opt-out-able via the campaign_reminders / campaign_insights topics.
    $engine->register(new \Custom\Classes\Service\Notification\Rule\CampaignWaveRerunReminderRule($app));
    $engine->register(new \Custom\Classes\Service\Notification\Rule\CampaignWaveDeltaDigestRule($app));
    return $engine;
};

// Real-time hook seam for event-triggered lifecycle mails (milestones, etc.).
$app['lifecycle.mail.events'] = function($app) {
    return new \Custom\Classes\Service\Notification\LifecycleMailEvents($app);
};

$app['jwt.globalSecret'] = AppLoginManager::getJwtSecret();

// SECURITY (M1): seal the legacy contentfly surfaces that bypass usabiq's hardened
// auth. High priority (512) so blocked requests short-circuit before the other
// security before-hooks. Path- + raw-body-based only, so it is independent of the
// framework's JSON-decode middleware ordering.
$app->before(function (\Symfony\Component\HttpFoundation\Request $request) use ($app) {
    if ($request->getMethod() === 'OPTIONS') {
        return; // never block CORS preflight
    }
    $path = $request->getPathInfo();

    // 1) Kill the entire bare /api/* PIM-CMS surface (login/single/list/query/insert/
    //    update/delete/config/schema/…). usabiq lives only under /api/v1/; nothing
    //    calls bare /api/* (verified). 404 is neutral — reveals no endpoint.
    if ($path === '/api' || (strncmp($path, '/api/', 5) === 0 && strncmp($path, '/api/v1/', 8) !== 0)) {
        return new \Symfony\Component\HttpFoundation\Response('', 404);
    }

    // 2) Force every POST /auth/login through the hardened AppLoginManager. The lib
    //    else-branch (unscoped lookup + APP_MASTER_PASSWORD + user enumeration + no
    //    rate-limit) runs only when loginManager is absent; the frontend always
    //    sends 'AppLoginManager', so this is transparent to real logins.
    if ($path === '/auth/login' && $request->getMethod() === 'POST') {
        // Resolve exactly as the lib dispatcher does (AuthController::loginAction ->
        // $request->get('loginManager')): attributes → QUERY → body. Reading only the
        // body bag here let `?loginManager=X` with a well-formed body slip past the
        // gate and still reach the else-branch, since the query wins at dispatch time.
        $loginManager = $request->get('loginManager');
        if ($loginManager === null) {
            // Body not JSON-decoded into the request bag yet at this priority.
            $raw = json_decode((string) $request->getContent(), true);
            $loginManager = is_array($raw) ? ($raw['loginManager'] ?? null) : null;
        }
        if ($loginManager !== 'AppLoginManager') {
            // Mirror the lib login's generic 401 so probing reveals nothing.
            return new \Symfony\Component\HttpFoundation\JsonResponse(
                ['message' => 'Benutzername und/oder Passwort fehlerhaft.'], 401
            );
        }
    }
}, 512);

// SECURITY (M5): generic JSON backstop for UNCAUGHT throwables on /api/v1.
// MUST declare ZERO parameters — the vendored Silex ExceptionListenerWrapper is
// broken on PHP 8 and silently skips any error handler that declares a param
// (verified against this stack). A zero-param handler bypasses that check; the
// exception/request/code arrive via func_get_args(). Returns the standard
// {success:false} envelope + a correlation id and logs the real exception, so SQL
// fragments / provider details / file paths never reach the client.
$app->error(function () use ($app) {
    $args    = func_get_args();
    $e       = $args[0] ?? null;   // \Throwable
    $request = $args[1] ?? null;   // Symfony Request
    $code    = $args[2] ?? 500;

    $path = $request ? $request->getPathInfo() : '';
    if (strncmp($path, '/api/v1/', 8) !== 0) {
        return null; // not our surface — leave framework/web handling intact
    }
    // Genuine 4xx routing/HTTP exceptions carry no sensitive detail — pass through.
    if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
        && $code >= 400 && $code < 500) {
        return null;
    }

    $correlationId = bin2hex(random_bytes(8));
    if ($e instanceof \Throwable) {
        error_log(sprintf(
            '[unhandled.exception] cid=%s path=%s %s: %s @ %s:%d',
            $correlationId, $path, get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()
        ));
        \Custom\Classes\Service\Core\StructuredLogger::error('unhandled.exception', [
            'correlationId' => $correlationId, 'path' => $path,
            'exception' => get_class($e), 'message' => $e->getMessage(),
        ]);
    }

    $debug  = \Areanet\PIM\Classes\Config\Adapter::getConfig()->APP_DEBUG;
    $detail = ($debug && $e instanceof \Throwable) ? $e->getMessage() : 'An unexpected error occurred.';

    return \Custom\Classes\Factory\ErrorResponseFactory::create(
        \Custom\Classes\Enum\ErrorCodes::SERVER_ERROR,
        $detail,
        'api.error.server_error',
        [],
        ['correlationId' => $correlationId]
    );
}, 128);

// SECURITY — TOP-3 state-gate.
// Blocks state-changing requests (POST/PUT/PATCH/DELETE) when the tenant is
// trial-expired or has unaccepted legal renewals. Reads (GET/HEAD/OPTIONS)
// always pass, and a focused allowlist keeps the renewal/billing/logout
// endpoints reachable. GLOBAL_ADMIN bypasses entirely.
$app->before(function (\Symfony\Component\HttpFoundation\Request $request) use ($app) {
    return \Custom\Classes\Middleware\StateGateMiddleware::assert($request, $app);
});

// SECURITY — TOP-0.1 cross-tenant backstop.
// Defense-in-depth net for the "action forgot its tenant-membership check"
// class. Fail-open: only blocks a signature-verified tenant-scoped JWT whose
// holder has NO role in the body's tenantId. Per-action gates remain the
// primary control (this does not validate userId). Registered AFTER StateGate
// so trial/legal 423s keep precedence. Spec: docs/specs/HIGH_security-auth-audit-remediation.md
$app->before(function (\Symfony\Component\HttpFoundation\Request $request) use ($app) {
    return \Custom\Classes\Middleware\CrossTenantBackstopMiddleware::assert($request, $app);
});

// SECURITY — T-3.1 JWT revocation denylist.
// Rejects (401) any request whose token `jti` has been revoked (logout,
// deactivation, leak). Fail-open: only a confirmed denylist hit blocks; tokens
// without a jti or any infra error pass through. Registered AFTER the backstop.
// Spec: docs/specs/HIGH_security-auth-audit-remediation.md T-3.1
$app->before(function (\Symfony\Component\HttpFoundation\Request $request) use ($app) {
    return \Custom\Classes\Middleware\JwtDenylistMiddleware::assert($request, $app);
});

// Activity tracking — bumps Tenant.lastActivityAt (throttled to ≤ 1×/h via a
// conditional UPDATE) for inactivity / re-engagement mail triggers. Registered
// as an after-hook so it can never interfere with the request; fail-open.
// Spec: docs/specs/079_activity-lifecycle-mails.md §6.4
$app->after(function (\Symfony\Component\HttpFoundation\Request $request, \Symfony\Component\HttpFoundation\Response $response) use ($app) {
    \Custom\Classes\Middleware\ActivityTrackerMiddleware::track($request, $app);
});

$app['tenantSecretResolver'] = $app->protect(function($tenantId) use ($app) {
    $stmt = $app['database']->prepare("SELECT jwtSecret FROM usabiq_core_tenant WHERE id = :id");
    $stmt->bindValue("id", $tenantId);
    $stmt->execute();
    $result = $stmt->fetch();

    return $result['jwtSecret'] ?? null;
});

/**
 * Security response headers.
 * Applied on every API response (on top of CORS already set by contentfly bootstrap).
 * CSP runs in Report-Only mode first — flip USABIQ_CSP_ENFORCE=1 once violations are clean.
 * Spec: docs/specs/2026-04-14_launch-blockers-plan.md §2
 */
$app->after(function (\Symfony\Component\HttpFoundation\Request $request, \Symfony\Component\HttpFoundation\Response $response) {
    $response->headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains');
    $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

    // Tell intermediate proxies (jwilder/nginx-proxy, CDNs) not to transform
    // the body — keeps API responses as plain JSON instead of gzip. Appended
    // to whatever Cache-Control directive is already set by PHP / route.
    $existing = $response->headers->get('Cache-Control', '');
    if ($existing === '') {
        $response->headers->set('Cache-Control', 'no-transform');
    } elseif (stripos($existing, 'no-transform') === false) {
        $response->headers->set('Cache-Control', $existing . ', no-transform');
    }

    $csp = "default-src 'self'; "
         . "img-src 'self' data: https:; "
         . "style-src 'self' 'unsafe-inline'; "
         . "script-src 'self'; "
         . "font-src 'self' data:; "
         . "connect-src 'self' https://api.stripe.com https://*.sentry.io https://*.ingest.sentry.io; "
         . "frame-ancestors 'self'; "
         . "base-uri 'self'; "
         . "form-action 'self'";

    $cspReportUri = $_ENV['CSP_REPORT_URI'] ?? getenv('CSP_REPORT_URI');
    if ($cspReportUri) {
        $csp .= '; report-uri ' . $cspReportUri;
    }

    $enforce = ($_ENV['USABIQ_CSP_ENFORCE'] ?? getenv('USABIQ_CSP_ENFORCE') ?? '0') === '1';
    $response->headers->set($enforce ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only', $csp);
});

// SECURITY (H4 — CORS fail-closed).
// The framework CORS hook (lib/contentfly/bootstrap-web.php) reflects the request
// Origin *with* Access-Control-Allow-Credentials when USABIQ_CORS_ORIGINS is unset —
// a credential-theft-grade default with no boot guard. This hook runs AFTER that
// framework hook (negative priority; Silex fires higher-priority listeners first)
// and, IN PRODUCTION, strips the credentialed reflection whenever no explicit
// allowlist is configured. Dev is intentionally exempt so a fresh/forgotten
// USABIQ_CORS_ORIGINS never breaks local CORS — the framework's reflect-origin dev
// fallback stays. Production is also guarded at boot (SecretsCheck refuses to start
// without the var when APP_ENV=production), so this is defense-in-depth there. When
// the allowlist IS set, the framework already validated the Origin — headers stay.
$app->after(function (\Symfony\Component\HttpFoundation\Request $request, \Symfony\Component\HttpFoundation\Response $response) {
    if (!\Custom\Classes\Helper\AppEnv::isProduction()) {
        return; // dev: keep the framework reflect-origin fallback (never break local CORS)
    }
    $corsAllowlist = trim((string) ($_ENV['USABIQ_CORS_ORIGINS'] ?? getenv('USABIQ_CORS_ORIGINS') ?: ''));
    if ($corsAllowlist === '') {
        $response->headers->remove('Access-Control-Allow-Origin');
        $response->headers->remove('Access-Control-Allow-Credentials');
    }
}, -100);

// SECURITY (M10 — refresh token off localStorage).
// The /auth/login response carries the long-lived refresh token in its body; the
// SPA used to persist it in localStorage, where any XSS could exfiltrate it for a
// durable, self-renewing account takeover. Here we additionally deliver it as an
// HttpOnly cookie on the login response, out of JS reach — the frontend stops
// persisting the body copy and /refresh reads the cookie. Host-only + SameSite=Lax
// → sent only on same-site XHR to this API host (CSRF-safe); Secure only over
// HTTPS so plain-http local dev keeps working.
$app->after(function (\Symfony\Component\HttpFoundation\Request $request, \Symfony\Component\HttpFoundation\Response $response) {
    if (!str_ends_with($request->getPathInfo(), '/auth/login') || $response->getStatusCode() !== 200) {
        return;
    }
    $body = json_decode($response->getContent(), true);
    $refresh = is_array($body) ? ($body['token'] ?? null) : null;
    if (!is_string($refresh) || $refresh === '') {
        return;
    }
    $response->headers->setCookie(new \Symfony\Component\HttpFoundation\Cookie(
        'usabiq_rt',
        $refresh,
        time() + 60 * 60 * 24 * 30,   // 30 days
        '/',
        null,                          // host-only — set by + sent to this API host
        $request->isSecure(),          // Secure only over HTTPS (dev is plain http)
        true,                          // HttpOnly — never readable by JS
        false,
        \Symfony\Component\HttpFoundation\Cookie::SAMESITE_LAX
    ));
});

/******************************************************************************************************************
 * 
 * **************************************************************************************************
 * API **********************************************************************************************
 * **************************************************************************************************
 * 
 ******************************************************************************************************************/
$controllerProvider = $app['routeManager'];

/******************************************************************************************************************
 * GLOBAL ADMIN
 ******************************************************************************************************************/
/**
 * DASHBOARD
 */
$controllerProvider->mount('api/v1/global-admin/dashboard/', '\Custom\Controller\Admin\Global\GlobalDashboardController')
->post('/stats', true, 'statsAction')
->post('/cohort-conversion', true, 'cohortConversionAction')
->post('/recommendation-quality', true, 'recommendationQualityAction');

/**
 * TENANTS
 */
$controllerProvider->mount('api/v1/global-admin/tenants/', '\Custom\Controller\Admin\Global\GlobalTenantController')
->post('/list', true, 'listAction')
->post('/detail', true, 'detailAction')
->post('/update-state', true, 'updateStateAction')
// Manual tenant activation (task 108) — `provisioning` → `active` incl. trial
// re-anchoring and verification mail. Deliberately separate from
// /update-state, which is a generic state setter without any mail side effect.
->post('/activate', true, 'activateAction')
->post('/resend-activation-mail', true, 'resendActivationMailAction');

/**
 * AGENCIES
 */
$controllerProvider->mount('api/v1/global-admin/agencies/', '\Custom\Controller\Admin\Global\GlobalAgencyController')
->post('/list', true, 'listAction')
->post('/detail', true, 'detailAction')
->post('/create', true, 'createAction')
->post('/update', true, 'updateAction')
->post('/update-state', true, 'updateStateAction')
->post('/delete', true, 'deleteAction')
->post('/assign-tenant', true, 'assignTenantAction')
->post('/unassign-tenant', true, 'unassignTenantAction')
->post('/invite-admin', true, 'inviteAdminAction')
->post('/resend-admin-invite', true, 'resendAdminInviteAction')
->post('/revoke-admin-invite', true, 'revokeAdminInviteAction')
->post('/delete-admin-invite', true, 'deleteAdminInviteAction')
->post('/remove-admin', true, 'removeAdminAction')
->post('/license-options', true, 'licenseOptionsAction');

/**
 * AGENCY-ADMIN (agency.usabiq.* subdomain)
 * Base prefix for endpoints scoped to a single agency (AGENCY_ADMIN role).
 * Each sub-surface lives in its own controller; base controller keeps the
 * ping smoke-test for infrastructure health checks.
 */
$controllerProvider->mount('api/v1/agency-admin/', '\Custom\Controller\Admin\Agency\AgencyBaseController')
->post('/ping', true, 'pingAction');

$controllerProvider->mount('api/v1/agency-admin/dashboard/', '\Custom\Controller\Admin\Agency\AgencyDashboardController')
->post('/stats', true, 'statsAction');

$controllerProvider->mount('api/v1/agency-admin/tenants/', '\Custom\Controller\Admin\Agency\AgencyTenantController')
->post('/list', true, 'listAction')
->post('/create', true, 'createAction');

// Spec 082 Step 4 — per-tenant detail + on-behalf settings proxies. Lives in
// its own controller; the mount key deliberately omits the trailing slash so
// RouteManager (keyed by mount path) keeps BOTH providers while Silex
// normalizes them to the same /api/v1/agency-admin/tenants/* URL prefix.
$controllerProvider->mount('api/v1/agency-admin/tenants', '\Custom\Controller\Admin\Agency\AgencyTenantSettingsController')
->post('/detail', true, 'detailAction')
// ADR 2026-08-07 §D1/§D2 — the agency changes its own tenant's plan, capped by
// its own tier. Because /api/v1/agency-admin/* is exempt from
// CrossTenantBackstopMiddleware, resolveTenantInAgency() + the ceiling in
// AgencyLicenseChecker::assertCanGrantTenantPlan() are the ENTIRE gate.
->post('/plan/update', true, 'planUpdateAction')
->post('/settings/fetch', true, 'settingsFetchAction')
->post('/settings/update', true, 'settingsUpdateAction')
->post('/resend-admin-invite', true, 'resendAdminInviteAction')
->post('/revoke-admin-invite', true, 'revokeAdminInviteAction')
->post('/request-deletion', true, 'requestDeletionAction');

$controllerProvider->mount('api/v1/agency-admin/users/', '\Custom\Controller\Admin\Agency\AgencyUserController')
->post('/list', true, 'listAction')
->post('/update-role', true, 'updateRoleAction');

$controllerProvider->mount('api/v1/agency-admin/settings/', '\Custom\Controller\Admin\Agency\AgencySettingsController')
->post('/fetch', true, 'fetchAction')
->post('/update', true, 'updateAction');

// Spec 090 §3 — agency-scoped legal documents (agency publishes its own
// DPA/privacy/terms; DocumentResolver resolves tenant → agency → global).
$controllerProvider->mount('api/v1/agency-admin/legal-documents/', '\Custom\Controller\Admin\Agency\AgencyLegalDocumentController')
->post('/list', true, 'listAction')
->post('/detail', true, 'detailAction')
->post('/create', true, 'createAction')
->post('/update', true, 'updateAction')
->post('/publish-version', true, 'publishVersionAction')
->post('/delete', true, 'deleteAction');

$controllerProvider->mount('api/v1/agency-admin/impersonation/', '\Custom\Controller\Admin\Agency\AgencyImpersonationController')
->post('/start', true, 'startAction')
->post('/stop', true, 'stopAction');

$controllerProvider->mount('api/v1/agency-admin/audit-log/', '\Custom\Controller\Admin\Agency\AgencyAuditLogController')
->post('/list', true, 'listAction');

// Task 134 — Portfolio Comparison: the agency's own tenants compared on three
// OPERATIONAL metrics (participation completion, response velocity, study
// completion). Pro+ via `agency.cross_tenant.benchmarking`. The request body is
// a window only — there is NO tenantIds parameter, because /api/v1/agency-admin/*
// is exempt from CrossTenantBackstopMiddleware and AgencyScopeResolver is the
// entire isolation gate (ADR 2026-08-06_agency_cross_tenant_disclosure_boundary §4).
$controllerProvider->mount('api/v1/agency-admin/benchmarking/', '\Custom\Controller\Admin\Agency\AgencyBenchmarkController')
->post('/compare', true, 'compareAction');

// Spec 100 §B.4 — agency custom-domain self-service (Enterprise-gated):
// request a DNS-TXT challenge, then verify ownership to activate the domain.
$controllerProvider->mount('api/v1/agency-admin/domain/', '\Custom\Controller\Admin\Agency\AgencyDomainController')
->post('/request', true, 'requestAction')
->post('/verify', true, 'verifyAction');

// Spec 062 §D.6.9 / task 125 — agency-admin SSO configuration. Gated on
// `agency.sso` for the agency's own connection; a body `tenantId` switches to
// the on-behalf path for one of the agency's OWN tenants (verified against the
// acting agency) and the gate follows the owner to that tenant's
// `compliance.sso`. `enabled` is never a body field — enable/disable are their
// own calls, because enabling is what has to satisfy the domain-verification
// and completeness gates.
$controllerProvider->mount('api/v1/agency-admin/sso/', '\Custom\Controller\Admin\Agency\AgencySsoConfigController')
->post('/list', true, 'listAction')
->post('/create', true, 'createAction')
->post('/update', true, 'updateAction')
->post('/delete', true, 'deleteAction')
->post('/enable', true, 'enableAction')
->post('/disable', true, 'disableAction')
->post('/domain/request', true, 'domainRequestAction')
->post('/domain/verify', true, 'domainVerifyAction');

// Phase C — agency license status (tier + resolved feature map) and
// billing scaffold (status returns real tier + Stripe placeholders;
// checkout/portal return 501 until Stripe is wired).
$controllerProvider->mount('api/v1/agency-admin/license/', '\Custom\Controller\Admin\Agency\AgencyLicenseController')
->post('/status', true, 'statusAction');

$controllerProvider->mount('api/v1/agency-admin/billing/', '\Custom\Controller\Admin\Agency\AgencyBillingController')
->post('/status', true, 'statusAction')
->post('/checkout-session', true, 'checkoutSessionAction')
->post('/portal-session', true, 'portalSessionAction');

/**
 * USERS (cross-tenant search)
 */
$controllerProvider->mount('api/v1/global-admin/users/', '\Custom\Controller\Admin\Global\GlobalUserController')
->post('/search', true, 'searchAction');

/**
 * AUDIT LOG
 */
$controllerProvider->mount('api/v1/global-admin/audit-log/', '\Custom\Controller\Admin\Global\GlobalAuditLogController')
->post('/list', true, 'listAction');

/**
 * IMPERSONATION — GLOBAL_ADMIN only
 * Spec: docs/specs/2026-04-14_high-value-features-plan.md §1
 */
$controllerProvider->mount('api/v1/global-admin/impersonate/', '\Custom\Controller\Admin\Global\ImpersonationController')
->post('/start', true, 'startAction')
->post('/stop', true, 'stopAction');

/**
 * CHANGELOG — GLOBAL_ADMIN CRUD
 * Spec: docs/specs/2026-04-14_high-value-features-plan.md §2
 */
$controllerProvider->mount('api/v1/global-admin/changelog/', '\Custom\Controller\Admin\Global\GlobalChangelogController')
->post('/list', true, 'listAction')
->post('/detail', true, 'detailAction')
->post('/create', true, 'createAction')
->post('/update', true, 'updateAction')
->post('/publish', true, 'publishAction')
->post('/delete', true, 'deleteAction');

/**
 * PLATFORM SETTINGS
 */
$controllerProvider->mount('api/v1/global-admin/settings/', '\Custom\Controller\Admin\Global\GlobalSettingsController')
->post('/fetch', true, 'fetchAction')
->post('/update', true, 'updateAction')
->post('/test-smtp', true, 'testSmtpAction')
->post('/ai/fetch', true, 'aiFetchAction')
->post('/ai/update', true, 'aiUpdateAction')
->post('/ai/validate', true, 'aiValidateAction');

/**
 * LICENSING MANAGEMENT
 */
$controllerProvider->mount('api/v1/global-admin/licensing/plans/', '\Custom\Controller\Admin\Global\GlobalLicensingController')
->post('/list', true, 'planListAction')
->post('/detail', true, 'planDetailAction')
->post('/create', true, 'planCreateAction')
->post('/update', true, 'planUpdateAction')
->post('/delete', true, 'planDeleteAction');

$controllerProvider->mount('api/v1/global-admin/licensing/features/', '\Custom\Controller\Admin\Global\GlobalLicensingController')
->post('/list', true, 'featureListAction')
->post('/create', true, 'featureCreateAction')
->post('/update', true, 'featureUpdateAction')
->post('/delete', true, 'featureDeleteAction');

$controllerProvider->mount('api/v1/global-admin/licensing/scopes/', '\Custom\Controller\Admin\Global\GlobalLicensingController')
->post('/list', true, 'scopeListAction')
->post('/create', true, 'scopeCreateAction')
->post('/update', true, 'scopeUpdateAction')
->post('/delete', true, 'scopeDeleteAction');

$controllerProvider->mount('api/v1/global-admin/licensing/plan-features/', '\Custom\Controller\Admin\Global\GlobalLicensingController')
->post('/list', true, 'planFeatureListAction')
->post('/assign', true, 'planFeatureAssignAction')
->post('/remove', true, 'planFeatureRemoveAction');

$controllerProvider->mount('api/v1/global-admin/licensing/tenant-plans/', '\Custom\Controller\Admin\Global\GlobalLicensingController')
->post('/list', true, 'tenantPlanListAction')
->post('/assign', true, 'tenantPlanAssignAction') // spec 093 — manual plan grant / comp
// ADR 2026-08-07 §D3 — hands plan authority back to the agency after a
// break-glass override. Until this runs, the agency's own plan write is
// refused: global admin wins, and an agency may not silently revert it.
->post('/lift-agency-override', true, 'tenantPlanLiftAgencyOverrideAction');

// Spec 093 — entitlement-override CRUD (per-feature deltas on top of the plan)
$controllerProvider->mount('api/v1/global-admin/licensing/overrides/', '\Custom\Controller\Admin\Global\GlobalLicensingController')
->post('/list', true, 'overrideListAction')
->post('/create', true, 'overrideCreateAction')
->post('/update', true, 'overrideUpdateAction')
->post('/delete', true, 'overrideDeleteAction');

/**
 * LEGAL DOCUMENTS — Global Admin CRUD with auto-versioning + renewal trigger
 * Spec: docs/specs/2026-04-10_legal-document-editor.md
 */
$controllerProvider->mount('api/v1/global-admin/legal-documents/', '\Custom\Controller\Admin\Global\GlobalLegalDocumentController')
->post('/list', true, 'listAction')
->post('/detail', true, 'detailAction')
->post('/create', true, 'createAction')
->post('/update', true, 'updateAction')
->post('/publish-version', true, 'publishVersionAction')
->post('/delete', true, 'deleteAction');

/******************************************************************************************************************
 * ADMIN (shared)
 ******************************************************************************************************************/
$controllerProvider->mount('api/v1/admin/tenant/key/', '\Custom\Controller\Admin\Tenant\TenantKeyController')
->post('/rotate', true, 'rotateAction')
->get('/status', true, 'keyStatusAction');

/******************************************************************************************************************
 * CORE 
 ******************************************************************************************************************/
/**
 * HEALTH CHECK
 */
$controllerProvider->mount('api/v1/core/health/', '\Custom\Controller\Core\HealthController')
->get('/', false, 'checkAction');

/**
 * AUTH
 */
$controllerProvider->mount('api/v1/core/auth/', '\Custom\Controller\Core\AuthController')
->post('/refresh', false, 'refreshAction')
->post('/logout', false, 'logoutAction')
->post('/identify', false, 'identifyAction')
->post('/redirect-token', false, 'redirectTokenAction')
->post('/resolve-redirect-token', false, 'resolveRedirectTokenAction')
->post('/resolve-impersonation-token', false, 'resolveImpersonationTokenAction')
// Task 127 — redeem the single-use envelope an SSO login lands with. Public
// (the SPA calls it before it holds a session) and DELIBERATELY separate from
// the impersonation redeem: this one fails CLOSED on a missing jti or an
// unavailable store, because an SSO envelope arrives in a browser redirect and
// redeems into a full session.
->post('/resolve-sso-token', false, 'resolveSsoTokenAction');

/**
 * CONFIG
 */
$controllerProvider->mount('api/v1/core/config/', '\Custom\Controller\Core\ConfigController')
->post('/bootstrap', false, 'bootstrapAction');

/**
 * USER CONFIG
 */
$controllerProvider->mount('api/v1/core/user-config/', '\Custom\Controller\Core\UserConfigController')
->post('/fetch', true, 'fetchAction')
->post('/fetch-draft', true, 'fetchDraftAction')
->post('/draft', true, 'draftAction')
->post('/publish', true, 'publishAction');

/**
 * TENANT SETTINGS
 */
$controllerProvider->mount('api/v1/core/tenant-settings/', '\Custom\Controller\Core\TenantSettingsController')
->post('/fetch', true, 'fetchAction')
->post('/update', true, 'updateAction')
->post('/request-deletion', true, 'requestDeletionAction');

/**
 * TENANT SETUP (public — used post-signup without JWT and by setup-check guard)
 */
// SECURITY — TOP-1.6: status/save-organization/complete/dismiss now require a
// JWT (isSecure=true) and are gated to the tenant's own admins inside the
// controller. They are only ever called from the authenticated setup wizard
// (SetupCheckGuard requires isTenantAdminAndAbove), so the matching NO_AUTH
// flag was removed from tenant-setup.service.ts. legal-documents stays public
// (global texts, no tenant data).
$controllerProvider->mount('api/v1/core/tenant-setup/', '\Custom\Controller\Core\TenantSetupController')
->post('/status', true, 'statusAction')
->post('/save-organization', true, 'saveOrganizationAction')
->post('/legal-documents', false, 'legalDocumentsAction')
->post('/complete', true, 'completeAction')
->post('/dismiss', true, 'dismissAction')
->post('/checklist', true, 'checklistAction');

/**
 * DASHBOARD
 */
$controllerProvider->mount('api/v1/core/dashboard/', '\Custom\Controller\Core\DashboardController')
->post('/stats', true, 'statsAction')
->post('/user-stats', true, 'userStatsAction')
->post('/editor-workspace', true, 'editorWorkspaceAction')
->post('/activity-feed', true, 'activityFeedAction');

/**
 * USER ACCOUNT
 */
$controllerProvider->mount('api/v1/core/user-account/', '\Custom\Controller\Core\UserAccountController')
->post('/change-password', true, 'changePasswordAction')
->post('/profile-stats', true, 'profileStatsAction')
->post('/preferences', true, 'preferencesAction')
->post('/update-preferences', true, 'updatePreferencesAction')
->post('/delete-account', true, 'deleteAccountAction')
->post('/devices', true, 'listDevicesAction')
->post('/devices/remove', true, 'removeDeviceAction')
->post('/export-data', true, 'exportDataAction')
->post('/notification-prefs', true, 'notificationPrefsAction')
->post('/notification-prefs/update', true, 'updateNotificationPrefsAction');

/**
 * ONBOARDING
 */
$controllerProvider->mount('api/v1/core/onboarding/', '\Custom\Controller\Core\OnboardingController')
->post('/update', true, 'updateAction');

/**
 * CHANGELOG (user-facing)
 * Spec: docs/specs/2026-04-14_high-value-features-plan.md §2
 */
$controllerProvider->mount('api/v1/core/changelog/', '\Custom\Controller\Core\ChangelogController')
->get('/list', true, 'listAction')
->post('/mark-read', true, 'markReadAction')
->post('/acknowledge', true, 'acknowledgeAction');

/**
 * AUDIENCE POOLS (tenant-admin / editor)
 * Spec: docs/specs/2026-04-14_high-value-features-plan.md §6
 */
$controllerProvider->mount('api/v1/features/survey/audience/', '\Custom\Controller\Features\Survey\AudienceController')
->post('/list', true, 'listAction')
->post('/detail', true, 'detailAction')
->post('/create', true, 'createAction')
->post('/update', true, 'updateAction')
->post('/delete', true, 'deleteAction')
->post('/add-members', true, 'addMembersAction')
->post('/remove-members', true, 'removeMembersAction')
->post('/resolve-filter', true, 'resolveFilterAction');

/**
 * USER DATA
 */
$controllerProvider->mount('api/v1/core/user-data/', '\Custom\Controller\Core\UserDataController')
->post('/fetch', true, 'fetchAction')
->post('/save', true, 'saveAction');

/**
 * FEEDBACK
 */
$controllerProvider->mount('api/v1/core/feedback/', '\Custom\Controller\Core\FeedbackController')
->post('/submit', true, 'submitAction')
->post('/list', true, 'listAction')
->post('/update', true, 'updateAction');

/**
 * TENANT AUDIT LOG
 */
$controllerProvider->mount('api/v1/core/tenant-audit-log/', '\Custom\Controller\Core\TenantAuditLogController')
->post('/list', true, 'listAction');

/**
 * TENANT LEGAL — current consents, history, accept new versions from settings
 */
$controllerProvider->mount('api/v1/core/tenant-legal/', '\Custom\Controller\Core\TenantLegalController')
->post('/status', true, 'statusAction')
->post('/accept', true, 'acceptAction');

/**
 * PUBLIC API KEYS (spec 096 Phase 1) — TENANT_ADMIN management of the keys used
 * by the key-authenticated /api/v1/public/* surface. JWT-protected.
 */
$controllerProvider->mount('api/v1/core/api-keys/', '\Custom\Controller\Core\ApiKeyController')
->post('/list', true, 'listAction')
->post('/create', true, 'createAction')
->post('/revoke', true, 'revokeAction');

/**
 * OUTBOUND WEBHOOKS (spec 096 Phase 3) — TENANT_ADMIN management of webhook
 * subscriptions consumed by the `usabiq:webhooks:dispatch` cron. JWT-protected.
 * `ping` enqueues a test delivery and flushes the due queue immediately.
 */
$controllerProvider->mount('api/v1/core/webhooks/', '\Custom\Controller\Core\WebhookSubscriptionController')
->post('/list', true, 'listAction')
->post('/create', true, 'createAction')
->post('/update', true, 'updateAction')
->post('/delete', true, 'deleteAction')
->post('/ping', true, 'pingAction');

/**
 * SSO CONFIGURATION (spec 062 §D.6.9 / task 125) — TENANT_ADMIN management of
 * the workspace's own IdP connection. Gated on `compliance.sso` (Enterprise).
 *
 * Mounted under `api/v1/core/` and NOT under the `api/v1/tenant-admin/` the
 * spec sketches: CrossTenantBackstopMiddleware guards exactly `/api/v1/core/`
 * and `/api/v1/features/`, so a new top-level prefix would place this write
 * outside the backstop. See the controller docblock.
 */
$controllerProvider->mount('api/v1/core/sso/', '\Custom\Controller\Core\TenantSsoConfigController')
->post('/list', true, 'listAction')
->post('/create', true, 'createAction')
->post('/update', true, 'updateAction')
->post('/delete', true, 'deleteAction')
->post('/enable', true, 'enableAction')
->post('/disable', true, 'disableAction')
->post('/domain/request', true, 'domainRequestAction')
->post('/domain/verify', true, 'domainVerifyAction');

/**
 * USER FILTERS
 */
$controllerProvider->mount('api/v1/core/user-filters/', '\Custom\Controller\Core\UserFilterController')
->post('/metadata', true, 'metadataAction')
->post('/query', true, 'queryAction');

/**
 * USER MANAGEMENT
 */
$controllerProvider->mount('api/v1/core/user-management/', '\Custom\Controller\Core\UserManagementController')
->post('/list', true, 'listAction')
->post('/metadata', true, 'metadataAction')
->post('/detail', true, 'detailAction')
->post('/update-status', true, 'updateStatusAction')
->post('/update-role', true, 'updateRoleAction')
->post('/transfer-ownership', true, 'transferOwnershipAction')
->post('/bulk-status', true, 'bulkStatusAction')
->post('/bulk-delete', true, 'bulkDeleteAction')
->post('/export-csv', true, 'exportCsvAction');

/**
 * INVITATION MANAGEMENT
 */
$controllerProvider->mount('api/v1/core/user-management/invitations/', '\Custom\Controller\Core\InvitationManagementController')
->post('/list', true, 'listAction')
->post('/resend', true, 'resendAction')
->post('/revoke', true, 'revokeAction');

/**
 * SHARE LINK MANAGEMENT
 */
$controllerProvider->mount('api/v1/core/user-management/share-link/', '\Custom\Controller\Core\ShareLinkController')
->post('/fetch', true, 'fetchAction')
->post('/generate', true, 'generateAction')
->post('/update', true, 'updateAction')
->post('/toggle', true, 'toggleAction');

/******************************************************************************************************************
 * LICENSING
 ******************************************************************************************************************/
/**
 * LICENSE
 */
$controllerProvider->mount('api/v1/licensing/me/', '\Custom\Controller\Licensing\LicensingController')
->get('/license', true, 'meLicenseAction')
->get('/usage', true, 'usageAction')
->get('/billing-info', true, 'billingInfoAction')
->post('/portal-session', true, 'portalSessionAction')
->post('/upgrade', true, 'upgradeAction')
// Task 135 / ADR 2026-08-07 §D4 — acknowledges the standing operational notice
// carried on the license payload (today: "your agency detached this workspace").
->post('/dismiss-notice', true, 'dismissNoticeAction');

/**
 * PLAN CATALOG (public, no auth required)
 */
$controllerProvider->mount('api/v1/licensing/plans/', '\Custom\Controller\Licensing\LicensingController')
->get('/catalog', false, 'catalogAction');

/******************************************************************************************************************
 * FEATURES
 ******************************************************************************************************************/
/**
 * PUBLIC_ACCESS::USER
 */
$controllerProvider->mount('api/v1/features/public-access/user/', '\Custom\Controller\Features\PublicAccess\UserController')
->post('/password-forgot', false, 'passwordForgotAction')
->post('/validate-reset-hash', false, 'validateResetTokenAction')
->post('/reset-password', false, 'resetPasswordAction');

/**
 * PUBLIC_ACCESS::EMAIL_VERIFICATION
 */
$controllerProvider->mount('api/v1/features/public-access/email-verification/', '\Custom\Controller\Features\PublicAccess\EmailVerificationController')
->post('/validate', false, 'validateTokenAction')
->post('/confirm', false, 'confirmAction');

/**
 * PUBLIC_ACCESS::SECURE_DEVICE
 */
$controllerProvider->mount('api/v1/features/public-access/secure-device/', '\Custom\Controller\Features\PublicAccess\SecureDeviceController')
->post('/confirm', false, 'confirmAction');

/**
 * PUBLIC_ACCESS::UNSUBSCRIBE — one-click List-Unsubscribe target (spec 079 §6.5).
 * GET renders a confirm page (no mutation); POST applies the opt-out. Public:
 * authorized by the unguessable HMAC token, not a session.
 *
 * MUST be a single ->match() (not ->get()+->post()): the RouteManager keys routes
 * by path, so a separate ->post() would shadow the ->get() and the GET confirm page
 * would 405 (finding 105/F1). RFC 8058 needs the SAME URL to serve GET (view) and
 * POST (one-click); match() binds both to unsubscribeAction, which branches on the
 * HTTP method itself.
 */
$controllerProvider->mount('api/v1/features/public-access/', '\Custom\Controller\Features\PublicAccess\UnsubscribeController')
->match('/unsubscribe', false, 'unsubscribeAction');

/**
 * SSO — PUBLIC SAML ENDPOINTS (spec 062 §D.6.9 / task 126)
 *
 * isSecure=false: a customer's IdP posts here with no bearer token. That skips
 * BOTH AuthMiddleware and FeatureGateMiddleware — and FeatureGateMiddleware
 * never enforced a licence anyway (`_required_feature` is set nowhere in the
 * codebase), so SamlAcsService applies the Enterprise gate, the `enabled` check
 * and the rate limit itself.
 *
 * Mounted under `api/v1/features/` on purpose: that prefix is inside
 * CrossTenantBackstopMiddleware::GUARDED_PREFIXES, the same reasoning recorded
 * for `api/v1/core/sso/` in task 125.
 *
 * ACS and metadata sit on DISTINCT paths deliberately. Routes inside one
 * provider are keyed by PATH ALONE (CustomControllerProvider::$routes), so a
 * ->get() and a ->post() on the same path would shadow each other — the same
 * trap already documented for the unsubscribe endpoint above (finding 105/F1).
 *
 * The SLO route is NOT registered: single logout is optional in the SAML profile
 * and §D.6.9 lists it as such. The SP metadata advertises the URL so it can be
 * added without re-registering at the IdP.
 */
$controllerProvider->mount('api/v1/features/sso/', '\Custom\Controller\Features\Sso\SamlController')
->post('/saml/acs/{connectionId}', false, 'acsAction')
->get('/saml/metadata/{connectionId}', false, 'metadataAction')
// Task 129 — SP-initiated login. Public for the same reason the ACS is: the
// person clicking "Sign in with SSO" has no session yet. The connection id
// comes from the HOST-derived bootstrap config, never from a typed email —
// binding it to an input would be the enumeration oracle task 129 §1 forbids.
->get('/saml/initiate/{connectionId}', false, 'initiateAction');

/**
 * SIGNUP::GENERAL
 */
$controllerProvider->mount('api/v1/features/signup/general/', '\Custom\Controller\Features\Signup\GeneralSignupController')
->post('/signup', false, 'signupAction')
->get('/legal-documents', false, 'legalDocumentsAction')
->get('/check-slug', false, 'checkSlugAction')
->post('/resend-verification', false, 'resendVerificationAction');

/**
 * SIGNUP::TENANT
 */
$controllerProvider->mount('api/v1/features/signup/tenant/', '\Custom\Controller\Features\Signup\TenantSignupController')
->post('/info', false, 'infoAction')
->post('/register', false, 'registerAction');

/**
 * SIGNUP::INVITATION
 */
$controllerProvider->mount('api/v1/features/signup/invitation/', '\Custom\Controller\Features\Signup\InvitationController')
->post('/invite', true, 'inviteAction')
->post('/validate', false, 'validateAction');

/**
 * SIGNUP::AGENCY (public accept flow for AGENCY_ADMIN invitations)
 */
$controllerProvider->mount('api/v1/features/signup/agency/', '\Custom\Controller\Features\Signup\AgencyInvitationController')
->post('/validate', false, 'validateAction')
->post('/accept', false, 'acceptAction');

/**
 * SIGNUP::TENANT_ADMIN (public accept flow for TENANT_ADMIN invitations on
 * agency-created tenants — spec 081)
 */
$controllerProvider->mount('api/v1/features/signup/tenant-admin/', '\Custom\Controller\Features\Signup\AgencyTenantInvitationController')
->post('/validate', false, 'validateAction')
->post('/accept', false, 'acceptAction');

/**
 * SIGNUP::SHARE_LINK
 */
$controllerProvider->mount('api/v1/features/signup/share-link/', '\Custom\Controller\Features\Signup\ShareLinkValidationController')
->post('/validate', false, 'validateAction');

/**
 * EXTERNAL::STRIPE WEBHOOK
 */
$controllerProvider->mount('api/v1/external/stripe/', '\Custom\Controller\External\Stripe\WebhookController')
->post('/webhook', false, 'webhookAction');

/**
 * PUBLIC API (spec 096 Phase 2) — read-only, API-key-authenticated export.
 *
 * Mounted isSecure=false: the JWT AuthMiddleware would try to parse the
 * `Bearer usq_live_…` key as a JWT and 401. Auth runs entirely inside the
 * controller via $app['public.api.resolver']. The global before-hooks
 * (StateGate / CrossTenantBackstop / JwtDenylist) all fail-open on these
 * routes — they are GET (StateGate passes reads) and carry no JWT, so the
 * JWT-decode in each backstop yields a null payload and returns null.
 */
$controllerProvider->mount('api/v1/public/', '\Custom\Controller\Public\PublicApiController')
->get('/openapi.json', false, 'openApiAction')
->get('/surveys', false, 'listSurveysAction')
->get('/surveys/{id}/insights', false, 'surveyInsightsAction')
->get('/surveys/{id}/decision-summary', false, 'surveyDecisionSummaryAction');

/**
 * SURVEY
 */
$controllerProvider->mount('api/v1/features/survey/', '\Custom\Controller\Features\Survey\SurveyController')
->post('/list', true, 'listAction')
->post('/fetch', true, 'fetchAction')
->post('/create', true, 'createAction')
->post('/update', true, 'updateAction')
->post('/publish', true, 'publishAction')
->post('/close', true, 'closeAction')
->post('/reactivate', true, 'reactivateAction')
->post('/archive', true, 'archiveAction')
->post('/delete', true, 'deleteAction')
->post('/duplicate', true, 'duplicateAction')
->post('/types', true, 'typesAction');

/**
 * SURVEY CAMPAIGNS
 */
$controllerProvider->mount('api/v1/features/survey/campaigns/', '\Custom\Controller\Features\Survey\SurveyCampaignController')
->post('/list', true, 'listAction')
->post('/fetch', true, 'fetchAction')
->post('/trend', true, 'trendAction')
->post('/create', true, 'createAction')
->post('/update', true, 'updateAction')
->post('/delete', true, 'deleteAction')
->post('/assign-survey', true, 'assignSurveyAction')
->post('/remove-survey', true, 'removeSurveyAction')
->post('/update-schedule', true, 'updateScheduleAction');

/**
 * BENCHMARK CONSENT ("Fair Data Exchange")
 * Spec: docs/specs/098_benchmark-normdata-pool-consent.md (Phase A / A10)
 */
// GET lives on a DISTINCT path (/programs) from the POST (/consent): same-path
// GET+POST would let the POST shadow the GET in the RouteManager (finding 105/F1),
// and these two have different actions so ->match() can't merge them.
$controllerProvider->mount('api/v1/features/benchmark/', '\Custom\Controller\Features\Benchmark\BenchmarkConsentController')
->get('/programs', true, 'listAction')
->post('/consent', true, 'setAction');

// Phase B (B5): the read side of the pool. Separate controller because this one
// is survey-scoped + license-gated, while the consent surface above is
// tenant-scoped and deliberately ungated (you must be able to consent BEFORE you
// have access).
//
// The mount key deliberately omits the trailing slash: RouteManager stores
// providers in an array keyed by mount path, so reusing the exact same string
// would REPLACE the consent provider instead of adding to it. Silex normalizes
// both to the same /api/v1/features/benchmark/* URL prefix (same trick as
// api/v1/agency-admin/tenants above).
$controllerProvider->mount('api/v1/features/benchmark', '\Custom\Controller\Features\Benchmark\BenchmarkCompareController')
->post('/compare', true, 'compareAction');

/**
 * SURVEY::PARTICIPATION
 */
$controllerProvider->mount('api/v1/features/survey/participate/', '\Custom\Controller\Features\Survey\SurveyParticipationController')
->post('/fetch', false, 'fetchAction')
->post('/start', false, 'startAction')
->post('/submit', false, 'submitAction')
->post('/progress', false, 'progressAction')
->post('/status', true, 'statusAction');

/**
 * SURVEY::EVALUATION
 */
$controllerProvider->mount('api/v1/features/survey/evaluate/', '\Custom\Controller\Features\Survey\SurveyEvaluationController')
->post('/data', true, 'dataAction')
->post('/summary', true, 'summaryAction')
->post('/stats', true, 'statsAction')
->post('/filter-metadata', true, 'filterMetadataAction')
->post('/filtered-data', true, 'filteredDataAction')
->post('/export', true, 'exportAction')
->post('/themes/get', true, 'themesGetAction')
->post('/themes/put', true, 'themesPutAction');

/**
 * SURVEY::EVALUATION SHARE (admin)
 */
$controllerProvider->mount('api/v1/features/survey/evaluate/share/', '\Custom\Controller\Features\Survey\SurveyEvaluationController')
->post('/create', true, 'createShareAction')
->post('/list', true, 'listSharesAction')
->post('/revoke', true, 'revokeShareAction')
->post('/info', false, 'shareInfoAction')
->post('/validate', false, 'validateShareAction');

/**
 * SURVEY::INSIGHTS (Decision Engine)
 */
$controllerProvider->mount('api/v1/features/survey/insights/', '\Custom\Controller\Features\Survey\SurveyInsightController')
->post('/data', true, 'dataAction')
->post('/regenerate', true, 'regenerateAction')
->post('/recommendations/fetch', true, 'recommendationsFetchAction')
->post('/recommendations/regenerate', true, 'recommendationsRegenerateAction')
->post('/recommendations/feedback', true, 'recommendationFeedbackAction')
->post('/decision-summary', true, 'decisionSummaryAction');

/**
 * RAG SEMANTIC SEARCH
 */
$controllerProvider->mount('api/v1/features/rag/', '\Custom\Controller\Features\Rag\RagController')
->post('/search', true, 'searchAction')
->post('/index', true, 'indexAction')
->post('/status', true, 'statusAction');

/**
 * RAG CHAT SESSIONS — "Ask usabiq" (spec 073)
 * POST verb-suffixed routes with params in the JSON body (mirrors the webhooks /
 * api-keys controllers). All require a JWT (isSecure=true).
 */
$controllerProvider->mount('api/v1/features/rag/sessions/', '\Custom\Controller\Features\Rag\RagSessionController')
->post('/create', true, 'createAction')
->post('/list', true, 'listAction')
->post('/get', true, 'getAction')
->post('/message', true, 'addMessageAction')
->post('/update', true, 'updateAction')
->post('/delete', true, 'deleteAction')
->post('/export', true, 'exportAction')
->post('/check-duplicate', true, 'checkDuplicateAction');

/**
 * GLOBAL SEARCH (spec 086)
 */
$controllerProvider->mount('api/v1/features/search/', '\Custom\Controller\Features\Search\GlobalSearchController')
->post('/global', true, 'globalAction');

/**
 * SURVEY::TEMPLATES
 */
$controllerProvider->mount('api/v1/features/survey/templates/', '\Custom\Controller\Features\Survey\TemplateController')
->post('/list', true, 'listAction')
->post('/detail', true, 'detailAction')
->post('/use', true, 'useAction');

/**
 * AI ASSIST
 */
$controllerProvider->mount('api/v1/features/ai-assist/', '\Custom\Controller\Features\AI\AIAssistController')
->post('/generate', true, 'generateAction');

// AI Survey Creator (spec 102) — goal → ranked types → generated draft.
// NOTE: distinct mount prefix — RouteManager keys providers by mount path, so a
// second mount at 'ai-assist/' would overwrite AIAssistController's routes.
$controllerProvider->mount('api/v1/features/survey-creator/', '\Custom\Controller\Features\AI\SurveyCreatorController')
->post('/recommend-types', true, 'recommendTypesAction')
->post('/generate-survey', true, 'generateSurveyAction')
->post('/critique-survey', true, 'critiqueSurveyAction');

/**
 * TENANT AI CONFIG
 */
$controllerProvider->mount('api/v1/core/tenant-ai-config/', '\Custom\Controller\Core\TenantAiConfigController')
->post('/status', true, 'statusAction')
->post('/save', true, 'saveAction')
->post('/validate', true, 'validateAction')
->post('/remove', true, 'removeAction');
