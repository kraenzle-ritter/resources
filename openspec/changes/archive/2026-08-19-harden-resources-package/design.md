## Context

The package is consumed by Anton (`~/Sites/anton.test`) and KB (`~/Sites/kb`),
both pinned to `dev-main`. There is no release gate: a merge to `main` is a
production deploy for both. Neither application wraps the package's Livewire
components in an authorization layer of its own — the components are dropped
into edit views that are already behind the application's `auth` middleware, and
everything below that is trusted to the package.

Current state relevant to this change:

- All nine provider components expose `removeResource($url)` as a public
  Livewire method that runs `Resource::where('url', $url)->delete()`.
  `ResourcesList::removeResource($id)` delegates to
  `Resource::find($id)->delete()`. Neither consults `$this->model`.
- `routes/web.php` registers five `/resources-check*` routes with `['web']`
  middleware, unconditionally, in every host application.
- `check/config.blade.php` already masks `api_token`; `check/provider.blade.php`
  does not, and currently throws `Undefined variable $result` before it gets
  the chance to leak anything. `geonames.user_name` is masked nowhere.
- Seven provider blades build HTML by string concatenation from raw API fields
  and hand it to `{!! !!}` in `partials/results-layout.blade.php`. The Wikipedia
  blade is the outlier that already calls `htmlspecialchars()`.
- `ResourceSyncService::__construct()` calls `setUpProviders()`, which issues a
  SPARQL query to `query.wikidata.org` — on every construction, including from
  `HasResources::syncFromProvider()` which every component calls after a save.
- `ResourceSaved::__construct()` writes `$this->model_id` while declaring
  `public $model`. Both applications read `$event->model_id` with a
  `@phpstan-ignore-line` on the call site.

## Goals / Non-Goals

**Goals:**

- Close the authorization, disclosure and injection issues without changing any
  API that Anton or KB call.
- Make provider failures uniform: log, return empty, never throw.
- Remove the per-save SPARQL round trip.
- Get CI off live third-party APIs so the suite is deterministic.
- Leave a documented switch for each of the two behaviour changes a consumer
  could notice, so a rollback is a config edit rather than a revert.

**Non-Goals:**

- No change to the database schema, to provider keys, or to
  `config('resources.rename')`. Provider keys are persisted values.
- No redesign of the Livewire component hierarchy. The nine near-duplicate
  components stay as they are; only the shared behaviour moves into traits.
- No new authentication/authorization model for the package. Ownership scoping
  is a data-integrity guarantee, not a permissions system — the host app still
  owns "may this user edit this model at all".
- No change to the `full_json` column semantics.
- IdRef is a separate change (`add-idref-provider`).

## Decisions

### D1 — Scope removal through the morph relation, not through a policy

`removeResource()` becomes `$this->model->resources()->where(...)->delete()`
inside the components, and `HasResources::removeResource()` /
`Resource::removeResource()` gain a model-scoped variant.

*Why:* the components already hold the authoritative model in `$this->model`,
which Livewire signs and rehydrates; the client cannot swap it. Scoping the
query is a one-line change per component with no new abstraction.

*Alternative considered:* a Laravel policy / `Gate::authorize()`. Rejected —
it would require every consumer to register a policy for
`KraenzleRitter\Resources\Resource`, which is a breaking change for two apps
that currently register nothing.

*Alternative considered:* signing the id into the wire payload. Rejected as
more machinery for the same result.

To avoid nine copies of the same query, the scoped removal lands in
`Traits\ProviderComponentTrait` as `removeResourceByUrl(string $url): bool` and
each component's public `removeResource($url)` delegates to it. The public
method name stays, because the blades call `wire:click="removeResource(...)"`
and applications may have overridden those blades.

### D2 — Diagnostics routes become opt-in, defaulting to off

`routes/web.php` is only loaded when `resources.diagnostics.enabled` is true,
and the route group takes its middleware from
`resources.diagnostics.middleware` (default `['web']`).

*Why:* the package has no way to know what "admin" means in a host app, and it
should not be adding publicly reachable routes as a side effect of
`composer require`. Default-off is the only safe default for two apps that are
already deployed.

*Alternative considered:* keep the routes and just add `auth`. Rejected —
`auth` in Anton/KB admits every logged-in user, and the page is a debugging
tool that dumps configuration; it should not exist in production at all unless
someone asks for it.

*Alternative considered:* move the diagnostics into an artisan command only.
Rejected — the HTML page is genuinely useful when debugging a provider, and
`TestResourcesCommand` already covers the CLI case (badly; it is fixed
separately).

