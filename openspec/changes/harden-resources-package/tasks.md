## 0. Pre-flight

- [x] 0.1 Record the baseline: `composer install && ./vendor/bin/phpunit` and note the pass/skip counts, so every later step can be compared against it.
- [ ] 0.2 Survey the production `resources` tables in Anton and KB for URL schemes — run inside the app container, the DB host is `db`: `SELECT SUBSTRING_INDEX(url, ':', 1) AS scheme, COUNT(*) FROM resources GROUP BY scheme ORDER BY 2 DESC;` plus `SELECT COUNT(*) FROM resources WHERE url = '' OR url IS NULL;`. Expect a non-zero empty-url count in KB (see design D4). Feeds the allow-list in task 5.1; does **not** block it.
- [x] 0.3 Confirm no consumer reads `ResourceSaved::$model` (only `$event->model_id`): `grep -rn 'ResourceSaved' -A6 ~/Sites/anton.test/app ~/Sites/kb/app`.

## 1. Deterministic tests before behaviour changes

- [x] 1.1 Add `?ClientInterface $client = null` constructor injection to `Gnd`, `Geonames`, `Idiotikon`, `Ortsnamen`, `Metagrid`, `Wikidata`, `Wikipedia` and `Anton`, defaulting to the client they build today. No call-site changes.
- [x] 1.1a Resolve provider clients through the container in the Livewire components (`new Gnd()` -> `app(Gnd::class)`, `new Anton($key)` -> `app()->makeWith(...)`). The ServiceProvider already binds all of them; this only adds the seam a test needs to substitute a mock. Behaviour-preserving.
- [x] 1.1b Rewrite the Livewire component tests to bind a mocked client (`$this->app->bind(Gnd::class, fn () => new Gnd($mock->client))`) instead of reaching the live API.
- [x] 1.2 Add `tests/Support/MockProviderClient.php` — a helper that builds a Guzzle client from a `MockHandler` queue plus a request-history middleware, so tests can assert on outgoing headers and query strings.
- [x] 1.3 Add JSON fixtures under `tests/fixtures/<provider>/` captured from the real APIs (success, empty, malformed-JSON, unexpected-shape).
- [x] 1.4 Rewrite `tests/Api/*` to use the mock client and fixtures; assert the parsed shape rather than "some results came back".
- [x] 1.5 Move every remaining live-network test into the PHPUnit group `live-api` and exclude that group by default in `phpunit.xml`.
- [x] 1.6 Add a scheduled CI job that runs `--group live-api` so upstream API drift is still noticed.
- [x] 1.7 Verify: full suite green (112 tests, 273 assertions), 26s -> 7.5s. Provider APIs are fully offline; every client is bound to a fixture by default in `TestCase::setUp()` and only the `live-api` group gets the real ones. **Residual outbound HTTP:** `ResourceSyncService` builds its own Guzzle clients for the Wikidata/SPARQL calls, which no container binding can intercept — ~3.6s of the remaining runtime. Closing that is task 7.3; re-verified in 7.8.

## 2. Non-behavioural cleanup

