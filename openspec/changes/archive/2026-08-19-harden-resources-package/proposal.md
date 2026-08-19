## Why

An audit of the package surfaced three classes of problems that reach the two
production consumers (Anton, KB) directly, because both track `dev-main`:

1. **Authorization holes.** Every Livewire component exposes a
   `removeResource()` method that deletes rows *globally* by `url` or `id`
   without checking that the row belongs to the component's model. Livewire
   method calls are client-controlled, so any user who can reach any edit page
   can delete arbitrary `resources` rows of arbitrary models.
2. **Unauthenticated diagnostics.** The package unconditionally registers
   `/resources-check*` web routes in every host application. They are reachable
   without authentication, and `check/provider.blade.php` renders the raw
   provider config — including `api_token` — without redaction. Today that page
   returns HTTP 500 (`Undefined variable $result`), so the leak is latent, not
   active; fixing the 500 without gating the route would turn it into a real
   credential disclosure.
3. **Untrusted third-party markup rendered unescaped.** Seven of the nine
   provider result views build HTML strings from raw API response fields and
   emit them through `{!! !!}`. `manual-input` additionally stores an unvalidated
   URL that is later rendered as `href`, so a `javascript:` URL becomes stored
   XSS.

On top of that, `ResourceSyncService::__construct()` fires a live Wikidata
SPARQL request on *every* instantiation — including on every save in every
Livewire component — which makes saving a resource slow and dependent on
`query.wikidata.org` being up.

## What Changes

### Security

- Scope every `removeResource()` in the Livewire components and in
  `HasResources`/`Resource` to the owning model's `resources()` relation, so a
  component can only delete what it displays.
- Make the `/resources-check*` diagnostics routes **opt-in** via
  `resources.diagnostics.enabled` (default `false`) with configurable
  middleware (`resources.diagnostics.middleware`, default `['web']`).
  **BREAKING for the diagnostics UI only** — the routes disappear until enabled;
  no model, trait, component, or config-provider contract changes.
- Redact secret-looking provider config keys (`api_token`, `user_name`,
  anything matching `*token*`/`*secret*`/`*password*`/`*key*`) in every
  diagnostics view.
- Escape all provider-supplied text and URLs in the result views, and restrict
  rendered/stored resource URLs to the `http`/`https` schemes.
- Validate manual input (`provider` required, `url` required + `http(s)` URL)
  before saving — `rules()` currently uses `$`-prefixed keys and `validate()` is
  never called, so no rule has ever applied.
- Send the Anton `api_token` as an `Authorization: Bearer` header instead of a
  query parameter, so tokens stop appearing in access logs, proxies and
  `Referer` headers. Transport is configurable
  (`api_token_transport: header|query`, default `header`) for a fast rollback.
- Add `rel="noopener noreferrer"` to every `target="_blank"` link.

### Correctness and robustness

- Cache the Wikidata SPARQL provider bootstrap in `ResourceSyncService`
  (configurable TTL, default 24h) instead of requesting it per instantiation.
- Declare `ResourceSaved::$model_id` as a real property. Both consumers read
  `$event->model_id`, which is currently an undeclared dynamic property
  (deprecated since PHP 8.2) — the `public $model` property it shadows is never
  populated.
- Fix the `/resources-check/provider/{provider}` 500 by actually running the
  provider search in the controller and passing `result`, `endpoint`,
  `availableEndpoints` and `showAll` to the view.
- Remove PHP deprecations: `http_build_query($filters, null, …)` in `Gnd`,
  dynamic `$client` properties in `Idiotikon`/`Ortsnamen`, implicit nullable
  parameters (`string $endpoint = null`) in `ProviderSelect` and `LabelHelper`.
- Route `Idiotikon`, `Ortsnamen`, `Metagrid`, `Anton`, `Wikipedia` and
  `Wikidata` through `HttpClientTrait::safeHttpGet()`, and stop dereferencing
  `$body->results` / `$body->meta` without a guard.
- Honour `base_url` from the config in every client — `Gnd`, `Geonames`,
  `Idiotikon`, `Ortsnamen` and `Metagrid` currently hardcode a URL, and for
  `idiotikon`/`metagrid` the hardcoded value even differs from the configured
  one.
