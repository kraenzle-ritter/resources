# Project Context

## Purpose

`kraenzle-ritter/resources` is a Laravel package that links Eloquent models to
external authority records ("resources", e.g. a GND entry, a Wikipedia article,
a Geonames place). It ships:

- an Eloquent `Resource` model (polymorphic `resourceable` morph relation) and a
  `HasResources` trait,
- Livewire (Bootstrap 5) components to search a provider, save a link and list
  the saved links,
- provider API clients (GND/lobid, Geonames, Wikipedia, Wikidata, Idiotikon,
  Ortsnamen, Metagrid, Anton),
- a `ResourceSyncService` that expands one saved link into further links via
  Wikidata properties and Metagrid concordances.

## Consumers — compatibility is a hard constraint

The package is used in production by two in-house Laravel applications:

- `~/Sites/anton.test` (Anton) — `kraenzle-ritter/resources: dev-main`, symlinked
  from `packages/kraenzle-ritter/resources`.
- `~/Sites/kb` (KB) — `kraenzle-ritter/resources: dev-main`.

Both track `dev-main`, so **every push to `main` reaches production directly**.
A breaking change must either not happen or ship with an immediate fix.

Public API surface both apps rely on (must not break):

| Surface | Used as |
| --- | --- |
| `HasResources` trait | `use KraenzleRitter\Resources\HasResources;` on `Actor`, `Place`, `Keyword`, `Bibl`, `Song` |
| `$model->updateOrCreateResource(array $data)` | array with `provider`, `provider_id`, `url`, optional `full_json` |
| `$model->syncFromProvider(string $provider, array $filter)` | Anton: `CreateActorByGnd`, `ResourcesSync` command |
| `$model->removeResource($id)` / `$model->resources` | listings, statistics |
| `KraenzleRitter\Resources\Resource` model | queried directly (`where('resourceable_type', …)`) |
| `KraenzleRitter\Resources\Gnd`, `…\Wikipedia` | instantiated directly in app controllers/services |
| `KraenzleRitter\Resources\Events\ResourceSaved` | listeners read **`$event->model_id`** (currently a dynamic property) |
| `@livewire('resources-list', [$model, 'deleteButton' => true])` | positional + named args |
| `@livewire('provider-select', [$model, $providers, $endpoint, $filter])` | positional args, 3rd arg may be `null` |
| `config('resources.providers')` keys | the app passes provider keys as strings; keys must stay stable |
| `config('resources.rename')` | legacy provider-name normalisation on write |

Note: Anton publishes a *different-shaped* `config/resources.php` (a flat
`slug => label` map). Because `mergeConfigFrom` merges at the top level, the
package's `providers`/`table`/`limit`/`rename` keys still come from the package
defaults. New providers added to the package config therefore become available
in Anton without an app-side change.

## Tech Stack

- PHP ^8.3, Laravel >= 11, Livewire ^4.0, Guzzle >= 7
- Testing: PHPUnit >= 11.5, Orchestra Testbench ^10, Mockery ^1.6
- CI: GitHub Actions, PHP 8.3, `prefer-stable`
- `minimum-stability: dev`
- Views: Blade + Bootstrap 5 + Font Awesome, overridable via
  `resources/views/vendor/kraenzle-ritter/livewire/*`

## Project Conventions

### Code Style

- PSR-4: `KraenzleRitter\Resources\` → `src/`, tests →
  `KraenzleRitter\Resources\Tests\` → `tests/`
- StyleCI preset `laravel`
- Provider API clients live at `src/<Provider>.php`, Livewire components at
  `src/Http/Livewire/<Provider>LwComponent.php`, views at
  `resources/views/livewire/<provider>-lw-component.blade.php`
- Comments and log messages are a mix of German and English; new code is written
  in English

### Architecture Patterns

- A provider is declared once in `config/resources.php` under
  `providers.<key>` with `api-type`, `base_url`, `target_url`, `test_search`
  and optionally `wikidata_property`.
- `ProviderSelect` derives the Livewire component name from `api-type`:
  `strtolower($apiType) . '-lw-component'`. Adding an api-type therefore means
  adding a matching registered Livewire component.
- Livewire components render through a `view()->exists('vendor.kraenzle-ritter.…')`
  fallback so applications can override any view.
- Search results are rendered through the shared partials
  `livewire/partials/{search-form,results-layout,save-button}.blade.php`.
- HTTP calls should go through `Traits\HttpClientTrait::safeHttpGet()`, which
  logs and returns a fallback instead of throwing.
- `Helpers\UserAgent::get()` supplies the User-Agent required by Wikimedia and
  lobid.

### Testing Strategy

- Testbench-based feature tests with an in-memory SQLite database
  (`tests/TestCase.php` creates `test_models` and `resources` tables).
- `tests/Api/*` currently perform **live network calls** against the real
  provider APIs. New provider work should prefer Guzzle `MockHandler` for the
  deterministic assertions and keep at most one opt-in smoke test for the live
  endpoint.
- Livewire components are tested with `Livewire::test(...)`.
- Run: `composer test` (copies views into testbench, then phpunit).

### Git Workflow

- Single `main` branch, conventional-commit-ish subjects
  (`fix:`, `feat:`, `chore:`).
- Consumers pin `dev-main`; assume no release gate between merge and production.

## Domain Context

"Normdateien" / authority files map an entity (person, corporate body, place,
subject) to a stable identifier. The package's job is to record
`(provider, provider_id, url)` per model and to enrich that set automatically:
a GND id is resolved to a Wikidata item via SPARQL (P227), and the Wikidata
item's external-id claims are turned into further resources using the
`wikidata_property` declared per provider.

## Important Constraints

- No breaking changes to the public API surface listed above.
- Provider keys in `config('resources.providers')` are persisted in the
  `resources.provider` database column — renaming a key orphans existing rows.
- The package registers **unauthenticated web routes** (`/resources-check…`) in
  every host application; anything rendered there is publicly reachable.
- External APIs are unreliable; failures must degrade to an empty result set,
  never to an exception reaching the host application.

## External Dependencies

| Service | Used for | Auth |
| --- | --- | --- |
| lobid.org/gnd | GND search | none |
| api.geonames.org | place search | `GEONAMES_USERNAME` (demo account is rate-limited) |
| \*.wikipedia.org/w/api.php | article search | none, User-Agent required |
| wikidata.org/w/api.php + query.wikidata.org/sparql | entity search, id resolution, provider URL patterns | none, User-Agent required |
| api.idiotikon.ch | Swiss German dictionary | none |
| search.ortsnamen.ch | Swiss place names | none |
| api.metagrid.ch | concordances | none |
| Anton instances (archives.georgfischer.com, gosteli.anton.ch, kba.karl-barth.ch) | archive search | `*_API_TOKEN` |
| www.idref.fr | French authority file (planned) | none |
