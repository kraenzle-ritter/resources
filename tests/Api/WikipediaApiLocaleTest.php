<?php

namespace KraenzleRitter\Resources\Tests\Api;

use KraenzleRitter\Resources\Tests\Support\MockProviderClient;
use KraenzleRitter\Resources\Tests\TestCase;
use KraenzleRitter\Resources\Wikipedia;
use PHPUnit\Framework\Attributes\Group;

class WikipediaApiLocaleTest extends TestCase
{
    public function test_search_returns_the_search_list()
    {
        $mock = MockProviderClient::withFixture('wikipedia', 'search');

        $results = (new Wikipedia($mock->client))->search('Bertha von Suttner', [
            'providerKey' => 'wikipedia-de',
            'limit' => 5,
        ]);

        $expected = MockProviderClient::fixtureData('wikipedia', 'search')['query']['search'];

        $this->assertNotEmpty($results);
        $this->assertCount(count($expected), $results);
        $this->assertSame($expected[0]['title'], $results[0]->title);
        $this->assertSame($expected[0]['pageid'], $results[0]->pageid);
    }

    public function test_search_sends_the_expected_query_parameters()
    {
        $mock = MockProviderClient::withFixture('wikipedia', 'search');

        (new Wikipedia($mock->client))->search('Bertha von Suttner', [
            'providerKey' => 'wikipedia-de',
            'limit' => 3,
        ]);

        $query = $mock->lastQuery();

        $this->assertSame('query', $query['action']);
        $this->assertSame('json', $query['format']);
        $this->assertSame('search', $query['list']);
        $this->assertSame('0', $query['srnamespace']);
        $this->assertSame('3', $query['srlimit']);
        // Spaces become underscores and the search is title-scoped.
        $this->assertSame('intitle:Bertha_von_Suttner', $query['srsearch']);
    }

    public function test_search_returns_empty_array_when_nothing_matches()
    {
        $mock = MockProviderClient::withFixture('wikipedia', 'empty');

        $results = (new Wikipedia($mock->client))->search('zzzzzzzz', ['providerKey' => 'wikipedia-de']);

        $this->assertSame([], $results);
    }

    public function test_get_article_returns_the_extract()
    {
        $mock = MockProviderClient::withFixture('wikipedia', 'article');

        $article = (new Wikipedia($mock->client))->getArticle('Bertha von Suttner', [
            'providerKey' => 'wikipedia-de',
        ]);

        $this->assertNotNull($article);
        $this->assertNotSame('', $article->title);
        $this->assertNotSame('', $article->extract);
    }

    public function test_get_article_sends_the_expected_query_parameters()
    {
        $mock = MockProviderClient::withFixture('wikipedia', 'article');

        (new Wikipedia($mock->client))->getArticle('Bertha von Suttner', [
            'providerKey' => 'wikipedia-de',
        ]);

        $query = $mock->lastQuery();

        $this->assertSame('query', $query['action']);
        $this->assertSame('extracts', $query['prop']);
        $this->assertSame('Bertha_von_Suttner', $query['titles']);
        $this->assertSame('1', $query['redirects']);
    }

    /**
     * Each locale must be configured with its own API host. The client picks the
     * base URL from this config, so a wrong entry silently searches the wrong
     * Wikipedia — which is what this guards.
     */
    public function test_each_locale_is_configured_with_its_own_api_host()
    {
        $seen = [];

        foreach (['de', 'en', 'fr', 'it'] as $locale) {
            $baseUrl = config("resources.providers.wikipedia-{$locale}.base_url");

            $this->assertSame(
                "https://{$locale}.wikipedia.org/w/api.php",
                $baseUrl,
                "wikipedia-{$locale} must point at the {$locale} API host"
            );

            $seen[] = $baseUrl;
        }

        $this->assertSame($seen, array_unique($seen), 'Locales must not share a base URL');
    }

    /**
     * The original intent of this file: the same term really does return
     * different content per language. Kept as a live check.
     */
    #[Group('live-api')]
    public function test_search_returns_different_content_per_locale()
    {
        $wikipedia = new Wikipedia();

        $de = $wikipedia->search('Einstein', ['providerKey' => 'wikipedia-de', 'limit' => 1]);
        $en = $wikipedia->search('Einstein', ['providerKey' => 'wikipedia-en', 'limit' => 1]);

        $this->assertNotEmpty($de, 'German search should return results');
        $this->assertNotEmpty($en, 'English search should return results');
        $this->assertNotEquals($de[0]->snippet ?? '', $en[0]->snippet ?? '');
    }
}
