## Context

IdRef is the authority file of ABES (Agence bibliographique de l'enseignement
supérieur), the French academic library agency. It holds ~4.5M person records,
~306k corporate bodies, ~125k geographic names, ~149k subject headings and
~24k uniform titles, and is the French node feeding VIAF and ISNI.

The package already carries a stub for it — `config('resources.providers.idref')`
exists with `label: 'IdRef'` and `wikidata_property: 'P269'` — but no
`api-type`, `base_url` or `target_url`, so it is inert in both the UI and the
sync path. Adding the missing keys is what makes it real.

### API reconnaissance (verified 2026-08-19)

The endpoints documented under `documentation.abes.fr` were probed directly:

| Endpoint | Result |
| --- | --- |
| `https://www.idref.fr/Sru/Solr?q=…&wt=json` | **works** — plain Solr select, JSON, no auth |
| `https://www.idref.fr/Sru/Solr/sudoc/select?q=…` | 404 (the core path in older docs is gone) |
| `https://www.idref.fr/Sru?operation=searchRetrieve&…` | responds, but rejects every access code — unusable |
| `https://www.idref.fr/{ppn}.json` | works — single record as MARC-in-JSON |
| `https://www.idref.fr/{ppn}.rdf` | works — 100 kB RDF/XML per record |
| `https://www.idref.fr/services/idref2id/{ppn}` | works — XML list of external ids |

So: `https://www.idref.fr/Sru/Solr` is the base URL, with the ordinary Solr
query parameters `q`, `wt=json`, `rows`, `start`, `fl`, `sort`, `version=2.2`.

Relevant fields on an authority document:

| Field | Meaning |
| --- | --- |
| `ppn_z` | the IdRef identifier — 8 digits plus a check character that may be `X` |
| `recordtype_z` | `a` person, `b` corporate body, `c` geographic name, `d` trademark, `e` family, `f`/`h` uniform title, `j` subject heading, `s` event, `r` **bibliographic record** |
| `affcourt_z` | short display form, already includes life dates: `Beauvoir, Simone de (1908-1986)` |
| `affcourt_r` | all variant forms, including transliterations |
| `anneenaissance_dt` / `anneemort_dt`, `pays_s`, `langue_s` | person details |
| `idsext_s` | external identifiers already known to IdRef — ISNI, BnF ARK, VIAF URI, Wikipedia URL |

Index names follow the record types: `persname_t`, `corpname_t`, `famname_t`,
`geogname_t`, `subjectheading_t`. There is also a catch-all `all` index.

## Goals / Non-Goals

**Goals:**

- IdRef searchable and savable from the existing Livewire UI, with the same
  behaviour, failure containment and escaping rules as every other provider.
- Result lists that are usable for the actual entity being edited: a place
  picker must not return people.
- P269 claims found during Wikidata sync turn into real IdRef links.
- No new dependency, no auth, no API key.

**Non-Goals:**

- No use of `{ppn}.rdf` or `{ppn}.json` for enrichment. A single record is
  ~100 kB of RDF; the Solr document already carries everything the list view
  needs.
- No use of `idref2id` to auto-create sibling resources (VIAF, BnF, Wikipedia)
  from `idsext_s`. That would duplicate what `ResourceSyncService` already does
  through Wikidata, with a second, differently-shaped source of truth. Noted as
  a possible follow-up.
- No Sudoc bibliographic search. This provider is about authority records.
- No new abstraction for provider components. `IdRefLwComponent` is written in
  the same shape as `GndLwComponent`.

## Decisions

### D1 — Query the authority indexes, never `all`

The catch-all `all` index matches bibliographic records: `q=all:(beauvoir simone)`
returns `recordtype_z: r` documents ("Simone de Beauvoir, a biography") ahead of
the person record. The client therefore builds an explicit disjunction over the
authority indexes for the requested record types.

*Alternative considered:* `all` plus `fq=-recordtype_z:r`. Rejected — it still
ranks by whole-index term frequency and the field-specific query gives markedly
better ordering.

### D2 — Boosted phrase, then loose terms, in one query

The query sent is, for each selected index:

```
persname_t:"karl barth"^10 OR persname_t:(karl barth)
```

The phrase clause pulls exact heading matches to the top while the loose clause
keeps recall. Verified against `Karl Barth`: the theologian's record surfaces in
the top results, where the loose-only query buried it.

*Alternative considered:* the two-pass "exact first, fall back to loose"
approach `Wikidata` uses for comma-reversal. Rejected — it doubles the request
count for the common case, and Solr can express the preference in one query.

### D3 — Record types are configuration, not code

`config/resources.php` gains, under `providers.idref`:

```php
'record_types' => [
    'person'    => ['code' => 'a', 'index' => 'persname_t'],
    'corporate' => ['code' => 'b', 'index' => 'corpname_t'],
    'place'     => ['code' => 'c', 'index' => 'geogname_t'],
    'family'    => ['code' => 'e', 'index' => 'famname_t'],
    'subject'   => ['code' => 'j', 'index' => 'subjectheading_t'],
],
'default_record_types'  => ['person', 'corporate'],
'endpoint_record_types' => [
    'actors'   => ['person', 'corporate', 'family'],
    'places'   => ['place'],
    'keywords' => ['subject'],
],
```

*Why:* the two consuming applications have different endpoint vocabularies
(Anton: `actors`/`places`/`keywords`; KB adds `songs`/`bibls`). Encoding the
mapping in config lets each app tune it by publishing the config, without a
package release. An unmapped endpoint falls back to `default_record_types`.

