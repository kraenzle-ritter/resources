## ADDED Requirements

### Requirement: Provider clients use the configured base URL

Every provider API client SHALL build its HTTP client from
`config('resources.providers.<key>.base_url')`, falling back to a documented
default only when the key is absent.

#### Scenario: Configured base URL is used

- **WHEN** `resources.providers.gnd.base_url` is set to a test double URL and a
  search is performed
- **THEN** the request goes to that URL

#### Scenario: Config and code no longer disagree

- **WHEN** the `idiotikon` and `metagrid` clients are constructed
- **THEN** their effective base URL equals the value in `config/resources.php`

### Requirement: Provider failures degrade to an empty result

A provider client SHALL NOT let an HTTP, DNS, TLS, timeout or JSON-decoding
failure escape to the caller. It SHALL log the failure and return its documented
empty value.

#### Scenario: Upstream returns 500

- **WHEN** a provider API responds with HTTP 500
- **THEN** `search()` returns an empty result and the failure is logged

#### Scenario: Upstream returns malformed JSON

- **WHEN** a provider API responds 200 with a body that is not valid JSON
- **THEN** `search()` returns an empty result instead of throwing

#### Scenario: Upstream returns valid JSON with an unexpected shape

- **WHEN** a provider API responds with JSON lacking the expected `results`,
  `member`, `data` or `concordances` key
- **THEN** `search()` returns an empty result instead of raising a property
  access error

#### Scenario: Connection times out

- **WHEN** the connection to a provider API times out
- **THEN** `search()` returns an empty result within the configured timeout

### Requirement: Every provider request carries the package User-Agent

Provider requests SHALL send the User-Agent from
`config('resources.user_agent')`, which SHALL resolve correctly when the host
application has run `php artisan config:cache`.

#### Scenario: User-Agent survives config caching

- **WHEN** the configuration is cached and a provider client is constructed
- **THEN** the `User-Agent` header is the configured value, not `null` and not
  the string `resources/` with an empty version

### Requirement: API credentials are not sent in the query string

A provider client that authenticates with a token SHALL send it in an
`Authorization: Bearer` header by default, so the token does not appear in
access logs, proxy logs or `Referer` headers. The transport SHALL be
configurable per provider via `api_token_transport` with the values `header`
and `query`.

#### Scenario: Token is sent as a header by default

- **WHEN** an Anton provider search runs with `api_token` configured
- **THEN** the outgoing request has an `Authorization: Bearer <token>` header
  and no `api_token` query parameter

#### Scenario: Query transport can be restored

- **WHEN** `api_token_transport` is set to `query` for that provider
- **THEN** the outgoing request carries `api_token` as a query parameter and no
  `Authorization` header

#### Scenario: No token configured

- **WHEN** no `api_token` is configured for the provider
- **THEN** the request is sent without an `Authorization` header and without an
  `api_token` parameter

### Requirement: Provider clients are free of PHP deprecations

Provider clients and helpers SHALL declare every property they assign, SHALL NOT
pass `null` to non-nullable internal parameters, and SHALL use explicit
nullable type declarations.

#### Scenario: No deprecation notices during the test run

- **WHEN** the full test suite runs with `E_ALL` error reporting
- **THEN** no `Deprecated` notice originates from `src/`
