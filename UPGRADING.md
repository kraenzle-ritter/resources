# Upgrading

## Unreleased — security hardening

Anton and KB both track `dev-main`, so this reaches them on the next
`composer update`. Read the **Action required** section before updating.

### Action required

#### 1. The diagnostics routes are now opt-in

`/resources-check`, `/resources-check/config` and
`/resources-check/provider/{provider}` are no longer registered by default —
they return 404 and the routes do not exist.

They render provider configuration, so a package should not add them to a host
application as a side effect of `composer require`.

To get them back:

```dotenv
RESOURCES_DIAGNOSTICS=true
```

and optionally, in a published `config/resources.php`:

```php
'diagnostics' => [
    'enabled' => env('RESOURCES_DIAGNOSTICS', false),
    'middleware' => ['web', 'auth'],   // default is ['web']
],
```

Neither Anton nor KB links to these pages, so no action is needed unless you
use them for debugging.

#### 2. Anton API tokens now travel in an `Authorization` header

`Anton::search()` sends `Authorization: Bearer <token>` instead of an
`api_token` query parameter, so tokens stop appearing in access logs, proxy
logs and `Referer` headers.

**No server-side change is needed.** Anton's `ApiToken` middleware already
treats the Bearer header as the preferred method and marks the query parameter
as the deprecated legacy fallback, logging every hit on it so the remaining
callers can be migrated (anton#195) — this package was one of them. KB's
equivalent middleware reads the Bearer header *only* and answers 403 otherwise.

The transport is still switchable per provider if some other Anton installation
turns out to need the old behaviour:

```php
'georgfischer' => [
    // ...
    'api_token_transport' => 'query',   // default is 'header'
],
```

Note that the endpoints this package searches by default (`actors`, `places`,
`keywords`) are public on all three instances; only `objects` sits behind the
token middleware.

#### 3. Resource URLs are restricted to `http` and `https`

`updateOrCreateResource()` and `updateResource()` now reject a URL whose scheme
is anything else — `javascript:` and `data:` URLs were storable and were later
rendered into an `href`.

- A resource written **without** a url is still accepted: the identifier-only
  pattern (KB's `Place::setGeonamesIdAttribute()`) keeps working.
- Existing rows are untouched. A stored unsafe url is simply no longer rendered
  as a live link.
- Widen the list if you store other schemes:

```php
'allowed_url_schemes' => ['http', 'https', 'urn'],
```

Run this against your `resources` table first if you are unsure:

```sql
SELECT SUBSTRING_INDEX(url, ':', 1) AS scheme, COUNT(*)
FROM resources GROUP BY scheme ORDER BY 2 DESC;
```

#### 4. New provider: IdRef

The French authority file (ABES) is now searchable, not just a label. Two
consequences:

- To offer it in the UI, add `idref` to the provider list your application
  passes to `@livewire('provider-select', ...)`.
- **It also affects data you already have.** `idref` declares
  `wikidata_property: P269` *and*, new in this release, a `target_url` — which
  is what makes `ResourceSyncService` build a link. Any `syncFromProvider()`
  run will now create `idref` resources for Wikidata items carrying a P269
  claim. That is the intended behaviour; add `idref` to your filter list
  (Anton: `setting('resources_filter')`) to suppress it.

Which record types a search returns is driven by the endpoint the component is
mounted with — see `endpoint_record_types` in the config and the README.

### Security fixes

- **Resource removal was not scoped to the owning model.** Every Livewire
  component's `removeResource()` deleted by url or id across *all* models, and
  Livewire method calls are client-controlled — so any user with access to any
  edit page could delete arbitrary rows. Removal now goes through the model's
  own `resources()` relation. `$model->removeResource($id)` still returns
  `true` for an owned row and now returns `false` instead of deleting a foreign
  one.
- **Provider responses were rendered unescaped.** Seven of the nine result views
  built HTML from raw API fields and emitted it through `{!! !!}`. All
  provider-supplied text now goes through `e()` and all URLs through
  `UrlHelper::safe()`.
- **Manual input was never validated.** `rules()` used `$`-prefixed keys and
  `validate()` was never called, so no rule had ever applied.
- **The diagnostics provider page rendered `api_token` in clear text.** It was
  masked nowhere and the page was reachable without authentication. Secrets are
  now redacted in the controller, before any view sees the config.
- External links carry `rel="noopener noreferrer"`.

### Behaviour changes worth knowing

- `ResourceSyncService` no longer performs a SPARQL query in its constructor.
  The lookup is lazy and cached for `config('resources.sync.cache_ttl')`
  (default 86400s), so saving a resource no longer blocks on
  `query.wikidata.org`.
- `Metagrid::search()` returned `null` for "no match" and `[]` for failures. It
  now always returns an array.
- Two configured base URLs were dead and are corrected: `api.idiotikon.ch` does
  not resolve (now `digital.idiotikon.ch/api/`) and `metagrid.ch/api/` answers
  404 (now `api.metagrid.ch/`). `ortsnamen` points at the redirect target
  directly. These were harmless before only because the clients hardcoded the
  working host — they now honour the config.
- `ResourceSaved::$model_id` is a declared property. It used to be created
  dynamically (deprecated since PHP 8.2), which is why both apps carry a
  `@phpstan-ignore-line` at the read site. **That comment can now be removed.**
  The never-populated `$model` property is gone.
- `resources:test-resources` actually tests providers now: it resolves the
  client from `api-type`, runs each provider's `test_search`, prints a table and
  exits non-zero on failure. `--provider=`, `--json` and `--timeout=` are
  available. The old version only checked `class_exists(ucfirst($key))`, which
  could not resolve 12 of the 18 API providers, and always exited 0.
- `UserAgent::get()` reads config only. It called `env()` at request time, which
  returns `null` once a host application has run `php artisan config:cache`.
- `survos/wikidata` (abandoned, unused) has been removed from `composer.json`.

### Unchanged

The API both applications use is untouched: the `HasResources` trait and all
its methods, `updateOrCreateResource(array)`, `syncFromProvider($provider,
$filter)`, the `Resource` model, direct instantiation of `Gnd`, `Wikipedia` and
the other clients, the `@livewire('resources-list', ...)` and
`@livewire('provider-select', ...)` call shapes, all provider keys, and
`config('resources.rename')`.

`tests/PublicApiCompatibilityTest.php` pins that surface so it cannot drift
unnoticed.
