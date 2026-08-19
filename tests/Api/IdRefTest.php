<?php

namespace KraenzleRitter\Resources\Tests\Api;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use KraenzleRitter\Resources\IdRef;
use KraenzleRitter\Resources\Tests\Support\MockProviderClient;
use KraenzleRitter\Resources\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * IdRef is the French authority file (ABES). Its Solr service answers at
 * https://www.idref.fr/Sru/Solr — the core paths in the older documentation
 * (/Sru/Solr/sudoc/select) are gone, and the SRU endpoint rejects every access
 * code, so Solr is the only usable search interface.
 */
class IdRefTest extends TestCase
{
    private function client(string $fixture): array
    {
        $mock = MockProviderClient::withFixture('idref', $fixture);

        return [new IdRef($mock->client), $mock];
    }

    // --- normalisation -----------------------------------------------------

    public function test_accents_are_stripped_because_the_index_has_none()
    {
        $this->assertSame('geneve', IdRef::normalise('Genève'));
        $this->assertSame('zurich', IdRef::normalise('Zürich'));
        $this->assertSame('beauvoir simone', IdRef::normalise('Beauvoir Simoné'));
    }

    public function test_solr_metacharacters_are_stripped()
    {
        // Pasted from another field, this would otherwise be an unbalanced
        // parenthesis and a Solr parse error.
        $this->assertSame('barth karl theologien', IdRef::normalise('Barth, Karl (théologien)'));
        $this->assertSame('foo bar', IdRef::normalise('foo && bar'));
        $this->assertSame('foo bar', IdRef::normalise('foo^2 bar~'));
        $this->assertSame('a b', IdRef::normalise('a   b'));
    }

    public function test_an_empty_term_normalises_to_an_empty_string()
    {
        $this->assertSame('', IdRef::normalise(''));
        $this->assertSame('', IdRef::normalise('   '));
        $this->assertSame('', IdRef::normalise('()[]{}'));
    }

    public function test_an_empty_search_makes_no_request()
    {
        $mock = MockProviderClient::withFixture('idref', 'person-search');

        $this->assertSame([], (new IdRef($mock->client))->search(''));
        $this->assertSame([], (new IdRef($mock->client))->search('   '));
        $this->assertSame(0, $mock->requestCount());
    }

    // --- query construction ------------------------------------------------

    public function test_the_query_boosts_the_exact_phrase_and_keeps_the_loose_terms()
    {
        [$client, $mock] = $this->client('person-search');

        $client->search('Karl Barth', ['record_types' => ['person']]);

        $q = $mock->lastQuery()['q'];

        $this->assertStringContainsString('persname_t:"karl barth"^10', $q);
        $this->assertStringContainsString('persname_t:(karl barth)', $q);
    }

    public function test_only_the_requested_record_types_are_queried()
    {
        [$client, $mock] = $this->client('place-search');

        $client->search('Geneve', ['record_types' => ['place']]);

        $q = $mock->lastQuery()['q'];

        $this->assertStringContainsString('geogname_t:', $q);
        $this->assertStringNotContainsString('persname_t:', $q);
        $this->assertStringNotContainsString('corpname_t:', $q);
    }

    public function test_several_record_types_are_combined()
    {
        [$client, $mock] = $this->client('person-search');

        $client->search('Croix Rouge', ['record_types' => ['person', 'corporate']]);

        $q = $mock->lastQuery()['q'];

        $this->assertStringContainsString('persname_t:', $q);
        $this->assertStringContainsString('corpname_t:', $q);
    }

    public function test_the_catch_all_index_is_never_used()
    {
        [$client, $mock] = $this->client('person-search');

        $client->search('Beauvoir');

        // `all:` also matches Sudoc bibliographic records and ranks them above
        // the authority record.
        $this->assertStringNotContainsString('all:', $mock->lastQuery()['q']);
    }

