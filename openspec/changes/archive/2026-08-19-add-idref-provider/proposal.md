## Why

The package covers German (GND), Swiss (HLS, Idiotikon, ortsnamen.ch, Metagrid)
and international (VIAF, LoC, Wikidata) authority files, but the French national
authority file **IdRef** (ABES, `www.idref.fr`) is only present as a passive
label: `config('resources.providers.idref')` has a `label` and
`wikidata_property: P269`, but no `api-type`, no `base_url` and no `target_url`.
That means an IdRef identifier can neither be searched for nor saved from the
UI — and because `ResourceSyncService` only builds a URL for providers that
declare a `target_url`, a P269 claim found on a Wikidata item is silently
dropped today.

French-language and French-institution records are a real gap for both
consuming applications, and IdRef is the reference point for the francophone
scholarly world (it feeds VIAF, ISNI and the Sudoc union catalogue).

## What Changes

- Add a searchable `idref` provider using the IdRef Solr web service at
  `https://www.idref.fr/Sru/Solr`, which returns JSON without authentication.
- Add `KraenzleRitter\Resources\IdRef` as the API client, following the existing
  provider-client shape (`search(string $search, array $params = []): array`,
  failures degrade to an empty array).
- Add `IdRefLwComponent` (registered as `idref-lw-component`, derived from the
  new `api-type: IdRef`) plus `resources/views/livewire/idref-lw-component.blade.php`
  built on the shared `partials/results-layout` blade.
- Complete the `idref` provider config: `api-type`, `base_url`, `target_url`
  (`https://www.idref.fr/{provider_id}`), `test_search`, timeouts, and a
  record-type map. The provider **key stays `idref`**, so existing rows and the
  `sudoc => idref` rename rule keep working.
- Search across the IdRef authority indexes rather than the bibliographic ones:
  persons (`persname_t`), corporate bodies (`corpname_t`), families
  (`famname_t`), places (`geogname_t`) and subjects (`subjectheading_t`), with
  the set of indexes selectable per search so a place picker does not return
  people.
- Map the host application's endpoint (`actors`, `places`, `keywords`, …) to a
  default record-type set via config, passed through `ProviderSelect` the same
  way `endpoint` is already passed to Anton components.
- Store the IdRef PPN as `provider_id` and the full Solr document as
  `full_json`.
- Add French/German/English/Italian label entries and a `test_search` term so
  the provider appears in the diagnostics page and in
  `php artisan resources:test-resources`.

**Side effect worth calling out:** giving `idref` a `target_url` makes
`ResourceSyncService` start creating `idref` resources automatically whenever a
synced Wikidata item carries a P269 claim. This is the intended behaviour, but
it means models in Anton and KB will gain IdRef links on the next sync. Both
apps can suppress it through their existing `resources_filter` setting.

## Capabilities

### New Capabilities

- `idref-provider`: searching the French IdRef authority file, the shape of a
  result, and how an IdRef record becomes a stored resource.

### Modified Capabilities

- `provider-http`: the IdRef client is bound by the shared provider-client
  contract (configured base URL, User-Agent, failure containment). No
  requirement text changes — listed only because the new client must satisfy it.

## Impact

**New files**

- `src/IdRef.php`
- `src/Http/Livewire/IdRefLwComponent.php`
- `resources/views/livewire/idref-lw-component.blade.php`
- `tests/Api/IdRefTest.php`, `tests/Livewire/IdRefLwComponentTest.php`
- `tests/fixtures/idref/*.json`

**Modified files**

- `config/resources.php` — complete the `idref` entry
- `src/ResourcesServiceProvider.php` — register the component and bind the client
- `src/Http/Livewire/ProviderSelect.php` — pass `endpoint` for `api-type: IdRef`
- `resources/lang/{de,en,fr,it}/messages.php` — record-type labels
- `README.md` — list IdRef under supported providers

**Consumers**

- Additive. No existing signature, provider key or view changes. Anton and KB
  pick up IdRef by adding `'idref'` to the provider list they pass to
  `@livewire('provider-select', ...)`; until they do, nothing changes for them
  except the automatic P269 links described above.

**External dependency**

- `https://www.idref.fr/Sru/Solr` — public, no key, no documented rate limit.
  Same failure-containment rules as every other provider apply.

## Dependencies

Builds on `harden-resources-package`: the new client is written against the
consolidated `HttpClientTrait` contract, the injectable-client test setup, and
the escaping rules for result views. It can be implemented independently, but
should land after — otherwise the IdRef blade would have to be escaped twice.
