# 089 — Off-Silex Backend Migration (best-practice proposal)

## 1. Why (problem)

Silex (~2.0) has been EOL since 2018 (~7.5 yrs unpatched), pulling Symfony 2/3-era components
into the tree. It is finding C-2 (Critical): indefinite supply-chain/CVE exposure + SOC2/PCI
friction. No known active exploit — this is exposure, not a live breach — so it is a **planned
re-platform, not a launch blocker**. The interim mitigation (CI `composer audit` + WAF + risk
memo) is already specced in `069_ci-cd-pipeline.md §5.2`.

## 2. What we actually run on (audited 2026-06-12)

| Layer | Reality | Migration impact |
|---|---|---|
| HTTP | Symfony **HttpFoundation** `Request`/`JsonResponse` used directly in controllers | ✅ portable as-is |
| Framework | **Silex `Application`** = router + Pimple DI + before/after hooks | ❌ rewrite |
| App-on-framework | Custom **`backend/lib/contentfly/`** PIM/CMS wraps Silex (RouteManager, TypeManager, UIManager, PluginManager, legacy `/api` admin, error/CORS bootstrap) | ⚠️ drop the CMS, keep a sliver |
| Routing | `backend/custom/app.php`: **68 `mount()`** calls, **~236 route lines**, **43 `$app['x']` service defs** | ⚠️ re-register |
| Controllers | **67** classes; access services via `$this->app['orm.em']` etc.; extend `Areanet\PIM\…\BaseController` | ⚠️ base + container access |
| Services | **183** classes under `Classes/Service/` — pure PHP | ✅ portable |
| Entities | **60** classes, **all `extends Areanet\PIM\Entity\Base`** + `@PIM\*` annotations (Config/Select/…) | 🔴 **deepest coupling** |
| Crypto type | contentfly `StringType` = legacy **AES-CBC, no MAC** (= finding **C-4**) | 🔴 + opportunity |
| CLI | **14** commands already `extends Symfony\…\Console\Command` | ✅ portable |
| Tests | 15 unit tests, service-layer only — **no HTTP/route/middleware tests** | 🔴 safety-net gap |

**The deepest tie is NOT routing — it is the entity layer.** Even with the PIM-CMS UI gone, all
60 entities depend on `Areanet\PIM\Entity\Base` and the `@PIM` annotation/type system (which
includes the C-4 `StringType`). Any "remove contentfly entirely" plan must answer this first.

## 3. Recommendation (best practice)

**Strangler migration to a lean Symfony 7 kernel, keeping the `$app['key']` idiom via a PSR-11
bridge, and extracting a *minimal* `pim-compat` library instead of carrying or rewriting the
whole PIM-CMS.** Concretely:

### 3.1 Drop vs. keep vs. extract

- **DROP** (PIM-CMS product, unused): the legacy `/api` admin UI + its controllers, `UIManager`,
  `PluginManager`, `TypeManager`'s UI concerns, `FileController`/admin file UI, Twig admin
  templates, session-based admin auth. This also removes the session-lock pressure on `/api`.
- **KEEP untouched**: the 183 `Classes/Service/*`, 60 Doctrine entities, 67 controller *bodies*,
  the 3 custom middleware (StateGate, CrossTenantBackstop, ActivityTracker), HttpFoundation
  request/response, the 14 Symfony Console commands.
- **EXTRACT a small `usabiq/pim-compat` lib** (vendored from contentfly): the Doctrine `Base`
  mapped-superclass, the `@PIM` annotation classes still referenced by entities, and the custom
  Doctrine **types** — re-implementing `StringType` on **XChaCha20-Poly1305 (AEAD)** so the cut
  **also closes C-4**. ~60 entities keep their `extends Base` and `@PIM\*` lines verbatim.

> Net: we delete the framework + the CMS, but preserve a thin Doctrine/entity compatibility
> shim. That is what makes "small modifications" realistic instead of a 60-entity rewrite.

### 3.2 Kernel & container

- **Symfony 7 runtime + components** (HttpKernel, Routing, DependencyInjection, Console,
  Doctrine via `doctrine/orm`, `symfony/security-core` optional). Rationale: HttpFoundation is
  already in use (zero controller churn on request/response), Symfony is the most SOC2-defensible
  long-term target, and its Console already backs our 14 commands.
  - *Alternative considered:* **Slim 4** (PSR-7/PSR-15, lighter, conceptually closest to Silex).
    Faster to stand up, but PSR-7 ≠ HttpFoundation → it would force a request/response shim
    across 67 controllers. **Rejected** for that churn; Symfony keeps controllers closer to today.
- **Container bridge (the key trick):** expose the Symfony DI container behind an `ArrayAccess`
  shim so existing `$this->app['orm.em']`, `$this->app['audit.log']`, `$this->app['database']`,
  `$this->app['license.checker']`, `$this->app['rate.limiter']`, … resolve **with the same string
  keys**. The 43 `$app['x'] = fn()` definitions become 43 container service entries with
  identical ids. Controllers change **almost nothing** in the body; only the base class swaps.
  Later, opportunistically refactor hot controllers to constructor injection — not required for
  cutover.

### 3.3 Routing, middleware, errors