    public function test_the_request_carries_the_solr_parameters()
    {
        [$client, $mock] = $this->client('person-search');

        $client->search('Karl Barth', ['limit' => 7]);

        $query = $mock->lastQuery();

        $this->assertSame('json', $query['wt']);
        $this->assertSame('2.2', $query['version']);
        $this->assertSame('7', $query['rows']);
        $this->assertSame('0', $query['start']);
        $this->assertStringContainsString('ppn_z', $query['fl']);
        $this->assertStringContainsString('affcourt_z', $query['fl']);
    }

    public function test_the_limit_falls_back_to_the_configured_default()
    {
        config(['resources.limit' => 3]);

        [$client, $mock] = $this->client('person-search');

        $client->search('Karl Barth');

        $this->assertSame('3', $mock->lastQuery()['rows']);
    }

    // --- result mapping ----------------------------------------------------

    public function test_a_result_exposes_the_fields_the_view_needs()
    {
        [$client] = $this->client('person-search');

        $results = $client->search('Karl Barth');

        $this->assertNotEmpty($results);

        $first = $results[0];
        $this->assertNotEmpty($first->ppn);
        $this->assertNotEmpty($first->heading);
        $this->assertNotEmpty($first->recordType);
        $this->assertSame('https://www.idref.fr/' . $first->ppn, $first->url);
        $this->assertIsArray($first->variants);
        $this->assertIsArray($first->raw);
    }

    public function test_the_theologian_is_found_and_carries_his_dates()
    {
        [$client] = $this->client('person-search');

        $results = $client->search('Karl Barth');
        $headings = array_map(fn ($r) => $r->heading, $results);

        $this->assertContains('Barth, Karl (1886-1968 ; théologien)', $headings);
    }

    public function test_a_ppn_with_a_check_character_survives_unchanged()
    {
        [$client] = $this->client('corporate-search');

        $ppns = array_map(fn ($r) => $r->ppn, $client->search('Croix Rouge'));

        $this->assertContains('12841457X', $ppns, 'A PPN ending in X must not be mangled');
    }

    public function test_bibliographic_records_are_filtered_out()
    {
        [$client] = $this->client('with-bibliographic-record');

        $results = $client->search('Beauvoir');

        $this->assertNotEmpty($results);
        foreach ($results as $result) {
            $this->assertNotSame('r', $result->recordType, 'A bibliographic record leaked into the results');
        }
    }

    public function test_variant_forms_are_exposed()
    {
        [$client] = $this->client('corporate-search');

        $results = $client->search('Croix Rouge');

        $this->assertIsArray($results[0]->variants);
    }

    public function test_no_matches_yields_an_empty_array()
    {
        [$client] = $this->client('empty');

        $this->assertSame([], $client->search('zzzzzzzzqqqq'));
    }

    // --- failure containment ----------------------------------------------

    public function test_a_server_error_yields_an_empty_array()
    {
        $mock = new MockProviderClient([new Response(500, [], 'Internal Server Error')]);

        $this->assertSame([], (new IdRef($mock->client))->search('Karl Barth'));
    }

    public function test_malformed_json_yields_an_empty_array()
    {
        [$client] = $this->client('malformed.txt');

        $this->assertSame([], $client->search('Karl Barth'));
    }

    public function test_a_payload_without_response_docs_yields_an_empty_array()
    {
        [$client] = $this->client('unexpected-shape');

        $this->assertSame([], $client->search('Karl Barth'));
    }

    public function test_a_connection_failure_yields_an_empty_array()
    {
        $mock = new MockProviderClient([
            new ConnectException('offline', new Request('GET', 'https://www.idref.fr/Sru/Solr')),
        ]);

        $this->assertSame([], (new IdRef($mock->client))->search('Karl Barth'));
    }

    // --- live smoke test ---------------------------------------------------

    #[Group('live-api')]
    public function test_the_live_service_still_answers_as_the_fixtures_assume()
    {
        $results = (new IdRef())->search('Karl Barth', ['record_types' => ['person'], 'limit' => 5]);

        $this->assertNotEmpty($results, 'IdRef Solr returned nothing for a known person');
        $this->assertMatchesRegularExpression('/^\d{8}[\dX]$/', $results[0]->ppn);
        $this->assertNotEmpty($results[0]->heading);
    }
}
