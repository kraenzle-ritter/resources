<?php

namespace KraenzleRitter\Resources;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\Str;
use KraenzleRitter\Resources\Helpers\UserAgent;
use KraenzleRitter\Resources\Traits\HttpClientTrait;

/**
 * IdRef — the French authority file, maintained by ABES.
 *
 * Queried through its Solr service:
 *   https://www.idref.fr/Sru/Solr?q=...&wt=json&rows=...
 *
 * The core paths in the older ABES documentation (/Sru/Solr/sudoc/select) now
 * answer 404, and the SRU endpoint rejects every access code, so this is the
 * only usable search interface.
 *
 * (new IdRef())->search('Karl Barth');
 */
class IdRef
{
    use HttpClientTrait;

    public const DEFAULT_BASE_URL = 'https://www.idref.fr/Sru/Solr';

    /**
     * Fields fetched per document. Kept explicit: `fl=*` returns the whole
     * MARC-ish record including every transliteration, which is far more than
     * the result list needs.
     */
    public const FIELDS = 'id,ppn_z,recordtype_z,affcourt_z,affcourt_r,anneenaissance_dt,anneemort_dt,pays_s,idsext_s';

    /**
     * `r` marks a Sudoc bibliographic record rather than an authority record.
     * The authority indexes should not return them, but a defensive filter
     * costs nothing and the catch-all index is full of them.
     */
    public const BIBLIOGRAPHIC_RECORD_TYPE = 'r';

    public $client;

    public function __construct(?ClientInterface $client = null)
    {
        if ($client) {
            $this->client = $client;

            return;
        }

        $this->client = new Client([
            'base_uri' => config('resources.providers.idref.base_url', self::DEFAULT_BASE_URL),
            'timeout' => config('resources.providers.idref.timeout', 15),
            'connect_timeout' => config('resources.providers.idref.connect_timeout', 5),
            'headers' => UserAgent::get(),
            'http_errors' => false,
        ]);
    }

    /**
     * Search the IdRef authority indexes.
     *
     * @param array $params limit, record_types (keys of the configured
     *                      record_types map), endpoint
     * @return array Result objects: ppn, heading, recordType, url, variants, raw
     */
    public function search(string $search, $params = []): array
    {
        $term = self::normalise($search);

        if ($term === '') {
            return [];
        }

        $limit = $params['limit']
            ?? config('resources.providers.idref.limit')
            ?? config('resources.limit')
            ?? 5;

        $query = http_build_query([
            'q' => $this->buildQuery($term, $this->resolveRecordTypes($params)),
            'wt' => 'json',
            'version' => '2.2',
            'sort' => 'score desc',
            'start' => 0,
            'rows' => $limit,
            'fl' => self::FIELDS,
        ]);

        return $this->safeHttpGet('?' . $query, 'IdRef API', [], function ($content, $endpoint, $apiName, $fallbackValue) {
            $body = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($body)) {
                return $fallbackValue;
            }

            $docs = $body['response']['docs'] ?? null;

            if (! is_array($docs)) {
                return $fallbackValue;
            }

            return $this->mapResults($docs);
        });
    }

    /**
     * Normalise a user-typed term for the Solr index.
     *
     * Two things happen: the index is built without accents, so `Genève` would
     * miss records that `geneve` finds; and Solr metacharacters pasted from
     * another field (`Barth, Karl (théologien)`) would otherwise produce an
     * unbalanced-parenthesis parse error.
     */
    public static function normalise(string $term): string
    {
        $term = Str::ascii($term);
        $term = strtolower($term);

        // Boost (^2) and fuzzy/proximity (~0.8) operators carry a number.
        // Dropping only the operator would leave the number behind as a stray
        // search term, so the whole expression goes.
        $term = preg_replace('/[\^~]\d*(\.\d+)?/', ' ', $term);

        // Solr syntax characters, plus punctuation that only adds noise here.
        $term = str_replace(
            ['+', '-', '&&', '||', '!', '(', ')', '{', '}', '[', ']', '^', '"', '~', '*', '?', ':', '\\', '/', ',', ';'],
            ' ',
            $term
        );

        return trim(preg_replace('/\s+/', ' ', $term));
    }

    /**
     * Which record types to search, in order of precedence:
     * explicit record_types -> the endpoint's mapping -> the provider default.
     *
     * @return array<int, string>
     */
    private function resolveRecordTypes(array $params): array
    {
        $configured = config('resources.providers.idref.record_types', []);

        $requested = $params['record_types']
            ?? ($params['endpoint'] ?? null
                ? config('resources.providers.idref.endpoint_record_types.' . $params['endpoint'])
                : null)
            ?? config('resources.providers.idref.default_record_types', ['person', 'corporate']);

        $valid = array_values(array_filter((array) $requested, fn ($type) => isset($configured[$type]['index'])));

        // An unmapped endpoint must still search something.
        return $valid ?: array_keys($configured);
    }

    /**
     * Build the Solr query.
     *
     * Per index, an exact-phrase clause boosted over a loose clause: the phrase
     * pulls exact heading matches to the top, the loose clause keeps recall.
     * Verified against "Karl Barth", where a loose-only query buried the
     * theologian below several namesakes.
     */
    private function buildQuery(string $term, array $recordTypes): string
    {
        $configured = config('resources.providers.idref.record_types', []);
        $boost = config('resources.providers.idref.phrase_boost', 10);

        $clauses = [];
        foreach ($recordTypes as $type) {
            $index = $configured[$type]['index'];
            $clauses[] = sprintf('%s:"%s"^%s', $index, $term, $boost);
            $clauses[] = sprintf('%s:(%s)', $index, $term);
        }

        return '(' . implode(' OR ', $clauses) . ')';
    }

    /**
     * @param array<int, array> $docs
     * @return array<int, object>
     */
    private function mapResults(array $docs): array
    {
        $targetUrl = config('resources.providers.idref.target_url', 'https://www.idref.fr/{provider_id}');

        $results = [];

        foreach ($docs as $doc) {
            $ppn = $doc['ppn_z'] ?? null;
            $recordType = $doc['recordtype_z'] ?? null;

            if (! $ppn || $recordType === self::BIBLIOGRAPHIC_RECORD_TYPE) {
                continue;
            }

            $heading = $doc['affcourt_z'] ?? ($doc['affcourt_r'][0] ?? $ppn);
            $variants = array_values(array_filter(
                (array) ($doc['affcourt_r'] ?? []),
                fn ($variant) => is_string($variant) && $variant !== $heading
            ));

            $results[] = (object) [
                'ppn' => (string) $ppn,
                'heading' => (string) $heading,
                'recordType' => (string) $recordType,
                'url' => str_replace('{provider_id}', (string) $ppn, $targetUrl),
                'variants' => $variants,
                'raw' => $doc,
            ];
        }

        return $results;
    }
}