- **Routes:** keep `app.php`'s mount/action structure but feed it through a small route loader
  that registers each `('/path', secured, 'action')` against Symfony Routing (one config pass;
  the 236 lines become data, not 236 hand edits). Preserve the `_secured` flag semantics that
  `RouteManager` tracks today.
- **Middleware:** map the 3 custom before/after middleware + CORS + security headers + the
  `/api/v1/*` **session-write-close concurrency optimization** (app.php:47-55) onto Symfony
  `kernel.request`/`kernel.response` listeners, **preserving order** (StateGate → CrossTenant →
  ActivityTracker). This is the highest-risk slice — it carries tenant-isolation + trial/legal
  gating; it must be ported with tests, not by eye.
- **Auth:** re-home the JWT decode/verify + `tenantSecretResolver` (per-tenant secret) as a
  request listener that populates `auth.user`/`auth.jwt` container entries the controllers
  already read.
- **Errors:** replace `$app->error()` + the EventDispatcher exception path with a Symfony
  exception listener that emits the existing `ApiResponseService` error envelope shape unchanged.

## 4. Phased plan (strangler, parity-first)

- [ ] **P0 — Safety net (precondition, reusable regardless of target).** Characterization tests
  for the API: per controller, at least auth-required, role-gating, and **tenant-isolation**
  (foreign `tenantId`/`agencyId` ⇒ 403/404) smoke tests across the 236 routes. Without this,
  re-platforming 236 endpoints is reckless. *(Aligns with audit rec #17 / T-3.13.)*
- [ ] **P1 — Kernel scaffold.** Symfony 7 kernel + DI container + `ArrayAccess` `$app` bridge +
  Doctrine wiring + `pim-compat` extraction (Base, `@PIM` annotations, AEAD type = C-4) +
  error/CORS/security-headers/session-close listeners + JWT/tenant-secret auth listener. Boots,
  serves a health route, runs the 14 Console commands.
- [ ] **P2 — Route + middleware port.** Load all 68 mounts/236 routes; port the 3 middleware in
  order; bring controllers onto the new base class. Run P0 suite green, endpoint by endpoint.
- [ ] **P3 — Shed contentfly.** Remove Silex + the PIM-CMS app; delete the legacy `/api` admin;
  confirm only `pim-compat` remains of the old `lib/`. **Migrate the C-4 CBC fields** to the new
  AEAD type (re-encrypt rows) as part of this cut.
- [ ] **P4 — Cutover & hardening.** Parity test (P0 + manual), perf-validate the session-lock
  optimization still holds, `composer audit` clean, drop the WAF interim/risk-memo, update
  `docs/operations/OPERATIONS.md` + runbook.

## 5. Risks & mitigations

| Risk | Mitigation |
|---|---|
| **Entity base coupling** (60 × `extends Base` + `@PIM`) | `pim-compat` shim keeps them verbatim; do NOT rewrite entities in this project |
| **Near-zero HTTP test coverage** | P0 is a hard gate; no porting before the net exists |
| **Middleware ordering / tenant isolation regressions** | Port with the CrossTenantBackstop tests from T-0.1; adversarial fuzz (rec #17) |
| **Silex 2 + Symfony 3 components on PHP 8.3 is already fragile** | Argues *for* doing this on a planned schedule rather than under a CVE fire-drill |
| **Hidden contentfly usages** (TypeManager/config readers at runtime) | P1 spike: boot the kernel and exercise every Console command + a smoke crawl before committing to the target |

## 6. Effort

With the **PIM-CMS dropped** (owner-confirmed), the wildcard Phase-3 shrinks and the estimate
trends to the lower end of the earlier range:

- **~3–4 calendar months, 1–2 senior PHP devs** for the Symfony-7-kernel strangler above.
- P0 (safety net) ≈ 2–4 wks · P1 (kernel + pim-compat) ≈ 3–5 wks · P2 (routes/middleware/67
  controllers) ≈ 6–10 wks (parallelizable) · P3 (shed + C-4 re-encrypt) ≈ 2–4 wks · P4 ≈ 2 wks.
- A Slim-4 variant is ~2–3 wks cheaper to scaffold but adds request/response shim churn across 67
  controllers — not worth it here.

## 7. Decision points (need owner sign-off before P1)

1. **Target:** Symfony 7 lean kernel (recommended) vs. Slim 4.
2. **`pim-compat` extraction** (recommended) vs. full entity migration off `Base` (much larger).
3. **C-4 fold-in:** migrate legacy CBC → AEAD as part of P3 (recommended — single re-encrypt) vs.
   separately.
4. **Sequencing vs. interim:** confirm we ship the `069 §5.2` interim (composer audit + WAF +
   risk memo) now and schedule P0–P4 as a dedicated quarter.

## 8. Verification (definition of done)

- [ ] Silex + contentfly PIM-CMS removed from `composer.json`/tree; only `pim-compat` remains.
- [ ] `composer audit` clean; PHP 8.3 on supported, patched components.
- [ ] P0 parity suite green on the new kernel; tenant-isolation fuzz passes.
- [ ] C-4 fields re-encrypted under AEAD; no CBC `StringType` left.
- [ ] Session-lock concurrency optimization preserved (measured).