- Read the User-Agent from config only; `env()` at runtime returns `null` under
  `php artisan config:cache`.
- Drop the stale `config('sources-components.gnd.limit')` lookup in `Gnd`.
- Remove the dead `ProviderComponentTrait::saveResourceToModel()` — it calls
  `updateOrCreateResource()` with four positional arguments against a signature
  that takes one array, so it would fatal if it were ever called.
- Drop the unused, abandoned `survos/wikidata` dependency (no `use` anywhere in
  `src/` or `tests/`).

### Test and tooling hygiene

- Convert `tests/Api/*` to Guzzle `MockHandler` fixtures so CI stops depending
  on eight live third-party APIs; keep the live calls behind an opt-in
  `RESOURCES_LIVE_API_TESTS=1` group.
- Add PHP 8.4 to the CI matrix.
- Fix `copy-views-testbench.sh` (it copies into a `…/kraenzle-ritter/components/…`
  path that does not match this package) and the README's `resources:fetch`
  command name (the real signature is `resources:test-resources`).

## Capabilities

### New Capabilities

- `resource-ownership`: which resource rows a request may create, modify or
  delete, and how removal is scoped to the owning model.
- `resource-link-safety`: validation of stored resource URLs and escaping of
  provider-supplied text and URLs in the rendered views.
- `package-diagnostics`: the `/resources-check*` routes — when they are
  registered, what middleware protects them, and how secrets are redacted.
- `provider-http`: the shared HTTP contract for all provider clients —
  configured base URL, timeouts, User-Agent, credential transport and failure
  containment.
- `resource-sync`: caching and failure behaviour of the Wikidata/Metagrid
  expansion in `ResourceSyncService`.

### Modified Capabilities

_(none — `openspec/specs/` is empty; this change establishes the first specs.)_

## Impact

**Code**

- `src/Http/Livewire/*LwComponent.php`, `src/Http/Livewire/ResourcesList.php` —
  scoped removal, validation
- `src/Resource.php`, `src/HasResources.php` — scoped removal helpers, URL
  validation on write
- `src/Http/Controllers/ResourcesCheckController.php`, `routes/web.php`,
  `resources/views/check/*.blade.php` — opt-in routes, redaction, 500 fix
- `resources/views/livewire/*.blade.php` — escaping, `rel="noopener"`
- `src/Gnd.php`, `src/Geonames.php`, `src/Idiotikon.php`, `src/Ortsnamen.php`,
  `src/Metagrid.php`, `src/Anton.php`, `src/Wikipedia.php`, `src/Wikidata.php`,
  `src/Traits/HttpClientTrait.php` — shared HTTP behaviour
- `src/ResourceSyncService.php` — SPARQL cache
- `src/Events/ResourceSaved.php` — declared `$model_id`
- `src/Helpers/UserAgent.php`, `src/Helpers/LabelHelper.php`
- `config/resources.php` — new `diagnostics` block, `api_token_transport`,
  `sync.cache_ttl`
- `composer.json` — remove `survos/wikidata`
- `.github/workflows/run-tests.yml`, `copy-views-testbench.sh`, `README.md`

**Consumers**

- Anton and KB keep working unchanged: `HasResources`, `Resource`,
  `updateOrCreateResource(array)`, `syncFromProvider()`, the `@livewire(...)`
  call signatures, provider keys and `config('resources.rename')` are all
  untouched. `$event->model_id` keeps working and stops being a dynamic
  property.
- The only visible change is that `/resources-check` returns 404 until
  `RESOURCES_DIAGNOSTICS=true` is set. Neither app links to it.
- Anton instances must accept `Authorization: Bearer <token>`; Laravel's token
  guard does. `api_token_transport: query` restores the old behaviour without a
  code change if an instance turns out not to.
- **Rollout gate:** once the Bearer transport is verified against a live Anton
  instance, an issue is filed in the Anton repository and in the KB repository.
  Neither app is deployed or `composer update`d until its issue is fixed and
  released.

**Risk**

Consumers track `dev-main`, so this ships straight to production. Mitigation:
every behavioural change lands with a regression test first, and the two
behaviour changes a consumer could notice (diagnostics routes, Anton token
transport) are config-switchable back to today's behaviour.
