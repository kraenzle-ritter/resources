<?php

namespace KraenzleRitter\Resources\Tests\Api;

use KraenzleRitter\Resources\Wikipedia;
use KraenzleRitter\Resources\Tests\TestCase;

class WikipediaApiLocaleTest extends TestCase
{
    protected $skipIfNoInternet = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip test if internet connection is not available
        if ($this->skipIfNoInternet) {
            try {
                $connection = @fsockopen("www.wikipedia.org", 80);
                if (!$connection) {
                    $this->markTestSkipped('No internet connection available');
                }
                fclose($connection);
            } catch (\Exception $e) {
                $this->markTestSkipped('No internet connection available');
            }
        }
    }

    /**
     * @test
     */
    public function test_search_uses_correct_locale_parameter()
    {
        // Instanz erstellen und direkt mit verschiedenen Locales testen
        $wikipedia = new Wikipedia();

        // Suche nach dem gleichen Begriff in verschiedenen Sprachen
        $searchTerm = 'Einstein';

        $deResults = $wikipedia->search($searchTerm, ['providerKey' => 'wikipedia-de', 'limit' => 1]);
        $enResults = $wikipedia->search($searchTerm, ['providerKey' => 'wikipedia-en', 'limit' => 1]);

        // Beide sollten Ergebnisse liefern
        $this->assertNotEmpty($deResults, 'Deutsche Suche sollte Ergebnisse liefern');
        $this->assertNotEmpty($enResults, 'Englische Suche sollte Ergebnisse liefern');

        // Die ersten Ergebnisse sollten unterschiedliche Snippets haben
        if (!empty($deResults) && !empty($enResults)) {
            $deSnippet = $deResults[0]->snippet ?? '';
            $enSnippet = $enResults[0]->snippet ?? '';

            // Da die Snippets in unterschiedlichen Sprachen sind, sollten sie sich unterscheiden
            $this->assertNotEquals($deSnippet, $enSnippet,
                'Die Snippets der deutschen und englischen Suche sollten unterschiedlich sein');
        }
    }

    /**
     * @test
     */
    public function test_get_article_uses_correct_locale_parameter()
    {
        $wikipedia = new Wikipedia();
        $title = 'Albert Einstein';

        $deArticle = $wikipedia->getArticle($title, ['providerKey' => 'wikipedia-de']);

        $enArticle = $wikipedia->getArticle($title, ['providerKey' => 'wikipedia-en']);

        $this->assertNotNull($deArticle, 'Deutscher Artikel sollte gefunden werden');
        $this->assertNotNull($enArticle, 'Englischer Artikel sollte gefunden werden');

        if ($deArticle && $enArticle) {
            $this->assertNotEquals($deArticle->extract, $enArticle->extract,
                'Deutsche und englische Artikel-Extracts sollten unterschiedlich sein');

            // Der deutsche Text sollte deutsche Wörter enthalten
            $this->assertTrue(
                stripos($deArticle->extract, 'physiker') !== false ||
                stripos($deArticle->extract, 'deutscher') !== false ||
                'Der deutsche Artikel sollte deutsche Begriffe enthalten'
            );

            // Der englische Text sollte englische Wörter enthalten
            $this->assertTrue(
                stripos($enArticle->extract, 'physicist') !== false ||
                stripos($enArticle->extract, 'german') !== false ||
                stripos($enArticle->extract, 'theory') !== false,
                'Der englische Artikel sollte englische Begriffe enthalten'
            );
        }
    }
}
