## ADDED Requirements

### Requirement: Diagnostics routes are opt-in

The `/resources-check*` routes SHALL only be registered when
`config('resources.diagnostics.enabled')` is true. The default SHALL be false,
so installing the package does not add publicly reachable routes to a host
application.

#### Scenario: Disabled by default

- **WHEN** the package is installed with no diagnostics configuration and
  `/resources-check` is requested
- **THEN** the application responds 404 and no `resources.check.*` route exists

#### Scenario: Enabled explicitly

- **WHEN** `resources.diagnostics.enabled` is true and `/resources-check` is
  requested
- **THEN** the diagnostics index renders with HTTP 200

### Requirement: Diagnostics middleware is configurable

When the diagnostics routes are registered they SHALL use the middleware stack
from `config('resources.diagnostics.middleware')`, defaulting to `['web']`, so
an application can require authentication or an admin gate.

#### Scenario: Application requires authentication

- **WHEN** `resources.diagnostics.middleware` is `['web', 'auth']` and a guest
  requests `/resources-check`
- **THEN** the request is handled by the `auth` middleware and not by the
  diagnostics controller

### Requirement: Secrets are redacted in diagnostics output

Diagnostics views SHALL NOT render the value of any provider configuration key
whose name contains `token`, `secret`, `password`, `key`, or is `user_name`.
Such values SHALL be replaced by a fixed placeholder.

#### Scenario: Anton api_token is redacted on the provider page

- **WHEN** `/resources-check/provider/kba` is rendered while
  `resources.providers.kba.api_token` is set
- **THEN** the response does not contain the token value and shows a
  placeholder instead

#### Scenario: Geonames user_name is redacted

- **WHEN** the configuration page renders the `geonames` provider
- **THEN** the configured `user_name` value is not present in the response

#### Scenario: Non-secret keys stay visible

- **WHEN** the provider page renders the `gnd` provider
- **THEN** `base_url` and `target_url` are shown with their real values

### Requirement: A headless provider health check exists

`resources:test-resources` SHALL actually exercise each configured provider —
resolving the client class from the provider's `api-type`, constructing it and
calling `search()` with the provider's `test_search` term — and SHALL exit
non-zero when at least one provider fails, so it is usable from cron and CI
without the opt-in diagnostics routes.

#### Scenario: Every API provider is reached

- **WHEN** the command runs over the default configuration
- **THEN** every provider declaring an `api-type` is reported with a status, and
  none is reported as "class not found"

#### Scenario: Providers sharing a client class are resolved

- **WHEN** the command reaches `georgfischer`, `gosteli` and `kba`
- **THEN** each is instantiated as the `Anton` client with its own provider key,
  not as a class named after the provider key

#### Scenario: Language-specific Wikipedia providers are resolved

- **WHEN** the command reaches `wikipedia-de`
- **THEN** the `Wikipedia` client is called with that provider key

#### Scenario: Manual input is skipped

- **WHEN** the command reaches the `manual-input` provider
- **THEN** it is reported as skipped and does not count as a failure

#### Scenario: A failing provider fails the command

- **WHEN** one provider's client returns no results
- **THEN** that provider is reported as failed and the command exits non-zero

#### Scenario: All providers healthy

- **WHEN** every provider returns results
- **THEN** the command exits zero

#### Scenario: A single provider can be tested

- **WHEN** the command runs with `--provider=gnd`
- **THEN** only `gnd` is exercised

### Requirement: The provider diagnostics page renders

`/resources-check/provider/{provider}` SHALL render successfully for every
configured provider that has an `api-type`, providing the search result, the
selected endpoint, the available endpoints and the show-all flag to the view.

#### Scenario: Provider page renders for an API provider

- **WHEN** `/resources-check/provider/gnd` is requested with diagnostics enabled
- **THEN** the response is HTTP 200 and reports the search status

#### Scenario: Provider page survives an API failure

- **WHEN** the provider's upstream API is unreachable
- **THEN** the page still renders HTTP 200 and reports an error status instead
  of throwing

#### Scenario: Unknown provider redirects

- **WHEN** `/resources-check/provider/does-not-exist` is requested
- **THEN** the response redirects to the diagnostics index with an error message
