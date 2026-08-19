## ADDED Requirements

### Requirement: Resource removal is scoped to the owning model

Removal SHALL be scoped to the owning model: every code path that deletes a
resource on behalf of a Livewire component or a `HasResources` model SHALL
delete only rows attached to that model through the `resourceable` morph
relation. A removal request that names a resource belonging to another model or
another model type SHALL leave the database unchanged.

#### Scenario: Removing by id only affects the own model

- **WHEN** `ResourcesList` is mounted on model A and `removeResource($id)` is
  called with the id of a resource attached to model B
- **THEN** no row is deleted and model B still has that resource

#### Scenario: Removing by url only affects the own model

- **WHEN** a provider component is mounted on model A and `removeResource($url)`
  is called with a url that is stored for both model A and model B
- **THEN** only model A's row is deleted and model B keeps its row

#### Scenario: Removing an own resource still works

- **WHEN** a component mounted on model A removes a resource that belongs to
  model A
- **THEN** the row is deleted and a `resourcesChanged` event is dispatched

#### Scenario: Removal reports whether anything was deleted

- **WHEN** `$model->removeResource($id)` is called for an id that does not
  belong to `$model`
- **THEN** it returns `false` without throwing

### Requirement: Resource writes are attached to the mounted model

A Livewire provider component SHALL persist resources only against the model it
was mounted with, and SHALL NOT accept a model or model identifier supplied by
the client at call time.

#### Scenario: Save targets the mounted model

- **WHEN** `saveResource()` is invoked on a component mounted with model A
- **THEN** the created resource has `resourceable_id` and `resourceable_type` of
  model A

### Requirement: The removal contract stays backwards compatible

`HasResources::removeResource()` and `Resource::removeResource()` SHALL keep
their current names and return a boolean, so existing consumer code
(`$model->removeResource($id)`) keeps working.

#### Scenario: Consumer call signature unchanged

- **WHEN** an application calls `$model->removeResource($id)` with an integer or
  numeric-string id of one of its own resources
- **THEN** the resource is deleted and `true` is returned
