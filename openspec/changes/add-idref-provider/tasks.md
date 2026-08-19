## 1. Fixtures and test scaffolding

- [x] 1.1 Capture live fixtures into `tests/fixtures/idref/`: `person-search.json` (`persname_t` query for Karl Barth), `corporate-search.json` (Croix-Rouge), `place-search.json` (Genève), `empty.json` (`numFound: 0`), `malformed.txt` (non-JSON body), `unexpected-shape.json` (valid JSON without `response.docs`).
- [x] 1.2 Add `tests/Api/IdRefTest.php` with the mock-client helper from `harden-resources-package` task 1.2, and a `live-api`-grouped smoke test that hits the real service.

## 2. API client (spec: idref-provider)

- [x] 2.1 Write failing tests for `IdRef::normalise()`: `Genève` → `geneve`; `Barth, Karl (théologien)` loses the Solr metacharacters; whitespace collapses; an empty/whitespace term yields an empty string.
- [x] 2.2 Write a failing test that an empty search term makes no HTTP request and returns `[]`.
- [x] 2.3 Write failing tests for query construction: the request `q` contains a boosted phrase clause and a loose clause for each selected index; only the indexes for the requested record types appear; `rows` follows the `limit` option; `wt=json` and `version=2.2` are present.
- [x] 2.4 Write failing tests for result mapping: each result exposes `ppn`, `heading` (`affcourt_z`), `recordType` (`recordtype_z`), `url` and the raw document; variant forms from `affcourt_r` are exposed; a PPN with a trailing `X` survives unchanged.
- [x] 2.5 Write failing tests that no result has `recordtype_z` of `r`.
- [x] 2.6 Write failing tests for the failure paths: HTTP 500, malformed JSON, and JSON without `response.docs` each return `[]` and log.
- [x] 2.7 Implement `src/IdRef.php`: `HttpClientTrait`, injectable `?ClientInterface $client = null`, `base_url` and timeouts from config, `UserAgent::get()`, `search(string $search, array $params = []): array` honouring `limit` and `record_types`.
- [x] 2.8 Verify: all tests in 2.1–2.6 pass with zero live network calls.

## 3. Configuration

- [x] 3.1 Write a failing test that `config('resources.providers.idref')` carries `api-type`, `base_url`, `target_url`, `test_search`, `record_types`, `default_record_types` and `endpoint_record_types`, and still carries `wikidata_property: P269`.
- [x] 3.2 Complete the `idref` entry in `config/resources.php` per design D3, keeping the existing `label` and `wikidata_property`, and adding `timeout: 15`, `connect_timeout: 5` and `phrase_boost: 10`.
- [x] 3.3 Write a failing test that `config('resources.rename')['sudoc']` still resolves to `idref` and that saving with provider `sudoc` produces an `idref` row.
- [x] 3.4 Verify: `tests/ConfigTest.php` and `tests/Api/ProviderConfigurationTest.php` still pass with the new provider counted as an API provider.

## 4. Livewire component (spec: idref-provider)

- [x] 4.1 Write a failing test that `Livewire::test(IdRefLwComponent::class, ['model' => $model])` renders, searches, and lists mocked results.
- [x] 4.2 Write a failing test that saving a result creates a resource with `provider` `idref`, `provider_id` the PPN, `url` `https://www.idref.fr/{ppn}` and `full_json` the Solr document.
- [x] 4.3 Write a failing test that a second save updates the existing `idref` row rather than duplicating it.
- [x] 4.4 Write a failing test that removal is scoped to the mounted model (per `resource-ownership`).
- [x] 4.5 Write a failing test that the component renders the "no matches" message and throws nothing when the service is unreachable.
- [x] 4.6 Implement `src/Http/Livewire/IdRefLwComponent.php` in the shape of `GndLwComponent`, accepting an optional `endpoint` in `mount()` and resolving its default record types from `endpoint_record_types`.
- [x] 4.7 Register `idref-lw-component` and bind `IdRef::class` in `ResourcesServiceProvider`.
- [x] 4.8 Verify: tests 4.1–4.5 pass.

