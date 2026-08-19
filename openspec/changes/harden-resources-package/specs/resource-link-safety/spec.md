## ADDED Requirements

### Requirement: Stored resource URLs are restricted to http and https

The package SHALL reject a resource whose `url` is present but does not use the
`http` or `https` scheme, both on manual input and on programmatic writes
through `updateOrCreateResource()`. A resource written without a `url` — the
identifier-only pattern KB uses for `geonames_id` / `wikidata_id` — SHALL be
accepted unchanged.

#### Scenario: javascript URL is rejected on manual input

- **WHEN** a user submits `javascript:alert(1)` in the manual-input component
- **THEN** a validation error is shown, no resource is created, and no
  `ResourceSaved` event is dispatched

#### Scenario: data URL is rejected

- **WHEN** `updateOrCreateResource(['provider' => 'x', 'provider_id' => '1', 'url' => 'data:text/html,<script>'])`
  is called
- **THEN** the write is rejected with a validation exception

#### Scenario: Ordinary provider URLs are accepted

- **WHEN** a resource with `https://d-nb.info/gnd/118500775` is saved
- **THEN** it is persisted unchanged

#### Scenario: Identifier-only rows keep working

- **WHEN** `$place->resources()->updateOrCreate(['provider' => 'geonames'], ['provider_id' => '2657896'])`
  is called with no `url` key, as KB's `Place::setGeonamesIdAttribute()` does
- **THEN** the row is created and no validation exception is raised

#### Scenario: Existing rows are not altered

- **WHEN** the model reads a resource that was stored before this rule existed,
  including one with an empty `url`
- **THEN** reading and listing it does not throw, and no `href` is emitted for
  the empty url

### Requirement: Manual input is validated before saving

`ManualInputLwComponent` SHALL validate its input before persisting. `provider`
SHALL be required, `url` SHALL be required and a valid `http`/`https` URL, and
`provider_id` SHALL be optional.

#### Scenario: Empty form does not create a resource

- **WHEN** the save button is pressed with all fields empty
- **THEN** validation errors are shown for `provider` and `url` and no row is
  created

#### Scenario: Valid manual input is saved

- **WHEN** provider `dodis`, provider_id `12345` and url
  `https://dodis.ch/12345` are submitted
- **THEN** the resource is created and `ResourceSaved` is dispatched

### Requirement: Provider-supplied text is escaped in result views

Result views SHALL escape all text and URL fragments taken from a provider API
response before emitting them, so a hostile or compromised upstream record
cannot inject markup or script into the host application.

#### Scenario: Markup in a provider label is not executed

- **WHEN** a provider search returns a heading containing
  `<img src=x onerror=alert(1)>`
- **THEN** the rendered page contains the escaped text and no `onerror`
  attribute

#### Scenario: Markup in a provider description is not executed

- **WHEN** a provider search returns a description containing a `<script>` tag
- **THEN** the rendered page contains no executable `<script>` element from that
  description

#### Scenario: A javascript URL from a provider is not rendered as a link

- **WHEN** a provider result carries a `url` field with a `javascript:` scheme
- **THEN** the result is rendered without an `href` pointing at that scheme

### Requirement: External links do not leak the opener

Every link rendered by the package with `target="_blank"` SHALL also carry
`rel="noopener noreferrer"`.

#### Scenario: Saved-resource list links

- **WHEN** `resources-list` renders a saved resource
- **THEN** the anchor has `target="_blank"` and `rel="noopener noreferrer"`

#### Scenario: Search-result links

- **WHEN** a provider component renders search results
- **THEN** every result anchor has `rel="noopener noreferrer"`