- [x] 2.1 Fix `Gnd::buildFilter()`: `http_build_query($filters, '', ' AND ')` instead of passing `null`.
- [x] 2.2 Declare the `$client` property on `Idiotikon` and `Ortsnamen` (currently created dynamically).
- [x] 2.3 Replace implicit nullable parameters with explicit ones: `ProviderSelect::mount(..., ?string $endpoint = null, ...)`, `LabelHelper::getLocalizedLabel(..., ?string $locale = null)`, `LabelHelper::getProviderLabel(..., ?string $locale = null)`.
- [x] 2.4 Remove the dead `ProviderComponentTrait::saveResourceToModel()` (calls a four-argument signature that does not exist).
- [x] 2.5 Remove the stale `config('sources-components.gnd.limit')` lookup in `Gnd::search()`.
- [x] 2.6 `UserAgent::get()` reads `config('resources.user_agent')` only; add a `user_agent` key to `config/resources.php` that resolves `env('RESOURCES_USER_AGENT')` at config-load time.
- [x] 2.7 Remove `survos/wikidata` from `composer.json` (unused, abandoned) and re-run `composer update --lock`.
- [x] 2.8 Add PHP 8.4 to the CI matrix in `.github/workflows/run-tests.yml`.
- [x] 2.9 Fix `copy-views-testbench.sh` to target `vendor/orchestra/testbench-core/laravel/packages/kraenzle-ritter/resources/resources/views`.
- [x] 2.10 Fix the README: the command is `resources:test-resources`, not `resources:fetch`.
- [x] 2.11 Added `tests/DeprecationsTest.php`. Note: `failOnDeprecation` in `phpunit.xml` is **not** sufficient on its own — Laravel's `HandleExceptions` captures deprecations and routes them to the log before PHPUnit sees them. The test installs its own handler for the duration of each call and filters to `src/`. Verified by reintroducing both original defects (`http_build_query(..., null, ...)` and the dynamic `Idiotikon::$client`): the test goes red, and green again once reverted.
- [x] 2.12 Verified: 117 tests green on **PHP 8.3 and PHP 8.4**, no deprecations from `src/` on either. The PHP 8.4 run also confirms task 2.3 — reintroducing `string $locale = null` makes `DeprecationsTest` fail with "Implicitly marking parameter $locale as nullable is deprecated".

## 3. ResourceSaved event

- [x] 3.1 Write a failing test asserting `ResourceSaved` exposes a declared `int $model_id` and that no dynamic property is created.
- [x] 3.2 Declare `public int $model_id`, assign it in the constructor, drop the never-assigned `public $model`.
- [x] 3.3 Verified: consumer listeners read only `$event->resource` and `$event->model_id`; both apps use `increments('id')` so the declared `int` type fits. The `@phpstan-ignore-line` on their call sites can now be dropped — consumer follow-up, not changed here. Verify against both consumers: `$event->model_id` in `UpdateLocationWithGeonamesCoordinates` still resolves; the `@phpstan-ignore-line` can be dropped on their side (note it for the consumer follow-up, do not edit their code here).

## 4. Ownership scoping (spec: resource-ownership)

- [x] 4.1 Write failing tests: model A's component removing model B's resource by url leaves B's row intact; `ResourcesList` on model A removing B's resource by id leaves it intact; removing an own resource still deletes and dispatches `resourcesChanged`.
- [x] 4.2 Add `ProviderComponentTrait::removeResourceByUrl(string $url): bool` scoping the delete to `$this->model->resources()`.
- [x] 4.3 Point all nine components' public `removeResource()` at it, keeping the method name and arity that the blades call.
- [x] 4.4 Scope `ResourcesList::removeResource($id)` to `$this->model->resources()`.
- [x] 4.5 Add `Resource::removeResourceFor(Model $model, $id): bool` and make `HasResources::removeResource()` use it; keep the boolean return and the existing signature.
- [x] 4.6 Add a test that `saveResource()` writes `resourceable_id`/`resourceable_type` of the mounted model only.
- [x] 4.7 Verified: 142 tests green. `tests/ResourceOwnershipTest.php` reproduced the IDOR first — 11 of 21 red, covering all 8 provider components, `ResourcesList`, the trait's boolean contract and cross-model-type removal. Consumer contract holds: `$model->removeResource($id)` returns `true` for an owned row and now `false` (instead of deleting) for a foreign one. Re-probed by unscoping the trait again: 9 tests go red.

## 5. Link safety (spec: resource-link-safety)

