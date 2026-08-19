<?php

namespace KraenzleRitter\Resources\Tests\Livewire;

use KraenzleRitter\Resources\Anton;
use KraenzleRitter\Resources\Geonames;
use KraenzleRitter\Resources\Gnd;
use KraenzleRitter\Resources\Http\Livewire\AntonLwComponent;
use KraenzleRitter\Resources\Http\Livewire\GeonamesLwComponent;
use KraenzleRitter\Resources\Http\Livewire\GndLwComponent;
use KraenzleRitter\Resources\Http\Livewire\IdiotikonLwComponent;
use KraenzleRitter\Resources\Http\Livewire\MetagridLwComponent;
use KraenzleRitter\Resources\Http\Livewire\OrtsnamenLwComponent;
use KraenzleRitter\Resources\Http\Livewire\WikidataLwComponent;
use KraenzleRitter\Resources\Http\Livewire\WikipediaLwComponent;
use KraenzleRitter\Resources\Idiotikon;
use KraenzleRitter\Resources\Metagrid;
use KraenzleRitter\Resources\Ortsnamen;
use KraenzleRitter\Resources\Tests\TestCase;
use KraenzleRitter\Resources\Tests\TestModel;
use KraenzleRitter\Resources\Wikidata;
use KraenzleRitter\Resources\Wikipedia;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The result views build HTML by string concatenation from provider response
 * fields and emit it through {!! !!}. Those fields are third-party data: a
 * compromised or hostile upstream record must not be able to inject markup or
 * script into the host application.
 *
 * Each provider gets a fixture whose heading, description and result URL carry
 * a payload, and the rendered output must contain none of them intact.
 */
class HostileProviderResponseTest extends TestCase
{
    /**
     * @return array<string, array{0: class-string, 1: class-string, 2: string}>
     */
    public static function components(): array
    {
        return [
            'gnd' => [GndLwComponent::class, Gnd::class, 'gnd'],
            'geonames' => [GeonamesLwComponent::class, Geonames::class, 'geonames'],
            'idiotikon' => [IdiotikonLwComponent::class, Idiotikon::class, 'idiotikon'],
            'ortsnamen' => [OrtsnamenLwComponent::class, Ortsnamen::class, 'ortsnamen'],
            'metagrid' => [MetagridLwComponent::class, Metagrid::class, 'metagrid'],
            'wikidata' => [WikidataLwComponent::class, Wikidata::class, 'wikidata'],
            'wikipedia' => [WikipediaLwComponent::class, Wikipedia::class, 'wikipedia'],
            'anton' => [AntonLwComponent::class, Anton::class, 'anton'],
        ];
    }

    #[DataProvider('components')]
    public function test_hostile_provider_markup_does_not_survive_rendering(string $component, string $clientClass, string $provider)
    {
        $html = $this->renderWithHostileFixture($component, $clientClass, $provider);

        // The payload may survive as escaped *text* - that is the desired
        // outcome. What must not survive is it becoming markup, so the
        // assertions are about tag boundaries: no payload-supplied `<` may
        // reach the output unescaped.
        $this->assertStringNotContainsStringIgnoringCase('<img', $html, "{$provider}: an img element survived");
        $this->assertStringNotContainsStringIgnoringCase('<script', $html, "{$provider}: a script element survived");

        // ...and it must actually have got there. Without this the test would
        // also pass if the component silently rendered nothing at all.
        $this->assertTrue(
            str_contains($html, '&lt;img') || str_contains($html, '&lt;script'),
            "{$provider}: the hostile payload never reached the view, so this test proves nothing"
        );
    }

    #[DataProvider('components')]
    public function test_a_hostile_result_url_is_not_rendered_as_a_link(string $component, string $clientClass, string $provider)
    {
        $html = $this->renderWithHostileFixture($component, $clientClass, $provider);

        $this->assertStringNotContainsString('href="javascript:', $html, "{$provider}: a javascript: href was rendered");
        $this->assertStringNotContainsString("href='javascript:", $html, "{$provider}: a javascript: href was rendered");
    }

    #[DataProvider('components')]
    public function test_external_links_carry_noopener(string $component, string $clientClass, string $provider)
    {
        $html = $this->renderWithHostileFixture($component, $clientClass, $provider, 'search');

        if (! str_contains($html, 'target="_blank"')) {
            $this->markTestSkipped("{$provider}: no external link in the rendered output");
        }

        $blankLinks = preg_match_all('/<a\b[^>]*target="_blank"[^>]*>/i', $html, $matches);

        foreach ($matches[0] as $anchor) {
            $this->assertMatchesRegularExpression(
                '/rel="[^"]*noopener[^"]*"/i',
                $anchor,
                "{$provider}: target=_blank without rel=noopener: {$anchor}"
            );
        }

        $this->assertGreaterThan(0, $blankLinks);
    }

    private function renderWithHostileFixture(string $component, string $clientClass, string $provider, string $fixture = 'hostile'): string
    {
        if ($clientClass === Anton::class) {
            $this->fakeAntonProvider($fixture);
        } else {
            $this->fakeProvider($clientClass, $provider, $fixture);
        }

        $model = TestModel::create(['name' => 'Hostile']);

        return Livewire::test($component, $this->mountParams($component, $model))
            ->set('search', 'anything')
            ->html();
    }

    private function mountParams(string $component, TestModel $model): array
    {
        return match ($component) {
            WikipediaLwComponent::class => ['model' => $model, 'search' => '', 'providerKey' => 'wikipedia-de'],
            AntonLwComponent::class => ['model' => $model, 'search' => '', 'providerKey' => 'georgfischer', 'endpoint' => 'actors'],
            default => ['model' => $model],
        };
    }
}
