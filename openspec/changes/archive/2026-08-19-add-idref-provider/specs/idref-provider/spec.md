## ADDED Requirements

### Requirement: IdRef is a searchable provider

The package SHALL expose `idref` as a searchable provider with
`api-type: IdRef`, backed by the IdRef Solr web service, so a user can find a
French authority record and save it as a resource from the Livewire UI.

#### Scenario: Provider is selectable

- **WHEN** an application passes `idref` in the provider list of
  `@livewire('provider-select', …)`
- **THEN** `ProviderSelect` renders the `idref-lw-component`

#### Scenario: Search returns results

- **WHEN** `(new IdRef())->search('Simone de Beauvoir')` is called
- **THEN** an array of result objects is returned, each carrying at least a PPN,
  a display heading and a record type

#### Scenario: Provider key is stable

- **WHEN** an IdRef resource is saved
- **THEN** its `provider` column is `idref`, and the existing
  `config('resources.rename')` rule mapping `sudoc` to `idref` still applies

### Requirement: Only authority records are returned

Search results SHALL be restricted to IdRef authority records and SHALL NOT
include bibliographic records. A query SHALL be sent against the authority
indexes (`persname_t`, `corpname_t`, `famname_t`, `geogname_t`,
`subjectheading_t`) rather than the catch-all `all` index, which also matches
Sudoc bibliographic records.

#### Scenario: Bibliographic records are excluded

- **WHEN** a search for `Simone de Beauvoir` runs
- **THEN** no result has `recordtype_z` of `r`, and every result is an authority
  record

#### Scenario: Person search returns persons

- **WHEN** a person search for `Karl Barth` runs
- **THEN** results have `recordtype_z` of `a` and headings of the form
  `Barth, Karl (1886-1968 ; théologien)`

### Requirement: The searched record types are selectable

The set of IdRef indexes queried SHALL be selectable per search through the
`record_types` query option, defaulting to the set configured for the provider.

#### Scenario: Place search does not return people

- **WHEN** a search runs with `record_types` limited to geographic names
- **THEN** only geographic authority records are returned

#### Scenario: Endpoint drives the default record types

- **WHEN** `ProviderSelect` mounts the IdRef component with endpoint `places`
  and `config('resources.providers.idref.endpoint_record_types.places')` names
  the geographic types
- **THEN** the component searches those types by default

#### Scenario: Unknown endpoint falls back to the provider default

- **WHEN** the component is mounted with an endpoint that has no mapping
- **THEN** the provider's default record-type set is used and the search still
  succeeds

### Requirement: Search terms are normalised for the IdRef index

Search terms SHALL be transliterated to their unaccented form and stripped of
Solr syntax characters before being sent, because the IdRef index stores
unaccented values and would otherwise miss or error on the query.

#### Scenario: Accented term matches

- **WHEN** a user searches for `Genève`
- **THEN** the query sent contains `geneve` and the Geneva authority record is
  among the results

#### Scenario: Solr metacharacters do not break the query

- **WHEN** a user types `Barth, Karl (théologien)` into the search box
- **THEN** the request succeeds and returns results rather than a Solr parse
  error

#### Scenario: Empty search performs no request

- **WHEN** the search term is empty or whitespace only
- **THEN** no HTTP request is made and an empty array is returned

### Requirement: An IdRef result is saved as a resource

Saving a result SHALL create a resource with `provider` `idref`, `provider_id`
set to the record's PPN, `url` built from the configured `target_url`, and
`full_json` set to the complete Solr document.

#### Scenario: Saved resource fields

- **WHEN** the result with PPN `026707357` is saved
- **THEN** a resource is created with `provider_id` `026707357` and `url`
  `https://www.idref.fr/026707357`

#### Scenario: PPN with a check character

- **WHEN** the result with PPN `02726453X` is saved
- **THEN** the PPN is stored verbatim, including the trailing `X`

#### Scenario: Saving is idempotent per provider

- **WHEN** a second IdRef record is saved for a model that already has one
- **THEN** the existing `idref` row is updated rather than duplicated,
  consistent with `updateOrCreateResource()`

### Requirement: IdRef failures degrade to an empty result

The IdRef client SHALL contain every HTTP, timeout and decoding failure, log it,
and return an empty array, so an ABES outage cannot break a host application's
edit page.

#### Scenario: Service returns 500

- **WHEN** the IdRef service responds with HTTP 500
- **THEN** `search()` returns `[]` and the failure is logged

#### Scenario: Service returns malformed JSON

- **WHEN** the response body is not valid JSON
- **THEN** `search()` returns `[]` without throwing

#### Scenario: Response lacks the expected structure

- **WHEN** the response is valid JSON without a `response.docs` key
- **THEN** `search()` returns `[]` without raising an undefined-index error

#### Scenario: Component renders through an outage

- **WHEN** the IdRef service is unreachable and the component renders
- **THEN** the "no matches" message is shown and no exception escapes

### Requirement: Results carry a localised record-type label

Each result SHALL expose a human-readable record-type label derived from the
IdRef `recordtype_z` code, localised through the package's translation files,
so a list mixing persons, corporate bodies and places stays readable.

#### Scenario: Person record type

- **WHEN** a result has `recordtype_z` `a` and the application locale is German
- **THEN** the rendered result labels it as a person in German

#### Scenario: Unmapped record type

- **WHEN** a result carries a `recordtype_z` code with no mapping
- **THEN** the result still renders, without a type label and without an error

### Requirement: Wikidata sync creates IdRef links

`ResourceSyncService` SHALL create an `idref` resource when a synced Wikidata
item carries a P269 claim, because the `idref` provider now declares both
`wikidata_property: P269` and a `target_url`.

#### Scenario: P269 claim becomes a resource

- **WHEN** `syncFromProvider($model, 'gnd')` resolves a Wikidata item carrying
  P269 `026707357`
- **THEN** an `idref` resource with url `https://www.idref.fr/026707357` is
  created

#### Scenario: Filter still applies

- **WHEN** the caller's filter list contains `idref`
- **THEN** no `idref` resource is created by the sync
