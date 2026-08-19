## ADDED Requirements

### Requirement: The Wikidata provider bootstrap is cached

`ResourceSyncService` SHALL NOT perform a SPARQL request against
`query.wikidata.org` on every instantiation. The provider/URL-pattern lookup
SHALL be cached for `config('resources.sync.cache_ttl')` seconds (default
86400).

#### Scenario: Second instantiation makes no request

- **WHEN** two `ResourceSyncService` instances are created within the TTL
- **THEN** at most one SPARQL request is sent

#### Scenario: Saving a resource does not block on Wikidata

- **WHEN** a Livewire component saves a resource and the bootstrap is cached
- **THEN** no request to `query.wikidata.org` is made for the bootstrap

#### Scenario: A failed bootstrap is not cached as success

- **WHEN** the SPARQL request fails
- **THEN** the empty result is not stored for the full TTL and the next
  instantiation retries

### Requirement: Sync failures never reach the caller

`syncFromProvider()` SHALL return an array in every case and SHALL NOT let an
exception from an upstream service propagate to the calling application.

#### Scenario: Wikidata is unreachable

- **WHEN** `syncFromProvider($model, 'gnd')` runs while `query.wikidata.org` is
  unreachable
- **THEN** it returns an empty array and logs the failure

#### Scenario: Metagrid returns an unexpected payload

- **WHEN** the stored Metagrid URL returns JSON without `concordances`
- **THEN** it returns an empty array and logs a warning

#### Scenario: A single bad record does not abort the batch

- **WHEN** one of several fetched resources fails to persist
- **THEN** the remaining resources are still created and the failure is logged

### Requirement: Synced resources honour the exclusion filter

`syncFromProvider()` SHALL skip any fetched resource whose provider key is
listed in the filter passed by the caller, preserving the existing
`$model->syncFromProvider($provider, $filter)` contract used by the consuming
applications.

#### Scenario: Filtered provider is skipped

- **WHEN** the filter contains `viaf` and Wikidata returns a VIAF claim
- **THEN** no `viaf` resource is created

#### Scenario: Unfiltered providers are still created

- **WHEN** the filter contains `viaf` and Wikidata also returns a GND claim
- **THEN** the `gnd` resource is created