## 5. View and localisation

- [x] 5.1 Write a failing test feeding a hostile fixture (`<img src=x onerror=alert(1)>` in `affcourt_z`, a `javascript:` url) through the component and asserting the payload does not survive rendering.
- [x] 5.2 Implement `resources/views/livewire/idref-lw-component.blade.php` on `partials/results-layout`, with heading `affcourt_z`, content = target url + record-type label + up to three variant forms, everything through `e()` and `UrlHelper::safe()`, and `rel="noopener noreferrer"` on the anchor.
- [x] 5.3 Add `idref.record_type.{person,corporate,place,family,subject}` keys to `resources/lang/{de,en,fr,it}/messages.php`.
- [x] 5.4 Write a failing test that an unmapped `recordtype_z` renders without a label and without an error.
- [x] 5.5 Verify: tests 5.1 and 5.4 pass; visually check a person, a corporate body and a place result.

## 6. Endpoint routing through ProviderSelect

- [x] 6.1 Write a failing test that `ProviderSelect` mounted with `idref` and endpoint `places` passes `endpoint` in `componentParams`.
- [x] 6.2 Write a failing test that an endpoint with no mapping falls back to `default_record_types` and still searches successfully.
- [x] 6.3 Add the `IdRef` branch to `ProviderSelect::updateActiveProvider()`, mirroring the existing `Anton` branch.
- [x] 6.4 Write a regression test that the `Wikipedia`, `Anton` and default branches still produce exactly the `componentParams` they produce today.
- [x] 6.5 Verify: the existing `tests/Livewire/ProviderSelectTest.php` still passes unchanged.

## 7. Sync integration (spec: idref-provider)

- [x] 7.1 Write a failing test that a mocked Wikidata entity carrying a P269 claim produces an `idref` resource with url `https://www.idref.fr/026707357`.
- [x] 7.2 Write a failing test that a caller filter containing `idref` suppresses that resource.
- [x] 7.3 Verify no code change is needed in `ResourceSyncService` — the `target_url` addition should be sufficient. If it is not, fix the generic path, not an IdRef special case.
- [~] 7.4 **BLOCKED — needs a staging database.** Same reason as the harden change's 9.1: the consumer databases live in their containers and are not reachable from here. The query to run there:
  `SELECT COUNT(*) FROM resources WHERE provider = 'wikidata';` for the upper bound, then a sync dry-run.
  Original: Measure the blast radius: run `syncFromProvider` over a staging copy of one consumer's actor table and count the new `idref` rows. Record the number in the change before merging.

## 8. Documentation and diagnostics

- [x] 8.1 Add IdRef to the supported-providers list in `README.md`, with a note on `record_types` / `endpoint_record_types`.
- [x] 8.2 Verify `php artisan resources:test-resources --provider=idref` reports the provider as working.
- [x] 8.3 Verify the diagnostics provider page renders for `idref` (requires `harden-resources-package` task 6.5).
- [x] 8.4 Run the full suite plus `--group live-api` once, confirming the live IdRef endpoint still answers as the fixtures assume.

## 9. Consumer rollout

- [~] 9.1 **BLOCKED — same as harden 9.1/9.2**: neither consumer is linked to this working tree and both suites need MySQL on host `db`.
- [x] 9.2 Noted in `UPGRADING.md` and the README. Not applied to the consumer repositories. Original: Note for the consumers: add `'idref'` to the provider lists passed to `@livewire('provider-select', …)` for the endpoints where it makes sense. Do not edit the consumer repositories as part of this change.
- [x] 9.3 Covered in the README (IdRef record types section) — the automatic P269 links and how to suppress them with the filter list. To be folded into `UPGRADING.md` when this change ships.