### D4 — `ProviderSelect` gains an `IdRef` branch, mirroring `Anton`

`ProviderSelect::updateActiveProvider()` already special-cases `Wikipedia`
(passes `providerKey`) and `Anton` (passes `endpoint`). `IdRef` gets the same
treatment as `Anton`: `endpoint` is added to `componentParams`.

*Why:* it is the established pattern in this class, it is purely additive, and
the alternative (making every component take `endpoint`) would change the mount
signature of eight components that two production apps render.

*Trade-off:* the `if/else if/else` chain grows a fourth arm. Acceptable; a
refactor to per-api-type parameter builders is a separate concern and would
touch the components Anton and KB depend on.

### D5 — Normalise the search term before sending

Two transformations, in order:

1. Transliterate to ASCII (`Genève` → `Geneve`) — the ABES documentation states
   the index is built without accents, and `geogname_t:genève` misses records
   that `geogname_t:geneve` finds.
2. Strip Solr metacharacters `+ - && || ! ( ) { } [ ] ^ " ~ * ? : \ /` and
   collapse whitespace — a user pasting `Barth, Karl (théologien)` from another
   field would otherwise send an unbalanced-parenthesis query and get a Solr
   parse error.

Implemented as `IdRef::normalise(string $term): string`. Transliteration uses
`Illuminate\Support\Str::ascii()`, already available through the framework
dependency.

### D6 — `provider_id` is the PPN, `url` comes from `target_url`

`target_url` is `https://www.idref.fr/{provider_id}`, resolved through the same
`str_replace('{provider_id}', …)` mechanism every other provider uses, so the
Wikidata sync path builds the identical URL for a P269 claim as the UI does for
a saved search result. PPNs are stored verbatim, including a trailing `X` check
character; nothing casts them to int.

*Note:* `Resource::setProviderIdAttribute()` runs `urldecode()` on the value.
A PPN contains no percent-encoding, so this is a no-op — verified, no special
handling needed.

### D7 — Display: `affcourt_z` plus a record-type label

The heading is `affcourt_z`, which already carries the disambiguating
parenthetical (`Barth, Karl (1886-1968 ; théologien)`) that makes an IdRef list
usable. The content line is the target URL plus the localised record-type label,
and — where present and different from the heading — up to three variant forms
from `affcourt_r`.

Record-type labels go into `resources/lang/{de,en,fr,it}/messages.php` under
`idref.record_type.*`. An unmapped code renders no label rather than the raw
letter.

Every interpolated value is passed through `e()` and the URL through
`UrlHelper::safe()`, per the escaping rules established in
`harden-resources-package`.

### D8 — `full_json` stores the Solr document

The whole `docs[n]` object is stored, matching what `GndLwComponent` does with
the lobid member. It is small (~2 kB), it includes `idsext_s`, and it keeps the
door open for the follow-up that turns those external ids into sibling
resources without a re-fetch.

## Risks / Trade-offs

- **Giving `idref` a `target_url` changes sync behaviour for live data.**
  Models in Anton and KB will start gaining `idref` resources on the next
  `syncFromProvider()` run wherever the Wikidata item has P269. → This is the
  intended outcome; it is called out in the proposal, and both apps can suppress
  it through their existing `resources_filter` setting. Verify the volume on a
  staging copy before merging.
- **ABES has no published rate limit and no SLA.** → Same failure containment as
  every other provider: log and return `[]`. Timeouts default to 15s/5s like
  GND. CI does not depend on the live service (fixtures + `live-api` group).
- **The Solr endpoint moved once already** (the documented
  `/Sru/Solr/sudoc/select` path is now a 404). → `base_url` lives in config, so
  a move is a config change; the `live-api` test group is what will notice.
- **Ranking is a judgement call.** The `^10` phrase boost was tuned against four
  hand-checked queries, not a benchmark. → The boost factor goes into config
  (`phrase_boost`, default 10) so it can be adjusted without a release.
- **`recordtype_z` codes were derived empirically** (one probe per letter),
  not from a published table. `d` (trademark) and `s` (event) in particular are
  inferred from single examples. → Only the five mapped types are used for
  querying; unmapped codes that arrive in a result render without a label rather
  than wrongly labelled.

## Migration Plan

1. Land `harden-resources-package` first (or at least its task groups 1 and 5),
   so the IdRef client is written against the injectable-client test setup and
   the blade is escaped once, correctly.
2. Add the client + fixtures + tests, with no config change — nothing is
   user-visible yet.
3. Add the component, view and translations.
4. Complete the config entry. **This is the step that switches on the sync
   side effect**; run `syncFromProvider` against a staging copy of one app
   first and count the new `idref` rows.
5. Add `idref` to the provider lists in Anton and KB (their change, not this
   package's) once the package side is merged.

**Rollback:** remove `api-type`/`target_url` from the `idref` config entry. The
provider falls back to being a passive label, the component stops being
selectable, and the sync stops creating IdRef rows. No schema change to undo.

## Open Questions

- Should `idref` be added to the default provider list the apps pass in, or left
  opt-in per endpoint? Package-side this makes no difference; it is the apps'
  call.
- Are `d` (trademark), `f`/`h` (uniform title) and `s` (event) worth exposing as
  searchable types for KB's `songs`/`bibls` endpoints? Deferred — add them to
  `record_types` when someone asks; the config shape already supports it.
- Follow-up candidate: use `idsext_s` from the stored `full_json` to offer
  sibling links (VIAF, BnF, Wikipedia) without a second request. Out of scope
  here.