Redaction is implemented once, in a `Helpers\ConfigRedactor::redact(array $config): array`,
and applied in the controller before the config reaches any view — so a future
view cannot reintroduce the leak. The key pattern is
`/token|secret|password|api_key|^key$|^user_name$/i`.

### D3 — Escape at the closure boundary, keep `{!! !!}`

The `result_heading` / `result_content` closures keep returning HTML — that is
what `results-layout` expects and what an application's overridden blade may
rely on — but every interpolated value inside them is passed through `e()`, and
every URL through a new `Helpers\UrlHelper::safe(?string $url): ?string` that
returns `null` for anything that is not `http`/`https`.

*Why:* the alternative — escaping inside `results-layout` — would double-escape
the intentional `<a>` and `<br>` markup and break every overridden blade.
Escaping at the source keeps the contract ("closures return trusted HTML") and
makes each blade individually verifiable.

*Trade-off:* the rule lives in seven blade files rather than one place, so it
can be forgotten in a future provider. Mitigated by a test that feeds a hostile
fixture through every provider component and asserts the payload does not
survive, so a new provider that forgets `e()` fails CI.

### D4 — URL validation in `updateOrCreateResource()`, explicitly **not** in a model event

The `http`/`https` restriction is enforced in `Resource::updateOrCreateResource()`
— the path the trait, the components and `ResourceSyncService` all go through —
with the Livewire validation in `ManualInputLwComponent` as the user-facing
layer that produces a readable message. A `saving`/`creating` model event is
explicitly rejected.

*Why not a model event:* KB writes resources straight through the Eloquent
relation, bypassing `updateOrCreateResource()` entirely:

```php
// ~/Sites/kb/app/Place.php:159
public function setGeonamesIdAttribute($value)
{
    $this->resources()->updateOrCreate(
        ['provider' => 'geonames'],
        ['provider_id' => $value]      // <- no url at all
    );
}
```

`setWikidataIdAttribute()` does the same, and `KbUpdateKbaPlaces` drives both.
The `url` column is `string` NOT NULL (see the migration stub), so these rows
land with `url = ''`. A model-level event asserting "url must be a valid
http(s) URL" would break that command on its next run.

Anton has two more direct writers that bypass the trait —
`SikIseaImportActors` (`Resource::firstOrNew(...)` then `->url = 'http://…'`)
and `BeaconReader`, which builds its URL from a BEACON `target` template
(`str_starts_with($array[2], 'http') ? … : str_replace('{id}', …)`) and can
therefore emit whatever a third-party BEACON file declares.

*Consequence:* the rule is **"if a url is present, it must be http/https"** —
an absent or empty url is left alone, because it is a legitimate, in-use
pattern for identifier-only rows. The write-path check catches the package's
own writers; the rendering layer refuses to emit a non-http(s) `href`, and that
is what actually neutralises legacy rows and the writers that bypass the trait.

*Follow-up (not this change):* KB's identifier-only rows and Anton's BEACON
import should eventually go through `updateOrCreateResource()` so they get the
same treatment. That is a consumer-side change, filed separately.

### D5 — Anton token moves to an `Authorization: Bearer` header

`Anton::search()` sets the header by default and keeps the query-parameter
transport behind `config('resources.providers.<key>.api_token_transport')`.

*Why:* a token in the query string ends up in the upstream web server's access
log, in any proxy in between, and in the `Referer` of any link the API's
response causes the browser to follow. Laravel's token guard reads
`Authorization: Bearer` as well as `api_token`, and all three Anton instances
are Laravel applications.

*Risk:* an Anton instance behind a proxy that strips `Authorization`. Mitigated
by the config switch — restoring the old behaviour is an env/config edit, not a
package release.