- [x] 5.1 Add `Helpers\UrlHelper::isSafe(?string $url): bool` and `::safe(?string $url): ?string`, allow-listing `http`/`https` (extended by `config('resources.allowed_url_schemes')` if task 0.2 turns up others).
- [x] 5.2 Write failing tests: `javascript:` and `data:` URLs rejected on `updateOrCreateResource()`; ordinary https URLs accepted; pre-existing rows still readable; a write with **no** `url` key is accepted (KB's identifier-only pattern).
- [x] 5.3 Enforce the scheme check in `Resource::updateOrCreateResource()` only — **not** in a model `saving`/`creating` event, which would break KB's `Place::setGeonamesIdAttribute()` and Anton's `SikIseaImportActors`/`BeaconReader` (design D4).
- [x] 5.3a Add a regression test mirroring KB's write: `$model->resources()->updateOrCreate(['provider' => 'geonames'], ['provider_id' => '2657896'])` succeeds and the row lists without an `href`.
- [x] 5.4 Write failing tests for `ManualInputLwComponent`: empty form produces validation errors and creates nothing; `javascript:` URL rejected without dispatching `ResourceSaved`; valid input saved.
- [x] 5.5 Fix `ManualInputLwComponent::rules()` (drop the `$` prefixes from the keys) and call `$this->validate()` at the top of `saveResource()`.
- [x] 5.6 Write a failing test that feeds a hostile fixture (`<img src=x onerror=alert(1)>` heading, `<script>` description, `javascript:` result url) through every provider component and asserts the payload does not survive rendering.
- [x] 5.7 Escape every interpolated value in the `result_heading`/`result_content` closures of `gnd`, `geonames`, `idiotikon`, `ortsnamen`, `metagrid`, `anton` and `wikidata` blades with `e()`, and route result URLs through `UrlHelper::safe()`.
- [x] 5.8 Add `rel="noopener noreferrer"` to every `target="_blank"` anchor in `resources-list.blade.php` and the provider blades.
- [x] 5.9 Verified: 189 tests green. `HostileProviderResponseTest` reproduced the XSS across 7 providers first (24 tests, 19 red), now all green. Regression-probed by reverting the GND blade: 3 tests go red. Two findings during this group: (a) the `url` column is NOT NULL, so identifier-only writes only ever worked on non-strict MySQL — `Resource` now defaults `url` to `''` so KB's pattern is portable rather than accidental; (b) `MockProviderClient::repeating()` reused one PSR-7 Response object, whose body stream is read-once, so every request after the first got an empty body — fixed, and it was masking that some component tests rendered nothing.

## 6. Diagnostics gating (spec: package-diagnostics)

- [x] 6.1 Write failing tests: `/resources-check` is 404 by default; 200 when `resources.diagnostics.enabled` is true; the configured middleware stack is applied.
- [x] 6.2 Add the `diagnostics` block to `config/resources.php` (`enabled` from `env('RESOURCES_DIAGNOSTICS', false)`, `middleware` default `['web']`).
- [x] 6.3 Load `routes/web.php` only when enabled, and build the route group from the configured middleware.
- [x] 6.4 Write a failing test that `/resources-check/provider/{provider}` returns 200 for `gnd` (today it is a 500 on `Undefined variable $result`) and 200 with an error status when the upstream API fails.
- [x] 6.5 Fix `ResourcesCheckController::provider()` to run the provider search and pass `result`, `endpoint`, `availableEndpoints` and `showAll` to the view.
- [x] 6.6 Write a failing test that the provider page does not contain a configured `api_token` or `geonames.user_name` value.
- [x] 6.7 Add `Helpers\ConfigRedactor::redact(array $config): array` (pattern `/token|secret|password|api_key|^key$|^user_name$/i`) and apply it in the controller before the config reaches any view; drop the now-redundant inline masking in `check/config.blade.php`.
- [x] 6.8 Document the flag in the README and note the behaviour change for Anton/KB.
- [x] 6.9 Verified: 203 tests green. `/resources-check*` is 404 by default and the routes are not even registered; middleware comes from config (test proves `['web','auth']` is applied). The provider page renders for **every** configured api-type provider, and survives a 500 from the upstream API. Regression-probed both ways: dropping the redaction makes 2 tests red, forcing the routes on makes 1 red. Note: `WithConfig` is applied after the providers boot, so the route tests use `#[DefineEnvironment]` instead. Two pre-existing bugs surfaced once the page actually rendered: the `Undefined variable $result` 500, and `Str::limit()` receiving an array for providers whose description field is a list (GND, Anton) — both fixed.

## 7. Sync caching (spec: resource-sync)

- [x] 7.1 Write a failing test asserting two `ResourceSyncService` instantiations issue at most one SPARQL request.
- [x] 7.2 Move `setUpProviders()` out of the constructor behind a lazy accessor used only by the fallback path.
- [x] 7.3 Wrap the SPARQL lookup in `Cache::remember()` with `config('resources.sync.cache_ttl', 86400)`; do not cache an empty/failed result.
- [x] 7.4 Add `sync.cache_ttl` to `config/resources.php`.
- [x] 7.5 Write failing tests for the failure paths: Wikidata unreachable → `[]` + log; Metagrid payload without `concordances` → `[]` + warning; one unpersistable record does not abort the batch.
- [x] 7.6 Write failing tests for the exclusion filter: a filtered provider is skipped, an unfiltered one is still created.
- [x] 7.7 Verified: the constructor makes **no** request at all any more (test asserts request count 0), the SPARQL lookup is lazy and only reached by the fallback path, and it is cached under `resources.sync.cache_ttl` (86400). A failed lookup is deliberately not cached. `setUpProviders()` became the public, lazy `wikidataUrlPatterns()`; the legacy reflection test was rewritten onto the new public behaviour rather than deleted.
- [x] 7.8 Re-verified by running the whole suite behind a dead proxy (`http_proxy=127.0.0.1:9`) and comparing: **212 tests green, identical results and identical runtime online and offline** (~6.5s each), while the `live-api` group fails offline as a control. That flushed out three remaining live callers: `test_sync_from_wikidata_provider` (a real integration test, moved to `live-api`), `test_sync_from_metagrid_provider` (used **httpbin.org** as a fixture, now a mock), and `HasResources::syncFromProvider()` constructing the service directly — now resolved via `app()->makeWith()`, same seam as D10. The "under 5 seconds" figure from 1.7 is not met and was a bad target: it was written when the suite had 108 tests and it now has 212, each file paying a Testbench boot. ~6.5s with zero network is the honest number.

## 8. Provider HTTP consolidation (spec: provider-http)

- [x] 8.1 Write failing tests per provider: upstream 500, malformed JSON, unexpected shape and connection timeout each yield the documented empty result without throwing.
- [x] 8.2 Route `Idiotikon`, `Ortsnamen`, `Metagrid`, `Anton`, `Wikipedia` and `Wikidata` through `HttpClientTrait::safeHttpGet()`, and guard every `$body->results` / `$body->meta` / `$result->data` dereference.
- [x] 8.3 Write failing tests that each client uses the configured `base_url`.
- [x] 8.4 Make `Gnd`, `Geonames`, `Idiotikon`, `Ortsnamen` and `Metagrid` read `base_url` from config with the current hardcoded value as fallback; reconcile the two configured values that disagree with the code (`idiotikon`, `metagrid`) — correct the config to the URL that actually works.
- [x] 8.5 Write a failing test that the Anton client sends `Authorization: Bearer <token>` and no `api_token` query parameter by default, and the reverse when `api_token_transport` is `query`.
- [x] 8.6 Implement the header transport in `Anton::search()` and add `api_token_transport` (default `header`) to the three Anton providers in `config/resources.php`.
- [x] 8.6a **Resolved by reading the server side, not by a token.** All four endpoints this package calls (`actors`, `places`, `keywords`, `objects`) are public on all three Anton instances except `objects`, so no public-endpoint probe could ever distinguish "Bearer honoured" from "Bearer ignored". Anton's own `app/Http/Middleware/ApiToken.php` settles it: `$request->bearerToken()` is the **preferred** path and the `?api_token=` query parameter is the documented legacy fallback, deprecated, logged on every hit and scheduled for removal (anton#195). KB's equivalent middleware reads the Bearer header **only** and returns 403 otherwise. So the package was the laggard this change migrates, not the server. `AntonTokenTransportTest::test_live_anton_accepts_the_bearer_token` remains in the `live-api` group and skips until a token for the protected `objects` endpoint is available.
- [x] 8.6b **Not required.** The premise was that Anton would need to accept Bearer; it already prefers it, and anton#195 is explicitly waiting for callers like this one to migrate. Nothing to fix on the Anton side. Worth a comment on anton#195 that this caller is migrated — the user's call, not a package task.
- [x] 8.6c **Not required.** KB's middleware reads the Bearer header only; a query-parameter token would never have authenticated there.
- [x] 8.7 Write a failing test that the User-Agent header is correct when the config is cached.
- [x] 8.8 Write failing tests for the rewritten command: every provider with an `api-type` is reached (no "class not found" for `georgfischer`, `gosteli`, `kba`, `manual-input`, `wikipedia-de`); a provider whose client returns results is reported OK; one whose client returns empty is reported as a failure; the exit code is non-zero if any provider failed; `--provider=<key>` restricts the run to one provider; `manual-input` is skipped, not failed.
- [x] 8.9 Rewrite `TestResourcesCommand`: resolve the client class from `config('resources.providers.<key>.api-type')` (`KraenzleRitter\Resources\{$apiType}`) instead of `ucfirst($providerKey)`, and construct/call it per api-type — `Anton` takes the provider key in its constructor and an endpoint in `search()`, `Wikipedia` takes `['providerKey' => $key]`, the rest take `['limit' => …]`. Skip `api-type: ManualInput` and providers without an `api-type`.
- [x] 8.10 Report as a table (provider, api-type, status, result count, duration, error) and return `Command::FAILURE` when at least one provider failed, so the command is usable from cron and CI.
- [x] 8.11 Add `--timeout=` and `--json` options so a monitoring job can consume the output.
- [x] 8.12 Verified: 296 tests green, identical online and offline. `ProviderHttpContractTest` runs 7 failure modes x 8 providers (56 tests) and was red 18x for Idiotikon/Ortsnamen/Metagrid before the rewrite. **Two configured base URLs were dead**: `api.idiotikon.ch` does not resolve at all and `metagrid.ch/api/` answers 404 — both were harmless only because the clients hardcoded the working host, so honouring the config without checking it would have broken both providers. Corrected, and `ortsnamen` now points at the redirect target. `Metagrid::search()` returned `null` for "no match" and `[]` for failures; it now always returns an array. Original: Verify: suite green; `php artisan resources:test-resources` reports all 18 API providers with no false "class not found", and `--provider=gnd` runs exactly one.

## 9. Consumer verification and rollout gate

- [~] 9.1 **BLOCKED — not runnable here.** Anton's vendor copy is not linked to this working tree (no `packages/kraenzle-ritter/resources` checkout), and its suite needs MySQL on host `db`, i.e. inside its container. Substituted with a static compatibility audit, now pinned as `tests/PublicApiCompatibilityTest.php` (25 tests): every trait method, signature, client constructor, provider key, rename-map entry and Livewire alias the two apps use. All green.
- [~] 9.2 **BLOCKED — same reason.** KB's vendor copy is likewise not linked here and its suite needs MySQL on host `db`.
- [~] 9.3 **BLOCKED — needs the running applications.** Covered inside the package by the Livewire component tests (search, save, list, scoped delete) but not against a real Anton/KB edit page.
- [x] 9.4 Verified as far as possible without the apps running: both listeners read only `$event->resource` and `$event->model_id`, both are declared properties now, both apps use `increments('id')` so the `int` type fits, and `PublicApiCompatibilityTest` pins the event shape. The runtime confirmation still belongs to the consumer smoke test in 9.3.
- [x] 9.5 Written to `UPGRADING.md`: the three action-required items, the security fixes, the behaviour changes (sync cache, Metagrid return shape, the two dead base URLs, `ResourceSaved::$model_id` and the now-removable `@phpstan-ignore-line`, the rewritten command, UserAgent) and an explicit list of what is unchanged.
- [x] 9.6 **Gate lifted.** It was predicated on 8.6b/8.6c, which turned out not to be required: both consumers already implement the Bearer path, and Anton's tokens are not even configured in its `.env`. No consumer deploy is blocked by the token transport. The remaining consumer verification (9.1-9.3) is still outstanding but is ordinary smoke-testing, not a security gate.
- [x] 9.7 **Not needed.** The `api_token_transport: query` escape hatch stays in the config, but no consumer requires it; it now falls back onto a path Anton is deprecating and KB does not implement at all.