**Update after implementation — the gate was unnecessary.** Reading Anton's
`app/Http/Middleware/ApiToken.php` settled it: `$request->bearerToken()` is the
*preferred* path there and the `?api_token=` query parameter is the documented
legacy fallback, deprecated, logged on every hit and scheduled for removal
(anton#195). KB's equivalent reads the Bearer header only and 403s otherwise.
The package was the laggard this change migrates, not the server — so no issue
needs filing and no consumer deploy is blocked. The original plan, kept for the
record:

The server side of this is Anton itself: the package is the client, the three
Anton instances are the API. So the order was to be —

1. implement the Bearer transport in the package,
2. verify it against a live Anton instance,
3. only then file an issue in the Anton repo
   (`ssh://git@git.k-r.ch:2222/kraenzle-ritter/anton.git`) and in the KB repo
   (`git@gitlab.com:andreas_kraenzle/karl_barth.git`),
4. both apps fix and release,
5. **then** deploy / `composer update` the package in either app.

No consumer is updated while its issue is open. If an app cannot be fixed in
time it sets `api_token_transport: query` for its providers, which unblocks the
package release without unblocking the security fix — the issue stays open until
it is really fixed.

### D6 — Cache the SPARQL bootstrap in Laravel's cache

`setUpProviders()` wraps its request in
`Cache::remember('kr-resources:wikidata-provider-urls', $ttl, fn () => …)`, and
only caches a non-empty result; a failed lookup returns `[]` without writing to
the cache.

*Why:* the query's answer is a set of Wikidata URL-formatter patterns (P1630)
for a fixed list of properties. It changes on the order of months. Caching it
removes one network round trip from every single save.

*Alternative considered:* shipping the patterns as a static config array.
Rejected — it would need manual maintenance and the dynamic lookup is the
fallback path that makes new `wikidata_property` entries work without a code
change.

*Alternative considered:* lazy initialisation (only query when the fallback path
is actually reached). Worth doing **in addition**: `setUpProviders()` moves out
of the constructor and behind a lazy accessor, so a `syncFromProvider()` whose
providers all resolve through the primary config path never triggers it at all.

### D7 — `ResourceSaved` gains a declared `$model_id`

`public $model` is dropped (it was never assigned, so nothing can be reading a
meaningful value from it) and `public int $model_id` is declared and assigned.

*Why:* both consumers already read `$event->model_id`; today it works only
because PHP still permits dynamic properties with a deprecation notice.
Declaring it is the fix and removes the `@phpstan-ignore-line` need on the
consumer side.

*Checked:* `grep` over both applications shows no read of `$event->model`.

### D8 — Deterministic provider tests via Guzzle `MockHandler`

Each provider client gets an injectable client:
`__construct(?ClientInterface $client = null)`, defaulting to the configured
Guzzle client. Tests inject a `MockHandler` stack.

*Why:* the current `tests/Api/*` suite makes ~15 live HTTP calls, takes 22s and
fails whenever GeoNames rate-limits the demo account (two tests already skip
for that reason). Constructor injection is additive and does not break the
`new Gnd()` calls in Anton's `LobidApiController`.

The live tests are kept, moved into a `live-api` PHPUnit group, and excluded by
default in `phpunit.xml`; CI can run them on a schedule.

### D9 — `TestResourcesCommand` is rewritten, not deleted

**Decided: rewrite.** The command is the only headless way to notice that a
provider API has gone away, and the diagnostics page (D2) is opt-in and
interactive, so it cannot fill that role for cron or CI.

*Current state:* it checks `class_exists()` and
`method_exists($className, 'search')`, never instantiates anything, never calls
`search()`, and always returns `Command::SUCCESS`. Its class-name derivation is
`ucfirst($providerKey)`, which cannot resolve the keys that actually carry an
`api-type` — `georgfischer`, `gosteli` and `kba` all map to the `Anton` class,
`manual-input` and `wikipedia-de` are not class names at all — so it reports
"class not found" for 12 of the 18 API providers. It is not merely useless, it
is actively misleading.

*Consumer safety:* neither Anton nor KB invokes it — verified by grep over both
repositories including scheduler, deploy scripts and KB's justfile. So the
rewrite has no consumer blast radius and needs no coordination.

*The fix is to use the information that is already in the config.* The client
class is `KraenzleRitter\Resources\{$config['api-type']}` and the query term is
`$config['test_search']`; both are already declared per provider. What differs
is construction and call shape, and that is the part worth encoding explicitly:

| `api-type` | construction | search call |
| --- | --- | --- |
| `Anton` | `new Anton($providerKey)` | `search($term, ['limit' => n], $endpoint)` |
| `Wikipedia` | `new Wikipedia()` | `search($term, ['providerKey' => $providerKey])` |
| `ManualInput` | — | skipped, not an API provider |
| everything else | `new {ApiType}()` | `search($term, ['limit' => n])` |

*Why not a `search()` interface on the clients:* it would be the cleaner
long-term shape, but `Anton::search()` has a third parameter and `Wikipedia`
needs the provider key, so an interface would either lie about the signature or
force a change to clients that Anton instantiates directly
(`LobidApiController` does `new Gnd()`). Deferred; the table above lives in the
command until the clients are unified.

*Output:* a table (provider, api-type, status, result count, duration, error),
`Command::FAILURE` if any provider failed, plus `--provider=`, `--timeout=` and
`--json` so a monitoring job can consume it.

### D10 — Livewire components resolve provider clients from the container

The components construct their client inline (`$client = new Gnd();` in
`render()`), which leaves no seam for a test to substitute a mock — so the
component tests reached the live APIs even after the clients themselves became
injectable (D8). They now resolve through the container instead:

```php
$client = new Gnd();   ->   $client = app(Gnd::class);
```

`ResourcesServiceProvider` already binds all seven parameterless clients, so
this adds no new registration. `AntonLwComponent` uses
`app()->makeWith(Anton::class, ['providerKey' => $this->providerKey])` because
Anton takes its provider key as a constructor argument.

*Why:* it is a one-line, behaviour-preserving change per component that makes
every later task group (ownership scoping, escaping, IdRef) testable without
network. Doing it in group 1 avoids writing those tests twice.

*Consequence for the suite:* `TestCase::setUp()` binds every provider client to
a fixture-backed client by default, so no test can reach a live API by
accident. Tests in the `live-api` group are exempted via `$this->groups()`, so
they still exercise the real services.

*Alternative considered:* a `protected function makeClient()` per component that
tests override via a subclass. Rejected — it needs a test double class per
component, where the container binding needs none.

The same seam was needed twice more before the suite was genuinely offline:
`ResourceSyncService` takes an optional `ClientInterface` (its four internal
`new Client(...)` calls now go through one `client()` factory), and
`HasResources::syncFromProvider()` resolves the service through
`app()->makeWith(ResourceSyncService::class, ['filter' => $filter])` instead of
constructing it. Both are behaviour-preserving and keep the consumer-facing
signature `$model->syncFromProvider($provider, $filter)` unchanged.

## Risks / Trade-offs

- **Consumers deploy from `main`; a mistake is immediately live.** → Every
  behavioural change lands with its regression test written first and the full
  suite green before commit. The two consumer-visible changes (diagnostics
  routes, Anton token transport) are config-switchable back to today's
  behaviour without a code change.
- **Scoping removal could break a legitimate flow that deletes across models.**
  → Verified: neither Anton nor KB calls `removeResource` other than through the
  components and `$model->removeResource($id)`. `Resource::where(...)->delete()`
  in the applications' own commands is unaffected.
- **Turning the diagnostics routes off could surprise someone who bookmarked
  them.** → Documented in the README with the exact env flag; both apps get told
  in the changelog entry.
- **URL validation could reject a legitimate scheme in use somewhere**
  (e.g. an `ark:` or `urn:` identifier stored as `url`). → Before implementing,
  run a distinct-scheme query over both production `resources` tables; if
  anything other than `http`/`https` exists, the allow-list gets extended
  through config rather than the rule being dropped.
- **Escaping changes rendered output.** A provider label that legitimately
  contained markup (none observed) would now show the tags. Acceptable.
- **Bearer header vs. query token** → see D5.
- **The SPARQL cache can serve a stale URL pattern for up to 24h.** Acceptable;
  the pattern set is effectively static, and the TTL is configurable.

## Migration Plan

1. Land the test-only changes first (MockHandler fixtures, `live-api` group) so
   the suite is deterministic before behaviour moves.
2. Land the non-behavioural fixes (deprecations, dead code, `survos/wikidata`
   removal, README, CI matrix). Safe to ship alone.
3. Land `ResourceSaved::$model_id` — verified compatible with both consumers.
4. Land the ownership scoping with its regression tests.
5. Land URL validation + view escaping, after the scheme survey named above.
6. Land the diagnostics gating and redaction, with the 500 fix.
7. Land the sync cache and lazy bootstrap.
8. After merge, run each consumer's own test suite
   (`~/Sites/anton.test`, `~/Sites/kb`) against the updated symlinked package
   and smoke-test one edit page per app: search, save, list, delete.
9. **Gate before any consumer deploy:** the Bearer issues filed in the Anton and
   KB repositories (see D5) are fixed and released. Neither app is updated while
   its issue is open.

**Rollback:** `composer.lock` pinning to the previous commit in each app, or —
for the two config-switchable behaviours — set `RESOURCES_DIAGNOSTICS=true` and
`api_token_transport: query`.

## Open Questions

- Do any rows in the production `resources` tables carry a `url` whose scheme is
  neither `http` nor `https`? The survey (task 0.2) is worth running, but it no
  longer blocks: D4 only validates a url that is actually present, and all 18
  `target_url` templates in the package config are `https`. The realistic
  finding is empty urls from KB's identifier-only writes, which D4 now permits
  explicitly.
- Should the diagnostics page be enabled in Anton/KB staging by default? The
  package defaults to off; whether the apps opt in is their call.
_(The `TestResourcesCommand` question is settled — see D9.)_
